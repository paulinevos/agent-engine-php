<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;

final readonly class ServicesRegistrar
{
    public function __construct(
        private ClientRegistrar $clients = new ClientRegistrar(),
        private PlatformRegistrar $platforms = new PlatformRegistrar(),
        private MemoryRegistrar $memory = new MemoryRegistrar(),
        private CommandRegistrar $commands = new CommandRegistrar(),
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public function register(array $config, ContainerBuilder $builder): void
    {
        $this->clients->register($config, $builder);
        $this->commands->register($config, $builder);

        if (isset($config['platform'])) {
            $this->platforms->register($config['platform'], $builder);
        }

        if (isset($config['memory'])) {
            $this->memory->register($config['memory'], $builder);
        }
    }
}
