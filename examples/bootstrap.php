<?php

declare(strict_types=1);

use AgentEngine\AgentEngineClient;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;

require dirname(__DIR__) . '/vendor/autoload.php';

function example_client(): AgentEngineClient
{
    return AgentEngineClient::forServiceAccount(
        BaseUrl::fromString(example_environment('AGENT_ENGINE_BASE_URL', 'https://agentengine.mongodb.com')),
        ProjectId::fromString(example_environment('AGENT_ENGINE_PROJECT_ID')),
        ClientCredentials::fromStrings(
            example_environment('AGENT_ENGINE_CLIENT_ID'),
            example_environment('AGENT_ENGINE_CLIENT_SECRET'),
        ),
    );
}

function example_workspace(): WorkspaceId
{
    return WorkspaceId::fromString(example_environment('AGENT_ENGINE_WORKSPACE_ID'));
}

function example_environment(string $name, ?string $default = null): string
{
    $value = getenv($name);

    if ($value !== false && $value !== '') {
        return $value;
    }

    if ($default !== null) {
        return $default;
    }

    throw new RuntimeException('Set ' . $name . '.');
}
