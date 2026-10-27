<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Support;

use AgentEngine\Http\StreamingResponse;
use AgentEngine\Http\Transport;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class FakeTransport implements Transport
{
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @var array<int, ResponseInterface> */
    private array $responses;

    /** @var list<StreamingResponse> */
    private array $streams;

    public function __construct(ResponseInterface ...$responses)
    {
        $this->responses = array_values($responses);
        $this->streams = [];
    }

    public function queueStream(StreamingResponse $stream): void
    {
        $this->streams[] = $stream;
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses);

        if ($response === null) {
            throw new \LogicException('No queued response.');
        }

        return $response;
    }

    public function stream(RequestInterface $request): StreamingResponse
    {
        $this->requests[] = $request;
        $stream = array_shift($this->streams);

        if ($stream === null) {
            throw new \LogicException('No queued stream.');
        }

        return $stream;
    }
}
