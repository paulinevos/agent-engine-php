<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\DeploymentId;

final readonly class DeploymentAccepted
{
    public function __construct(public DeploymentId $deploymentId)
    {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(DeploymentId::fromString($json->string('deployment_id')));
    }
}
