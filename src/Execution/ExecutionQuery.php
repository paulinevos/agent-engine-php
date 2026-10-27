<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Exception\InvalidArgument;

final readonly class ExecutionQuery
{
    public function __construct(
        public ?string $status = null,
        public ?string $sessionId = null,
        public ?string $workspaceId = null,
        public ?int $limit = null,
    ) {
        if ($limit !== null && $limit < 1) {
            throw InvalidArgument::belowMinimum('Execution list limit', $limit, 1);
        }
    }

    /**
     * @return array<string, scalar|null>
     */
    public function query(): array
    {
        return [
            'status' => $this->status,
            'session_id' => $this->sessionId,
            'workspace_id' => $this->workspaceId,
            'limit' => $this->limit,
        ];
    }
}
