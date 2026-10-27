<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Client;

use AgentEngine\AgentEngineClient;
use AgentEngine\Http\Transport;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;
use Psr\SimpleCache\CacheInterface;

final readonly class ServiceAccountClientFactory implements ClientFactory
{
    public function __construct(
        private BaseUrl $baseUrl,
        private ProjectId $projectId,
        private ClientCredentials $credentials,
        private ?Transport $transport = null,
        private ?ContractVersion $contractVersion = null,
        private ?CacheInterface $cache = null,
    ) {
    }

    public function create(): AgentEngineClient
    {
        return AgentEngineClient::forServiceAccount(
            $this->baseUrl,
            $this->projectId,
            $this->credentials,
            $this->transport,
            $this->contractVersion,
            $this->cache,
        );
    }
}
