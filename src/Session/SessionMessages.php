<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class SessionMessages
{
    /**
     * @param list<SessionMessage> $messages
     */
    public function __construct(
        public array $messages,
        public ?string $historyStatus = null,
        public ?string $latestStatus = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $messages = array_map(
            static fn (mixed $message): SessionMessage => SessionMessage::fromJson(JsonObject::fromArray((array) $message)),
            $json->optionalArray('messages') ?? [],
        );

        return new self(
            messages: $messages,
            historyStatus: $json->optionalString('history_status'),
            latestStatus: $json->optionalString('latest_status'),
        );
    }
}
