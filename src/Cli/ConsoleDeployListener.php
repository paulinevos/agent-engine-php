<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

use AgentEngine\Deployment\DeployListener;
use AgentEngine\Deployment\DeployProgress;
use Symfony\Component\Console\Output\OutputInterface;

final readonly class ConsoleDeployListener implements DeployListener
{
    public function __construct(
        private OutputInterface $output,
        private bool $quiet = false,
    ) {
    }

    public function onDeployProgress(DeployProgress $progress): void
    {
        if ($this->quiet) {
            return;
        }

        $this->output->writeln(sprintf('[%s] %s', $progress->stage->value, $progress->message));
    }
}
