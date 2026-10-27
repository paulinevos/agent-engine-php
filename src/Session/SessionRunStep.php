<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class SessionRunStep
{
    public function __construct(
        public ?string $kind = null,
        public ?string $name = null,
        public ?string $status = null,
        public ?int $stepNumber = null,
        public ?float $durationMilliseconds = null,
        public ?int $startOffsetMilliseconds = null,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?int $totalTokens = null,
        public ?string $toolCallId = null,
        public ?string $spanId = null,
        public ?string $error = null,
        public ?string $memoryOperation = null,
        public ?string $reviewOutcome = null,
        public ?string $reviewer = null,
        public ?string $reviewerNotes = null,
        public ?int $waitMilliseconds = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            kind: $json->optionalString('kind'),
            name: $json->optionalString('name'),
            status: $json->optionalString('status'),
            stepNumber: $json->optionalInteger('step_number'),
            durationMilliseconds: $json->optionalNumber('duration_ms') === null
                ? null
                : (float) $json->number('duration_ms'),
            startOffsetMilliseconds: $json->optionalInteger('start_offset_ms'),
            promptTokens: $json->optionalInteger('prompt_tokens'),
            completionTokens: $json->optionalInteger('completion_tokens'),
            totalTokens: $json->optionalInteger('total_tokens'),
            toolCallId: $json->optionalString('tool_call_id'),
            spanId: $json->optionalString('span_id'),
            error: $json->optionalString('error'),
            memoryOperation: $json->optionalString('memory_op'),
            reviewOutcome: $json->optionalString('review_outcome'),
            reviewer: $json->optionalString('reviewer'),
            reviewerNotes: $json->optionalString('reviewer_notes'),
            waitMilliseconds: $json->optionalInteger('wait_ms'),
        );
    }
}
