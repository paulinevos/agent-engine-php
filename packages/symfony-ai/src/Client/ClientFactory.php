<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Client;

use AgentEngine\AgentEngineClient;

interface ClientFactory
{
    public function create(): AgentEngineClient;
}
