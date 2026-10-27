<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\AgentEngineClient;
use AgentEngine\SymfonyAi\Command\DeployCommand;
use AgentEngine\SymfonyAi\Command\InvokeCommand;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CommandTest extends TestCase
{
    #[Test]
    public function it_exposes_the_deploy_command(): void
    {
        $command = new DeployCommand($this->client(), WorkspaceId::fromString('workspace-1'));

        self::assertSame('agent-engine:deploy', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('label'));
        self::assertTrue($command->getDefinition()->hasOption('dry-run'));
        self::assertTrue($command->getDefinition()->hasOption('workspace'));
    }

    #[Test]
    public function it_exposes_the_invoke_command(): void
    {
        $command = new InvokeCommand($this->client(), WorkspaceId::fromString('workspace-1'));

        self::assertSame('agent-engine:invoke', $command->getName());
        self::assertTrue($command->getDefinition()->hasOption('stream'));
        self::assertTrue($command->getDefinition()->hasOption('session'));
        self::assertTrue($command->getDefinition()->hasOption('workspace'));
    }

    private function client(): AgentEngineClient
    {
        return AgentEngineClient::forServiceAccount(
            BaseUrl::fromString('https://example.test'),
            ProjectId::fromString('project-1'),
            ClientCredentials::fromStrings('client-id', 'client-secret'),
        );
    }
}
