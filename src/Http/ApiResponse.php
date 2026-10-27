<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Json\JsonObject;

final readonly class ApiResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public JsonObject $body,
        public array $headers = [],
    ) {
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }
}
