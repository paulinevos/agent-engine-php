<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\Value\WorkspaceId;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;

final readonly class WorkspaceMap
{
    /**
     * @param array<string, WorkspaceId> $workspaces
     */
    private function __construct(private array $workspaces)
    {
    }

    /**
     * @param array<string, string> $workspaces
     */
    public static function fromStrings(array $workspaces): self
    {
        if ([] === $workspaces) {
            throw new InvalidArgumentException('At least one Agent Engine workspace must be configured.');
        }

        return new self(array_map(
            static fn (string $workspace): WorkspaceId => WorkspaceId::fromString($workspace),
            $workspaces,
        ));
    }

    public function contains(string $modelName): bool
    {
        return isset($this->workspaces[$modelName]);
    }

    public function workspaceFor(string $modelName): WorkspaceId
    {
        return $this->workspaces[$modelName]
            ?? throw new ModelNotFoundException(sprintf('No Agent Engine workspace is configured for model "%s".', $modelName));
    }

    /**
     * @return list<string>
     */
    public function modelNames(): array
    {
        return array_keys($this->workspaces);
    }
}
