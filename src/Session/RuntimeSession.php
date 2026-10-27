<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;

final readonly class RuntimeSession
{
    public function __construct(
        public SessionId $sessionId,
        public ?string $status = null,
        public ?string $lastActivityAt = null,
        public ?string $scheduledReleaseAt = null,
        public bool $isTestSession = false,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            sessionId: SessionId::fromString($json->string('session_id')),
            status: $json->optionalString('status'),
            lastActivityAt: $json->optionalString('last_activity_at'),
            scheduledReleaseAt: $json->optionalString('scheduled_release_at'),
            isTestSession: $json->optionalBoolean('is_test_session') ?? false,
        );
    }
}
