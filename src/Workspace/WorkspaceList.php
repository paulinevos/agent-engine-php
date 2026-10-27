<?php

declare(strict_types=1);

namespace AgentEngine\Workspace;

use AgentEngine\Json\JsonObject;

final readonly class WorkspaceList
{
    /**
     * @param list<Workspace> $workspaces
     */
    public function __construct(
        public array $workspaces,
        public int $total,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $workspaces = array_map(
            static fn (mixed $workspace): Workspace => Workspace::fromJson(JsonObject::fromArray((array) $workspace)),
            $json->optionalArray('workspaces') ?? [],
        );

        return new self($workspaces, $json->optionalInteger('total') ?? count($workspaces));
    }
}
