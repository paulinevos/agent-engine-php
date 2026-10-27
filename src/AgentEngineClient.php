<?php

declare(strict_types=1);

namespace AgentEngine;

use AgentEngine\Auth\ClientCredentialsTokenProvider;
use AgentEngine\Auth\StaticTokenProvider;
use AgentEngine\Auth\TokenProvider;
use AgentEngine\Clock\Clock;
use AgentEngine\Clock\SystemClock;
use AgentEngine\Deployment\AgentArchive;
use AgentEngine\Deployment\AgentDeployer;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\PresignedUploader;
use AgentEngine\Http\StreamTransport;
use AgentEngine\Http\Transport;
use AgentEngine\Http\UserAgent;
use AgentEngine\Resource\Builds;
use AgentEngine\Resource\Deployments;
use AgentEngine\Resource\Executions;
use AgentEngine\Resource\Health;
use AgentEngine\Resource\Invocations;
use AgentEngine\Resource\Memory;
use AgentEngine\Resource\Sessions;
use AgentEngine\Resource\Traces;
use AgentEngine\Resource\Workspaces;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\SimpleCache\CacheInterface;

/**
 * Entry point. Resources are exposed as readonly properties:
 *
 *     $client->invocations->invoke($workspace, InvokeRequest::message('Hello'));
 *     $client->executions->get($workspace, $executionId);
 */
final readonly class AgentEngineClient
{
    public function __construct(
        public ApiRequestSender $sender,
        public Invocations $invocations,
        public Executions $executions,
        public Sessions $sessions,
        public Traces $traces,
        public Memory $memory,
        public Workspaces $workspaces,
        public Builds $builds,
        public Deployments $deployments,
        public AgentDeployer $deployer,
        public Health $health,
    ) {
    }

    public static function forServiceAccount(
        BaseUrl $baseUrl,
        ProjectId $projectId,
        ClientCredentials $credentials,
        ?Transport $transport = null,
        ?ContractVersion $contractVersion = null,
        ?CacheInterface $cache = null,
        ?Clock $clock = null,
        ?UserAgent $userAgent = null,
    ): self {
        $transport ??= new StreamTransport();
        $factory = new Psr17Factory();

        $tokenProvider = new ClientCredentialsTokenProvider(
            baseUrl: $baseUrl,
            credentials: $credentials,
            transport: $transport,
            requestFactory: $factory,
            streamFactory: $factory,
            clock: $clock ?? new SystemClock(),
            cache: $cache,
        );

        return self::withTokenProvider(
            $baseUrl,
            $projectId,
            $tokenProvider,
            $transport,
            $contractVersion,
            $userAgent,
        );
    }

    public static function withAccessToken(
        BaseUrl $baseUrl,
        ProjectId $projectId,
        AccessToken $accessToken,
        ?Transport $transport = null,
        ?ContractVersion $contractVersion = null,
        ?UserAgent $userAgent = null,
    ): self {
        return self::withTokenProvider(
            $baseUrl,
            $projectId,
            new StaticTokenProvider($accessToken),
            $transport,
            $contractVersion,
            $userAgent,
        );
    }

    public static function withTokenProvider(
        BaseUrl $baseUrl,
        ProjectId $projectId,
        TokenProvider $tokenProvider,
        ?Transport $transport = null,
        ?ContractVersion $contractVersion = null,
        ?UserAgent $userAgent = null,
    ): self {
        $transport ??= new StreamTransport();
        $factory = new Psr17Factory();

        $sender = new ApiRequestSender(
            baseUrl: $baseUrl,
            tokenProvider: $tokenProvider,
            transport: $transport,
            contractVersion: $contractVersion ?? ContractVersion::default(),
            userAgent: $userAgent ?? UserAgent::default(),
            requestFactory: $factory,
            streamFactory: $factory,
        );

        $builds = new Builds($projectId, $sender);
        $deployments = new Deployments($projectId, $sender);

        return new self(
            $sender,
            new Invocations($projectId, $sender),
            new Executions($projectId, $sender),
            new Sessions($projectId, $sender),
            new Traces($projectId, $sender),
            new Memory($projectId, $sender),
            new Workspaces($projectId, $sender),
            $builds,
            $deployments,
            new AgentDeployer(
                $builds,
                $deployments,
                new PresignedUploader($transport, $factory, $factory),
                new AgentArchive(),
            ),
            new Health($baseUrl, $transport, $factory),
        );
    }
}
