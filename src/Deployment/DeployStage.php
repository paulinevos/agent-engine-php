<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

enum DeployStage: string
{
    case Archiving = 'archiving';
    case ArchiveInitialized = 'archive_initialized';
    case Uploaded = 'uploaded';
    case BuildRequested = 'build_requested';
    case BuildStatus = 'build_status';
    case DeploymentRequested = 'deployment_requested';
    case DeploymentStatus = 'deployment_status';
    case Completed = 'completed';
}
