<?php

declare(strict_types=1);

namespace AgentEngine\Http;

final readonly class SseFrame
{
    public function __construct(
        public string $data,
        public ?string $event = null,
        public ?string $id = null,
        public ?int $retryMilliseconds = null,
    ) {
    }
}
