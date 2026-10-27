<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Trace\SessionTraces;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\WorkspaceId;

final readonly class Traces
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function forSession(WorkspaceId $workspace, SessionId $session): SessionTraces
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: sprintf('/api/v1/projects/%s/traces', rawurlencode($this->projectId->value)),
            query: [
                'session_id' => $session->value,
                'workspace_id' => $workspace->value,
            ],
        ));

        return SessionTraces::fromJson($response->body);
    }
}
