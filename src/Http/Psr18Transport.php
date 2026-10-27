<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Exception\TransportFailure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Adapts any PSR-18 client. Whether a streamed response arrives incrementally
 * depends on that client; this transport reads the body stream as it becomes
 * available rather than buffering it itself.
 */
final readonly class Psr18Transport implements Transport
{
    public function __construct(private ClientInterface $client)
    {
    }

    public function send(RequestInterface $request): \Psr\Http\Message\ResponseInterface
    {
        try {
            return $this->client->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw TransportFailure::forRequest(
                $request->getMethod(),
                (string) $request->getUri(),
                $exception,
            );
        }
    }

    public function stream(RequestInterface $request): StreamingResponse
    {
        $response = $this->send($request);

        return new StreamingResponse(
            $response->getStatusCode(),
            array_map('array_values', $response->getHeaders()),
            $this->readLines($response->getBody()),
        );
    }

    /**
     * @return \Generator<int, string>
     */
    private function readLines(StreamInterface $body): \Generator
    {
        $buffer = '';

        while (!$body->eof()) {
            $buffer .= $body->read(8192);

            while (($newline = strpos($buffer, "\n")) !== false) {
                yield rtrim(substr($buffer, 0, $newline), "\r");
                $buffer = substr($buffer, $newline + 1);
            }
        }

        if ($buffer !== '') {
            yield rtrim($buffer, "\r");
        }
    }
}
