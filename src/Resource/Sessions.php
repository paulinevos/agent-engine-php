<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Session\RuntimeSessionStop;
use AgentEngine\Session\RuntimeSessions;
use AgentEngine\Session\SessionMessages;
use AgentEngine\Session\SessionPage;
use AgentEngine\Session\SessionQuery;
use AgentEngine\Session\SessionRuns;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\WorkspaceId;

final readonly class Sessions
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function list(SessionQuery $query = new SessionQuery()): SessionPage
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath() . '/sessions',
            query: $query->query(),
        ));

        return SessionPage::fromJson($response->body);
    }

    public function messages(WorkspaceId $workspace, SessionId $session): SessionMessages
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->sessionPath($session) . '/messages',
            query: ['workspace_id' => $workspace->value],
        ));

        return SessionMessages::fromJson($response->body);
    }

    public function runs(WorkspaceId $workspace, SessionId $session): SessionRuns
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->sessionPath($session) . '/runs',
            query: ['workspace_id' => $workspace->value],
        ));

        return SessionRuns::fromJson($response->body);
    }

    public function runtimeSessions(WorkspaceId $workspace): RuntimeSessions
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->workspacePath($workspace) . '/runtime-sessions',
        ));

        return RuntimeSessions::fromJson($response->body);
    }

    public function stopRuntimeSession(WorkspaceId $workspace, SessionId $session): RuntimeSessionStop
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->workspacePath($workspace) . '/runtime-sessions/' . rawurlencode($session->value) . '/stop',
        ));

        return RuntimeSessionStop::fromJson($response->body);
    }

    private function basePath(): string
    {
        return sprintf('/api/v1/projects/%s', rawurlencode($this->projectId->value));
    }

    private function workspacePath(WorkspaceId $workspace): string
    {
        return $this->basePath() . '/workspaces/' . rawurlencode($workspace->value);
    }

    private function sessionPath(SessionId $session): string
    {
        return $this->basePath() . '/sessions/' . rawurlencode($session->value);
    }
}
