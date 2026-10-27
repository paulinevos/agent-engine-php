<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

use AgentEngine\Deployment\Build;
use AgentEngine\Deployment\Deployment;

final class DeployFailed extends AgentEngineException
{
    public function __construct(
        public readonly string $stage,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function build(Build $build): self
    {
        return new self('build', sprintf(
            'Build %s ended as %s%s.',
            $build->buildId->value,
            $build->rawStatus,
            $build->errorMessage === null ? '' : ': ' . $build->errorMessage,
        ));
    }

    public static function deployment(Deployment $deployment): self
    {
        return new self('deployment', sprintf(
            'Deployment %s ended as %s.',
            $deployment->deploymentId->value,
            $deployment->rawStatus,
        ));
    }

    public static function timedOut(string $stage, int $seconds): self
    {
        return new self($stage, sprintf('The %s did not settle within %d seconds.', $stage, $seconds));
    }
}
