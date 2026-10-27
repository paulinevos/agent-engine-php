<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class SessionPage
{
    /**
     * @param list<Session> $sessions
     */
    public function __construct(
        public array $sessions,
        public ?string $nextCursor = null,
        public bool $hasMore = false,
        public ?int $totalCount = null,
        public bool $truncated = false,
        public int $limit = 0,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $sessions = array_map(
            static fn (mixed $session): Session => Session::fromJson(JsonObject::fromArray((array) $session)),
            $json->optionalArray('sessions') ?? [],
        );

        return new self(
            sessions: $sessions,
            nextCursor: $json->optionalString('next_cursor'),
            hasMore: $json->optionalBoolean('has_more') ?? false,
            totalCount: $json->optionalInteger('total_count'),
            truncated: $json->optionalBoolean('truncated') ?? false,
            limit: $json->optionalInteger('limit') ?? count($sessions),
        );
    }

    public function nextPageCursor(): ?string
    {
        return $this->hasMore ? $this->nextCursor : null;
    }
}
