<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Json\JsonObject;

final readonly class ApiRequest
{
    /**
     * @param array<string, scalar|null> $query
     * @param array<string, string> $headers Already-expanded header names and values.
     */
    public function __construct(
        public HttpMethod $method,
        public string $path,
        public array $query = [],
        public ?JsonObject $body = null,
        public array $headers = [],
    ) {
    }
}
