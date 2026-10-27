<?php

declare(strict_types=1);

namespace AgentEngine\Trace;

use AgentEngine\Json\JsonObject;

final readonly class SessionTraces
{
    /**
     * @param list<Trace> $traces
     */
    public function __construct(
        public array $traces,
        public int $count,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $traces = array_map(
            static fn (mixed $trace): Trace => Trace::fromJson(JsonObject::fromArray((array) $trace)),
            $json->optionalArray('traces') ?? [],
        );

        return new self($traces, $json->optionalInteger('count') ?? count($traces));
    }
}
