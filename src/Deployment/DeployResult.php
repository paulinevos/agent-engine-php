<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

final readonly class DeployResult
{
    public function __construct(
        public Build $build,
        public ArchiveInit $archiveInit,
        public ?Deployment $deployment = null,
    ) {
    }

    public function isHealthy(): bool
    {
        return $this->build->isSuccessful() && ($this->deployment?->isHealthy() ?? false);
    }
}
