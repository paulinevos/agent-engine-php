<?php

declare(strict_types=1);

namespace AgentEngine\Http;

final readonly class StreamedResponse
{
    /**
     * @param array<string, list<string>> $headers
     * @param iterable<SseFrame> $frames
     */
    public function __construct(
        public array $headers,
        public iterable $frames,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}
