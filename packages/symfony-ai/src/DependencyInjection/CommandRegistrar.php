<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use AgentEngine\SymfonyAi\Command\DeployCommand;
use AgentEngine\SymfonyAi\Command\InvokeCommand;
use AgentEngine\Value\WorkspaceId;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class CommandRegistrar
{
    /**
     * @param array<string, mixed> $config
     */
    public function register(array $config, ContainerBuilder $builder): void
    {
        $workspace = $this->workspace($config['workspace_id'] ?? null, $builder);

        $builder->register('agent_engine.deploy_command', DeployCommand::class)
            ->setArguments([new Reference('agent_engine.client'), $workspace])
            ->addTag('console.command');
        $builder->register('agent_engine.invoke_command', InvokeCommand::class)
            ->setArguments([new Reference('agent_engine.client'), $workspace])
            ->addTag('console.command');
    }

    private function workspace(mixed $workspaceId, ContainerBuilder $builder): ?Reference
    {
        if (!is_string($workspaceId) || $workspaceId === '') {
            return null;
        }

        $builder->register('agent_engine.workspace_id', WorkspaceId::class)
            ->setFactory([WorkspaceId::class, 'fromString'])
            ->setArguments([$workspaceId]);
        $builder->setAlias(WorkspaceId::class, 'agent_engine.workspace_id');

        return new Reference('agent_engine.workspace_id');
    }
}
