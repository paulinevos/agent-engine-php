<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class SessionRuns
{
    /**
     * @param list<SessionRun> $runs
     */
    public function __construct(
        public array $runs,
        public int $count,
        public bool $truncated = false,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $runs = array_map(
            static fn (mixed $run): SessionRun => SessionRun::fromJson(JsonObject::fromArray((array) $run)),
            $json->optionalArray('runs') ?? [],
        );

        return new self(
            runs: $runs,
            count: $json->optionalInteger('count') ?? count($runs),
            truncated: $json->optionalBoolean('truncated') ?? false,
        );
    }
}
