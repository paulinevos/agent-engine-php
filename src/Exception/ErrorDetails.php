<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

use AgentEngine\Json\JsonObject;

final readonly class ErrorDetails
{
    /**
     * @param list<string> $parameters
     */
    public function __construct(
        public int $status,
        public string $message,
        public ?string $errorCode = null,
        public ?string $reason = null,
        public array $parameters = [],
        public JsonObject $body = new JsonObject(),
        public ?int $retryAfterSeconds = null,
    ) {
    }

    public function describe(): string
    {
        return sprintf(
            'Agent Engine API returned %d%s: %s',
            $this->status,
            $this->errorCode === null ? '' : ' (' . $this->errorCode . ')',
            $this->message,
        );
    }
}
