<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\DependencyInjection;

use AgentEngine\SymfonyAi\Memory\AgentEngineMemoryProvider;
use AgentEngine\SymfonyAi\Memory\MemoryOptions;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final readonly class MemoryRegistrar
{
    /**
     * @param array<string, mixed> $values
     */
    public function register(array $values, ContainerBuilder $builder): void
    {
        $config = new ConfigValues($values);
        $sessionId = $config->optionalString('session_id');

        $options = new MemoryOptions(
            defaultUserId: UserId::fromString($config->string('user_id')),
            defaultSessionId: $sessionId === null ? null : SessionId::fromString($sessionId),
            maxTokens: $config->optionalInteger('max_tokens'),
            topK: $config->optionalInteger('top_k'),
            enabledSources: $config->stringList('enabled_sources'),
            formatStyle: $config->optionalString('format_style'),
        );

        $builder->register('agent_engine.memory_provider', AgentEngineMemoryProvider::class)
            ->setArguments([new Reference('agent_engine.client'), $options]);
    }
}
