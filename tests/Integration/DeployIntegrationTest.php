<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Integration;

use AgentEngine\AgentEngineClient;
use AgentEngine\Deployment\DeployRequest;
use AgentEngine\Tests\Support\MockRequest;
use AgentEngine\Tests\Support\MockServer;
use AgentEngine\Tests\Support\TempDirectory;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DeployIntegrationTest extends TestCase
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
    public function it_deploys_an_agent_end_to_end(): void
    {
        $directory = TempDirectory::create();
        TempDirectory::write($directory, 'agent.yaml', "name: support-agent\nentrypoint: src/main.py\n");
        TempDirectory::write($directory, 'src/main.py', 'print("hello")');

        try {
            $result = $this->client()->deployer->deploy(
                WorkspaceId::fromString('workspace-1'),
                DeployRequest::forDirectory($directory)
                    ->withLabel('integration')
                    ->withPollInterval(10),
            );

            self::assertSame('bld_1', $result->build->buildId->value);
            self::assertSame('registry.example/agent:1', $result->build->imageUri);
            self::assertSame('deploy-1', $result->deployment?->deploymentId->value);
            self::assertTrue($result->isHealthy());

            $requests = self::$server->requests();
            $archiveInit = $this->find($requests, 'POST', '/builds/archive-init');
            $upload = $this->find($requests, 'PUT', '/upload/bld_1');
            $start = $this->find($requests, 'POST', '/builds/bld_1/start');
            $deployment = $this->find($requests, 'POST', '/deployments');
            $current = $this->find($requests, 'GET', '/deployments/current');

            self::assertSame('integration', $archiveInit->json()['label']);
            self::assertArrayNotHasKey('authorization', $upload->headers);
            self::assertSame('application/gzip', $upload->headers['content-type']);
            self::assertGreaterThan(0, $upload->bodySize);
            self::assertSame('', $start->body);
            self::assertSame('{"build_id":"bld_1"}', $deployment->body);
            self::assertSame('', $current->body);
        } finally {
            TempDirectory::remove($directory);
        }
    }

    /**
     * @param list<MockRequest> $requests
     */
    private function find(array $requests, string $method, string $pathSuffix): MockRequest
    {
        foreach ($requests as $request) {
            if ($request->method === $method && str_ends_with($request->path, $pathSuffix)) {
                return $request;
            }
        }

        self::fail(sprintf('No %s request ending in %s was recorded.', $method, $pathSuffix));
    }

    private function client(): AgentEngineClient
    {
        return AgentEngineClient::forServiceAccount(
            BaseUrl::fromString(self::$server->baseUrl),
            ProjectId::fromString('project-1'),
            ClientCredentials::fromStrings('client-id', 'client-secret'),
        );
    }
}
