<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Deployment\ArchiveInit;
use AgentEngine\Deployment\Build;
use AgentEngine\Deployment\DeployRequest;
use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BuildId;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;

final readonly class Builds
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function archiveInit(WorkspaceId $workspace, DeployRequest $request): ArchiveInit
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->basePath($workspace) . '/builds/archive-init',
            body: $this->archiveInitBody($request),
        ));

        return ArchiveInit::fromJson($response->body);
    }

    public function start(WorkspaceId $workspace, BuildId $build): Build
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->basePath($workspace) . '/builds/' . rawurlencode($build->value) . '/start',
        ));

        return Build::fromJson($response->body);
    }

    public function get(WorkspaceId $workspace, BuildId $build): Build
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath($workspace) . '/builds/' . rawurlencode($build->value),
        ));

        return Build::fromJson($response->body);
    }

    private function archiveInitBody(DeployRequest $request): JsonObject
    {
        $body = [
            'label' => $request->label,
            'git_info' => $this->gitInfo($request),
            'build_target' => ['subdirectory' => $request->subdirectory],
            'auto_deploy' => $request->autoDeploy,
        ];

        return JsonObject::fromArray(array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null,
        ));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function gitInfo(DeployRequest $request): ?array
    {
        if ($request->commitSha === null && $request->branch === null) {
            return null;
        }

        return [
            'commit_sha' => $request->commitSha,
            'branch' => $request->branch,
            'dirty' => $request->dirty,
        ];
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
