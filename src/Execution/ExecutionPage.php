<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Json\JsonObject;

final readonly class ExecutionPage
{
    /**
     * @param list<Execution> $executions
     */
    public function __construct(
        public array $executions,
        public int $count,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $executions = array_map(
            static fn (mixed $execution): Execution => Execution::fromJson(JsonObject::fromArray((array) $execution)),
            $json->optionalArray('executions') ?? [],
        );

        return new self($executions, $json->optionalInteger('count') ?? count($executions));
    }
}
