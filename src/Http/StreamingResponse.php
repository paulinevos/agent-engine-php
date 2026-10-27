<?php

declare(strict_types=1);

namespace AgentEngine\Http;

/**
 * Response headers and raw body lines from a streaming request. Lines are
 * yielded as they arrive, without the line terminator.
 */
final readonly class StreamingResponse
{
    /**
     * @param array<string, list<string>> $headers
     * @param iterable<string> $lines
     */
    public function __construct(
        public int $status,
        public array $headers,
        public iterable $lines,
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function body(): string
    {
        $body = [];

        foreach ($this->lines as $line) {
            $body[] = $line;
        }

        return implode("\n", $body);
    }
}
