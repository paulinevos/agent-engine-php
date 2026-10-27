<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

use AgentEngine\Http\UserAgent;
use Symfony\Component\Console\Application;

final class AgentEngineApplication extends Application
{
    public function __construct()
    {
        parent::__construct('agent-engine', UserAgent::VERSION);

        $this->addCommands([
            new DeployCommand(),
            new InvokeCommand(),
        ]);
    }
}
