<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Support;

final readonly class MockRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $method,
        public string $path,
        public string $query,
        public array $headers,
        public string $body,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $headers = $data['headers'] ?? [];

        return new self(
            method: (string) ($data['method'] ?? ''),
            path: (string) ($data['path'] ?? ''),
            query: (string) ($data['query'] ?? ''),
            headers: is_array($headers) ? array_map('strval', $headers) : [],
            body: (string) ($data['body'] ?? ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
