<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class RuntimeSessions
{
    /**
     * @param list<RuntimeSession> $sessions
     */
    public function __construct(public array $sessions)
    {
    }

    public static function fromJson(JsonObject $json): self
    {
        $sessions = array_map(
            static fn (mixed $session): RuntimeSession => RuntimeSession::fromJson(JsonObject::fromArray((array) $session)),
            $json->optionalArray('sessions') ?? [],
        );

        return new self($sessions);
    }
}
