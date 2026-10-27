<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

final readonly class DeployProgress
{
    /**
     * @param array<string, mixed> $context
     */
    public function __construct(
        public DeployStage $stage,
        public string $message,
        public array $context = [],
    ) {
    }
}
