<?php

declare(strict_types=1);

namespace AgentEngine\Workspace;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\WorkspaceId;

final readonly class Workspace
{
    public function __construct(
        public WorkspaceId $workspaceId,
        public ?string $name = null,
        public ?string $description = null,
        public ?string $framework = null,
        public ?string $language = null,
        public ?string $status = null,
        public ?string $releaseMode = null,
        public ?string $orgId = null,
        public ?string $projectId = null,
        public ?string $createdAt = null,
        public ?string $lastInvokedAt = null,
        public ?string $currentDeploymentId = null,
        public ?string $agentHttpEndpoint = null,
        public ?bool $autoDeploy = null,
        public ?int $invocations = null,
        public ?float $p95LatencyMilliseconds = null,
        public ?float $completionRate = null,
        public ?JsonObject $features = null,
        public ?JsonObject $agentCard = null,
        public ?JsonObject $gitOps = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            workspaceId: WorkspaceId::fromString($json->string('id')),
            name: $json->optionalString('name'),
            description: $json->optionalString('description'),
            framework: $json->optionalString('framework'),
            language: $json->optionalString('language'),
            status: $json->optionalString('status'),
            releaseMode: $json->optionalString('release_mode'),
            orgId: $json->optionalString('org_id'),
            projectId: $json->optionalString('project_id'),
            createdAt: $json->optionalString('created_date'),
            lastInvokedAt: $json->optionalString('last_invoked_at'),
            currentDeploymentId: $json->optionalString('current_deployment_id'),
            agentHttpEndpoint: $json->optionalString('agent_http_endpoint'),
            autoDeploy: $json->optionalBoolean('auto_deploy'),
            invocations: $json->optionalInteger('invocations'),
            p95LatencyMilliseconds: $json->optionalNumber('p95_latency_ms') === null
                ? null
                : (float) $json->number('p95_latency_ms'),
            completionRate: $json->optionalNumber('completion_rate') === null
                ? null
                : (float) $json->number('completion_rate'),
            features: $json->optionalObject('features'),
            agentCard: $json->optionalObject('agent_card'),
            gitOps: $json->optionalObject('gitops'),
        );
    }

    public function usesCustomParser(): bool
    {
        return $this->features?->optionalBoolean('use_custom_parser') ?? false;
    }
}
