<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Auth\StaticTokenProvider;
use AgentEngine\Deployment\AgentArchive;
use AgentEngine\Deployment\AgentDeployer;
use AgentEngine\Deployment\DeployRequest;
use AgentEngine\Exception\DeployFailed;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\PresignedUploader;
use AgentEngine\Resource\Builds;
use AgentEngine\Resource\Deployments;
use AgentEngine\Tests\Support\FakeTransport;
use AgentEngine\Tests\Support\TempDirectory;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentDeployerTest extends TestCase
{
    #[Test]
    public function it_runs_the_archive_build_and_deploy_sequence(): void
    {
        $transport = new FakeTransport(
            new Response(201, [], $this->json([
                'build_id' => 'bld_1',
                'upload_url' => 'https://upload.test/bld_1',
                'upload_expires_at' => '2026-10-27T20:10:00Z',
                'source_type' => 'archive',
            ])),
            new Response(200, [], ''),
            new Response(202, [], $this->json(['build_id' => 'bld_1', 'status' => 'accepted'])),
            new Response(200, [], $this->json([
                'build_id' => 'bld_1',
                'status' => 'succeeded',
                'image_uri' => 'registry.example/agent:1',
            ])),
            new Response(202, [], $this->json(['deployment_id' => 'deploy-1'])),
            new Response(200, [], $this->json([
                'deployment_id' => 'deploy-1',
                'status' => 'successful',
                'components' => [['name' => 'agent', 'available' => true, 'replicas' => 1, 'ready_replicas' => 1]],
                'health' => ['available' => true, 'checked_at' => '2026-10-27T20:00:00Z'],
            ])),
        );
        $directory = $this->agentDirectory();

        try {
            $result = $this->deployer($transport)->deploy(
                WorkspaceId::fromString('workspace-1'),
                DeployRequest::forDirectory($directory)->withLabel('release-1')->withPollInterval(1),
            );

            self::assertSame('bld_1', $result->build->buildId->value);
            self::assertSame('registry.example/agent:1', $result->build->imageUri);
            self::assertSame('deploy-1', $result->deployment?->deploymentId->value);
            self::assertTrue($result->isHealthy());
            self::assertCount(6, $transport->requests);
            self::assertSame('', $transport->requests[1]->getHeaderLine('Authorization'));

            $archiveInit = json_decode((string) $transport->requests[0]->getBody(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame('release-1', $archiveInit['label']);
            self::assertSame(['subdirectory' => ''], $archiveInit['build_target']);
            self::assertFalse($archiveInit['auto_deploy']);
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_reuses_the_active_build_when_the_start_is_rejected(): void
    {
        $transport = new FakeTransport(
            new Response(201, [], $this->json([
                'build_id' => 'bld_1',
                'upload_url' => 'https://upload.test/bld_1',
                'source_type' => 'archive',
            ])),
            new Response(200, [], ''),
            new Response(409, [], $this->json([
                'success' => false,
                'code' => 'BUILD_ALREADY_ACTIVE',
                'details' => ['existing_build_id' => 'bld_9', 'existing_status' => 'in_progress'],
            ])),
            new Response(200, [], $this->json([
                'build_id' => 'bld_9',
                'status' => 'succeeded',
                'image_uri' => 'registry.example/agent:9',
            ])),
            new Response(202, [], $this->json(['deployment_id' => 'deploy-9'])),
            new Response(200, [], $this->json([
                'deployment_id' => 'deploy-9',
                'status' => 'successful',
                'components' => [['name' => 'agent', 'available' => true, 'replicas' => 1, 'ready_replicas' => 1]],
                'health' => ['available' => true],
            ])),
        );
        $directory = $this->agentDirectory();

        try {
            $result = $this->deployer($transport)->deploy(
                WorkspaceId::fromString('workspace-1'),
                DeployRequest::forDirectory($directory)->withPollInterval(1),
            );

            self::assertSame('bld_9', $result->build->buildId->value);
            self::assertSame('deploy-9', $result->deployment?->deploymentId->value);
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_fails_when_the_build_fails(): void
    {
        $transport = new FakeTransport(
            new Response(201, [], $this->json([
                'build_id' => 'bld_1',
                'upload_url' => 'https://upload.test/bld_1',
                'source_type' => 'archive',
            ])),
            new Response(200, [], ''),
            new Response(202, [], $this->json(['build_id' => 'bld_1', 'status' => 'accepted'])),
            new Response(200, [], $this->json([
                'build_id' => 'bld_1',
                'status' => 'failed',
                'error_message' => 'uv.lock is out of date',
            ])),
        );
        $directory = $this->agentDirectory();

        try {
            $this->expectException(DeployFailed::class);

            $this->deployer($transport)->deploy(
                WorkspaceId::fromString('workspace-1'),
                DeployRequest::forDirectory($directory)->withPollInterval(1),
            );
        } finally {
            TempDirectory::remove($directory);
        }
    }

    private function deployer(FakeTransport $transport): AgentDeployer
    {
        $sender = new ApiRequestSender(
            baseUrl: BaseUrl::fromString('https://example.test'),
            tokenProvider: new StaticTokenProvider(
                AccessToken::fromParts('token', new \DateTimeImmutable('+1 hour')),
            ),
            transport: $transport,
            contractVersion: ContractVersion::default(),
        );
        $projectId = ProjectId::fromString('project-1');

        return new AgentDeployer(
            new Builds($projectId, $sender),
            new Deployments($projectId, $sender),
            new PresignedUploader($transport),
            new AgentArchive(),
        );
    }

    private function agentDirectory(): string
    {
        $directory = TempDirectory::create();
        TempDirectory::write($directory, 'agent.yaml', "name: support-agent\nentrypoint: src/main.py\n");
        TempDirectory::write($directory, 'src/main.py', 'print("hello")');

        return $directory;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function json(array $body): string
    {
        return json_encode($body, JSON_THROW_ON_ERROR);
    }
}
