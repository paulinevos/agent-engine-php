<?php

declare(strict_types=1);

namespace AgentEngine\Trace;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\SessionId;

final readonly class Trace
{
    /**
     * @param list<mixed> $events
     */
    public function __construct(
        public ?string $traceId = null,
        public ?string $spanId = null,
        public ?string $parentSpanId = null,
        public ?string $name = null,
        public ?string $kind = null,
        public ?int $startTimeNanoseconds = null,
        public ?int $endTimeNanoseconds = null,
        public ?int $durationNanoseconds = null,
        public ?SessionId $sessionId = null,
        public ?SessionId $rootSessionId = null,
        public ?ExecutionId $rootExecutionId = null,
        public JsonObject $status = new JsonObject(),
        public JsonObject $attributes = new JsonObject(),
        public JsonObject $resource = new JsonObject(),
        public array $events = [],
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            traceId: $json->optionalString('trace_id'),
            spanId: $json->optionalString('span_id'),
            parentSpanId: $json->optionalString('parent_span_id'),
            name: $json->optionalString('name'),
            kind: $json->optionalString('kind'),
            startTimeNanoseconds: $json->optionalInteger('start_time_ns'),
            endTimeNanoseconds: $json->optionalInteger('end_time_ns'),
            durationNanoseconds: $json->optionalInteger('duration_ns'),
            sessionId: self::sessionId($json, 'session_id'),
            rootSessionId: self::sessionId($json, 'root_session_id'),
            rootExecutionId: $json->optionalString('root_execution_id') === null
                ? null
                : ExecutionId::fromString($json->string('root_execution_id')),
            status: $json->optionalObject('status') ?? new JsonObject(),
            attributes: $json->optionalObject('attributes') ?? new JsonObject(),
            resource: $json->optionalObject('resource') ?? new JsonObject(),
            events: $json->optionalArray('events') ?? [],
        );
    }

    private static function sessionId(JsonObject $json, string $key): ?SessionId
    {
        $value = $json->optionalString($key);

        return $value === null ? null : SessionId::fromString($value);
    }
}
