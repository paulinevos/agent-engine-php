<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Deployment\Deployment;
use AgentEngine\Deployment\DeploymentAccepted;
use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BuildId;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;

final readonly class Deployments
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function create(WorkspaceId $workspace, ?BuildId $build = null): DeploymentAccepted
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->basePath($workspace) . '/deployments',
            body: $build === null ? new JsonObject() : JsonObject::fromArray(['build_id' => $build->value]),
        ));

        return DeploymentAccepted::fromJson($response->body);
    }

    public function current(WorkspaceId $workspace): Deployment
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath($workspace) . '/deployments/current',
        ));

        return Deployment::fromJson($response->body);
    }

    private function basePath(WorkspaceId $workspace): string
    {
        return sprintf(
            '/api/v1/projects/%s/workspaces/%s',
            rawurlencode($this->projectId->value),
            rawurlencode($workspace->value),
        );
    }
}
