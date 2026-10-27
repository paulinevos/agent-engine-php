<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\WorkspaceId;

final readonly class SessionRun
{
    /**
     * @param list<SessionRunStep> $steps
     */
    public function __construct(
        public ?ExecutionId $executionId = null,
        public ?SessionId $sessionId = null,
        public ?WorkspaceId $workspaceId = null,
        public ?string $status = null,
        public ?int $runNumber = null,
        public ?string $summary = null,
        public ?string $error = null,
        public ?string $errorCode = null,
        public ?string $startedAt = null,
        public int $durationMilliseconds = 0,
        public int $activeDurationMilliseconds = 0,
        public ?int $timeToFirstEventMilliseconds = null,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public int $totalTokens = 0,
        public int $waitMilliseconds = 0,
        public int $memoryRecalls = 0,
        public int $memorySaves = 0,
        public ?string $userId = null,
        public ?string $invokerUserId = null,
        public array $steps = [],
        public ?SessionRunStepRef $slowestStep = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $steps = array_map(
            static fn (mixed $step): SessionRunStep => SessionRunStep::fromJson(JsonObject::fromArray((array) $step)),
            $json->optionalArray('steps') ?? [],
        );

        return new self(
            executionId: $json->optionalString('execution_id') === null
                ? null
                : ExecutionId::fromString($json->string('execution_id')),
            sessionId: $json->optionalString('session_id') === null
                ? null
                : SessionId::fromString($json->string('session_id')),
            workspaceId: $json->optionalString('workspace_id') === null
                ? null
                : WorkspaceId::fromString($json->string('workspace_id')),
            status: $json->optionalString('status'),
            runNumber: $json->optionalInteger('run_number'),
            summary: $json->optionalString('summary'),
            error: $json->optionalString('error'),
            errorCode: $json->optionalString('error_code'),
            startedAt: $json->optionalString('started_at'),
            durationMilliseconds: $json->optionalInteger('duration_ms') ?? 0,
            activeDurationMilliseconds: $json->optionalInteger('active_duration_ms') ?? 0,
            timeToFirstEventMilliseconds: $json->optionalInteger('time_to_first_event_ms'),
            promptTokens: $json->optionalInteger('prompt_tokens'),
            completionTokens: $json->optionalInteger('completion_tokens'),
            totalTokens: $json->optionalInteger('total_tokens') ?? 0,
            waitMilliseconds: $json->optionalInteger('wait_ms') ?? 0,
            memoryRecalls: $json->optionalInteger('memory_recalls') ?? 0,
            memorySaves: $json->optionalInteger('memory_saves') ?? 0,
            userId: $json->optionalString('user_id'),
            invokerUserId: $json->optionalString('invoker_user_id'),
            steps: $steps,
            slowestStep: $json->optionalObject('slowest_step') === null
                ? null
                : SessionRunStepRef::fromJson($json->object('slowest_step')),
        );
    }
}
