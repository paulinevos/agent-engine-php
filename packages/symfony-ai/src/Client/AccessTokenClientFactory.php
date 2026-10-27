<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Client;

use AgentEngine\AgentEngineClient;
use AgentEngine\Http\Transport;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;

final readonly class AccessTokenClientFactory implements ClientFactory
{
    public function __construct(
        private BaseUrl $baseUrl,
        private ProjectId $projectId,
        private AccessToken $accessToken,
        private ?Transport $transport = null,
        private ?ContractVersion $contractVersion = null,
    ) {
    }

    public function create(): AgentEngineClient
    {
        return AgentEngineClient::withAccessToken(
            $this->baseUrl,
            $this->projectId,
            $this->accessToken,
            $this->transport,
            $this->contractVersion,
        );
    }
}
