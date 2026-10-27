<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;

final readonly class RuntimeSessionStop
{
    public function __construct(
        public SessionId $sessionId,
        public string $status,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            SessionId::fromString($json->string('session_id')),
            $json->optionalString('status') ?? 'unknown',
        );
    }
}
