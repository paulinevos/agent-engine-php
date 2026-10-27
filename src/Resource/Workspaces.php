<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;
use AgentEngine\Workspace\Workspace;
use AgentEngine\Workspace\WorkspaceList;

final readonly class Workspaces
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function list(): WorkspaceList
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath(),
        ));

        return WorkspaceList::fromJson($response->body);
    }

    public function get(WorkspaceId $workspace): Workspace
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath() . '/' . rawurlencode($workspace->value),
        ));

        return Workspace::fromJson($response->body);
    }

    private function basePath(): string
    {
        return sprintf('/api/v1/projects/%s/workspaces', rawurlencode($this->projectId->value));
    }
}
