<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Integration;

use AgentEngine\Cli\DeployCommand;
use AgentEngine\Cli\InvokeCommand;
use AgentEngine\Tests\Support\MockServer;
use AgentEngine\Tests\Support\TempDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class CliCommandTest extends TestCase
{
    private static MockServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = MockServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        self::$server->clearRequests();
    }

    #[Test]
    public function it_deploys_from_the_cli(): void
    {
        $directory = TempDirectory::create();
        TempDirectory::write($directory, 'agent.yaml', "name: support-agent\nentrypoint: src/main.py\n");
        TempDirectory::write($directory, 'src/main.py', 'print("hello")');

        try {
            $tester = new CommandTester(new DeployCommand());
            $exitCode = $tester->execute([
                'path' => $directory,
                '--project' => 'project-1',
                '--workspace' => 'workspace-1',
                '--base-url' => self::$server->baseUrl,
                '--client-id' => 'client-id',
                '--client-secret' => 'client-secret',
                '--label' => 'cli-test',
                '--poll-interval' => '10',
                '--json' => true,
            ], ['capture_stderr_separately' => true]);

            self::assertSame(0, $exitCode);

            $payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame('bld_1', $payload['build_id']);
            self::assertSame('succeeded', $payload['build_status']);
            self::assertSame('deploy-1', $payload['deployment_id']);
            self::assertTrue($payload['healthy']);
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_invokes_from_the_cli(): void
    {
        $tester = new CommandTester(new InvokeCommand());
        $exitCode = $tester->execute([
            'message' => 'hi',
            '--project' => 'project-1',
            '--workspace' => 'workspace-1',
            '--base-url' => self::$server->baseUrl,
            '--client-id' => 'client-id',
            '--client-secret' => 'client-secret',
            '--json' => true,
        ]);

        self::assertSame(0, $exitCode);

        $payload = json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('Hello from the mock agent: hi', $payload['response']);
        self::assertSame('session-generated', $payload['session_id']);
    }

    #[Test]
    public function it_rejects_a_missing_agent_manifest_in_dry_run(): void
    {
        $directory = TempDirectory::create();

        try {
            $tester = new CommandTester(new DeployCommand());
            $exitCode = $tester->execute([
                'path' => $directory,
                '--dry-run' => true,
            ]);

            self::assertSame(1, $exitCode);
        } finally {
            TempDirectory::remove($directory);
        }
    }
}
