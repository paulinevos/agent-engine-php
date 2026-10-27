<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\WorkspaceId;

final readonly class Session
{
    public function __construct(
        public SessionId $sessionId,
        public ?WorkspaceId $workspaceId = null,
        public ?string $userId = null,
        public ?string $visibility = null,
        public int $turns = 0,
        public int $totalTokens = 0,
        public int $totalDurationMilliseconds = 0,
        public int $activeDurationMilliseconds = 0,
        public int $waitMilliseconds = 0,
        public ?string $latestStatus = null,
        public ?string $firstMessagePreview = null,
        public ?string $createdAt = null,
        public ?string $lastActivity = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            sessionId: SessionId::fromString($json->string('session_id')),
            workspaceId: $json->optionalString('workspace_id') === null
                ? null
                : WorkspaceId::fromString($json->string('workspace_id')),
            userId: $json->optionalString('user_id'),
            visibility: $json->optionalString('visibility'),
            turns: $json->optionalInteger('turns') ?? 0,
            totalTokens: $json->optionalInteger('total_tokens') ?? 0,
            totalDurationMilliseconds: $json->optionalInteger('total_duration_ms') ?? 0,
            activeDurationMilliseconds: $json->optionalInteger('active_duration_ms') ?? 0,
            waitMilliseconds: $json->optionalInteger('wait_ms') ?? 0,
            latestStatus: $json->optionalString('latest_status'),
            firstMessagePreview: $json->optionalString('first_message_preview'),
            createdAt: $json->optionalString('created_at'),
            lastActivity: $json->optionalString('last_activity'),
        );
    }
}
