<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Client;

use AgentEngine\AgentEngineClient;
use AgentEngine\Auth\TokenProvider;
use AgentEngine\Http\Transport;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Value\ProjectId;

final readonly class TokenProviderClientFactory implements ClientFactory
{
    public function __construct(
        private BaseUrl $baseUrl,
        private ProjectId $projectId,
        private TokenProvider $tokenProvider,
        private ?Transport $transport = null,
        private ?ContractVersion $contractVersion = null,
    ) {
    }

    public function create(): AgentEngineClient
    {
        return AgentEngineClient::withTokenProvider(
            $this->baseUrl,
            $this->projectId,
            $this->tokenProvider,
            $this->transport,
            $this->contractVersion,
        );
    }
}
