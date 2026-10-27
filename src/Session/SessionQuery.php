<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Exception\InvalidArgument;

final readonly class SessionQuery
{
    public function __construct(
        public ?string $workspaceId = null,
        public ?string $status = null,
        public ?string $since = null,
        public ?string $until = null,
        public ?int $limit = null,
        public ?string $cursor = null,
    ) {
        if ($limit !== null && $limit < 1) {
            throw InvalidArgument::belowMinimum('Session page limit', $limit, 1);
        }
    }

    public function withCursor(string $cursor): self
    {
        return new self($this->workspaceId, $this->status, $this->since, $this->until, $this->limit, $cursor);
    }

    /**
     * @return array<string, scalar|null>
     */
    public function query(): array
    {
        return [
            'workspace_id' => $this->workspaceId,
            'status' => $this->status,
            'since' => $this->since,
            'until' => $this->until,
            'limit' => $this->limit,
            'cursor' => $this->cursor,
        ];
    }
}
