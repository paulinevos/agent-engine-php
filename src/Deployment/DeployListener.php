<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

interface DeployListener
{
    public function onDeployProgress(DeployProgress $progress): void;
}
