<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use AgentEngine\SymfonyAi\Platform\AgentEngineModelCatalog;
use AgentEngine\SymfonyAi\Platform\AgentEngineModelClient;
use AgentEngine\SymfonyAi\Platform\AgentEngineResultConverter;
use AgentEngine\SymfonyAi\Platform\ExceptionTranslator;
use AgentEngine\SymfonyAi\Platform\InvocationOptionsFactory;
use AgentEngine\SymfonyAi\Platform\WorkspaceMap;
use Symfony\AI\Platform\Provider;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class PlatformRegistrar
{
    /**
     * @param array<string, mixed> $values
     */
    public function register(array $values, ContainerBuilder $builder): void
    {
        $config = new ConfigValues($values);
        $name = $config->string('name');
        $workspaces = WorkspaceMap::fromStrings($config->stringMap('models'));

        $builder->register('agent_engine.platform.exception_translator', ExceptionTranslator::class);
        $builder->register('agent_engine.platform.model_catalog', AgentEngineModelCatalog::class)
            ->setFactory([AgentEngineModelCatalog::class, 'for'])
            ->setArguments([$workspaces]);
        $builder->register('agent_engine.platform.options', InvocationOptionsFactory::class);
        $builder->register('agent_engine.platform.model_client', AgentEngineModelClient::class)
            ->setArguments([
                new Reference('agent_engine.client'),
                $workspaces,
                new Reference('agent_engine.platform.exception_translator'),
                new Reference('agent_engine.platform.options'),
            ]);
        $builder->register('agent_engine.platform.result_converter', AgentEngineResultConverter::class);
        $builder->register('agent_engine.platform', Provider::class)
            ->setArguments([
                $name,
                [new Reference('agent_engine.platform.model_client')],
                [new Reference('agent_engine.platform.result_converter')],
                new Reference('agent_engine.platform.model_catalog'),
            ])
            ->addTag('ai.platform', ['name' => $name]);
    }
}
