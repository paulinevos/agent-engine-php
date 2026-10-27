<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Support;

use AgentEngine\Http\StreamingResponse;
use AgentEngine\Http\Transport;
use Nyholm\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class RecordingTransport implements Transport
{
    /**
     * @var list<RequestInterface>
     */
    public array $requests = [];

    /**
     * @param array<string, list<string>> $headers
     * @param iterable<string>            $lines
     */
    public function __construct(
        private readonly string $body = '{}',
        private readonly array $headers = [],
        private readonly iterable $lines = [],
    ) {
    }

    public function send(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return new Response(200, $this->headers, $this->body);
    }

    public function stream(RequestInterface $request): StreamingResponse
    {
        $this->requests[] = $request;

        return new StreamingResponse(200, $this->headers, $this->lines);
    }
}
