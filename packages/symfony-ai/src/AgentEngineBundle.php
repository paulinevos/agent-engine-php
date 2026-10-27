<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi;

use AgentEngine\SymfonyAi\DependencyInjection\ServicesRegistrar;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class AgentEngineBundle extends AbstractBundle
{
    protected string $extensionAlias = 'agent_engine';

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->import('../config/configuration.php');
    }

    /**
     * @param array<string, mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        (new ServicesRegistrar())->register($config, $builder);
    }
}
