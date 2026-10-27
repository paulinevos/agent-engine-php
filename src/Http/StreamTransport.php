<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Exception\TransportFailure;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Default transport. Uses PHP's HTTP stream wrapper, so streaming responses
 * are delivered line by line rather than buffered by an HTTP client.
 */
final readonly class StreamTransport implements Transport
{
    public function __construct(
        private float $timeoutSeconds = 300.0,
        private ResponseFactoryInterface $responseFactory = new Psr17Factory(),
        private StreamFactoryInterface $streamFactory = new Psr17Factory(),
    ) {
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        $handle = $this->open($request);
        $meta = $this->metaOf($handle);
        $body = @stream_get_contents($handle);
        fclose($handle);

        if ($body === false) {
            throw TransportFailure::forRequest(
                $request->getMethod(),
                (string) $request->getUri(),
                new \RuntimeException('The response body could not be read.'),
            );
        }

        return $this->responseFrom($meta, $body);
    }

    public function stream(RequestInterface $request): StreamingResponse
    {
        $handle = $this->open($request);
        $meta = $this->metaOf($handle);

        return new StreamingResponse($meta->status, $meta->headers, $this->readLines($handle));
    }

    /**
     * @param resource $handle
     */
    private function metaOf($handle): ResponseMeta
    {
        $wrapperData = stream_get_meta_data($handle)['wrapper_data'] ?? [];

        if (!is_array($wrapperData)) {
            return ResponseMeta::fromHeaderLines([]);
        }

        return ResponseMeta::fromHeaderLines(array_values(array_filter($wrapperData, 'is_string')));
    }

    /**
     * @param resource $handle
     *
     * @return \Generator<int, string>
     */
    private function readLines($handle): \Generator
    {
        try {
            while (($line = fgets($handle)) !== false) {
                yield rtrim($line, "\r\n");
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @return resource
     */
    private function open(RequestInterface $request)
    {
        $handle = @fopen(
            (string) $request->getUri(),
            'rb',
            false,
            stream_context_create($this->contextOptions($request)),
        );

        if ($handle === false) {
            throw TransportFailure::forRequest(
                $request->getMethod(),
                (string) $request->getUri(),
                new \RuntimeException('The connection could not be opened.'),
            );
        }

        return $handle;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function contextOptions(RequestInterface $request): array
    {
        return [
            'http' => [
                'method' => $request->getMethod(),
                'header' => $this->headerLines($request),
                'content' => (string) $request->getBody(),
                'ignore_errors' => true,
                'timeout' => $this->timeoutSeconds,
                'follow_location' => 1,
                'max_redirects' => 5,
                'protocol_version' => 1.1,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ];
    }

    private function headerLines(RequestInterface $request): string
    {
        $lines = [];

        foreach ($request->getHeaders() as $name => $values) {
            $lines[] = $name . ': ' . implode(', ', $values);
        }

        return implode("\r\n", $lines);
    }

    private function responseFrom(ResponseMeta $meta, string $body): ResponseInterface
    {
        $response = $this->responseFactory
            ->createResponse($meta->status)
            ->withBody($this->streamFactory->createStream($body));

        foreach ($meta->headers as $name => $values) {
            $response = $response->withHeader($name, $values);
        }

        return $response;
    }
}
