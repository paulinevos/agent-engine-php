<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Functional;

use AgentEngine\AgentEngineClient;
use AgentEngine\SymfonyAi\AgentEngineBundle;
use AgentEngine\SymfonyAi\Memory\AgentEngineMemoryProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Provider;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;

final class BundleConfigurationTest extends TestCase
{
    #[Test]
    public function it_wires_a_service_account_client_and_provider(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
            'platform' => ['models' => ['support-agent' => 'workspace-1']],
            'memory' => ['user_id' => 'user-1'],
        ]);

        self::assertTrue($container->hasDefinition('agent_engine.client'));
        self::assertTrue($container->hasAlias(AgentEngineClient::class));
        self::assertTrue($container->hasDefinition('agent_engine.deploy_command'));
        self::assertTrue($container->hasDefinition('agent_engine.invoke_command'));
        self::assertSame(Provider::class, $container->getDefinition('agent_engine.platform')->getClass());
        self::assertSame(
            [['name' => 'agent_engine']],
            $container->getDefinition('agent_engine.platform')->getTags()['ai.platform'],
        );
        self::assertTrue($container->hasDefinition('agent_engine.memory_provider'));
    }

    #[Test]
    public function it_instantiates_the_wired_services(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
            'platform' => ['models' => ['support-agent' => 'workspace-1']],
            'memory' => ['user_id' => 'user-1'],
        ]);

        foreach (['agent_engine.client', 'agent_engine.platform', 'agent_engine.memory_provider'] as $id) {
            $container->getDefinition($id)->setPublic(true);
        }

        $container->compile();

        self::assertInstanceOf(AgentEngineClient::class, $container->get('agent_engine.client'));
        self::assertInstanceOf(Provider::class, $container->get('agent_engine.platform'));
        self::assertInstanceOf(AgentEngineMemoryProvider::class, $container->get('agent_engine.memory_provider'));
    }

    #[Test]
    public function it_registers_a_default_workspace_for_the_commands(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'workspace_id' => 'workspace-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
        ]);

        self::assertTrue($container->hasDefinition('agent_engine.workspace_id'));
        self::assertTrue($container->hasDefinition('agent_engine.deploy_command'));
        self::assertTrue($container->hasDefinition('agent_engine.invoke_command'));
    }

    #[Test]
    public function it_allows_commands_without_a_default_workspace(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
        ]);

        self::assertFalse($container->hasDefinition('agent_engine.workspace_id'));
        self::assertTrue($container->hasDefinition('agent_engine.deploy_command'));
        self::assertTrue($container->hasDefinition('agent_engine.invoke_command'));
    }

    #[Test]
    public function it_wires_an_access_token_client(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => [
                'access_token' => 'token',
                'access_token_expires_at' => '2030-01-01T00:00:00+00:00',
            ],
        ]);

        self::assertTrue($container->hasDefinition('agent_engine.client_factory'));
        self::assertFalse($container->hasDefinition('agent_engine.platform'));
        self::assertFalse($container->hasDefinition('agent_engine.memory_provider'));
    }

    #[Test]
    public function it_wires_a_token_provider_client(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => ['token_provider' => 'app.token_provider'],
        ]);

        self::assertTrue($container->hasDefinition('agent_engine.client_factory'));
    }

    #[Test]
    public function it_wraps_a_psr18_client_as_the_transport(): void
    {
        $container = $this->load([
            'project_id' => 'project-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
            'psr18_client' => 'http_client',
        ]);

        self::assertTrue($container->hasDefinition('agent_engine.transport'));
    }

    #[Test]
    public function it_rejects_missing_authentication(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'project_id' => 'project-1',
            'auth' => [],
        ]);
    }

    #[Test]
    public function it_rejects_an_empty_platform_model_map(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        $this->load([
            'project_id' => 'project-1',
            'auth' => ['client_id' => 'id', 'client_secret' => 'secret'],
            'platform' => ['models' => []],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $container->setParameter('kernel.project_dir', dirname(__DIR__, 2));
        $bundle = new AgentEngineBundle();
        $bundle->build($container);

        $extension = $bundle->getContainerExtension();
        self::assertInstanceOf(ExtensionInterface::class, $extension);

        $extension->load([$config], $container);

        return $container;
    }
}
