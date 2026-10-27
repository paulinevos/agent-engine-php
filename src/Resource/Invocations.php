<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Invocation\InvocationOptions;
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\WorkspaceId;

final readonly class Invocations
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function invoke(
        WorkspaceId $workspace,
        InvokeRequest $request,
        InvocationOptions $options = new InvocationOptions(),
    ): InvokeResult {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->basePath($workspace) . '/invoke',
            body: $request->toJson(),
            headers: $options->headers(),
        ));

        return InvokeResult::fromJson($response->body, $this->sessionId($response->header('X-Session-ID')));
    }

    public function stream(
        WorkspaceId $workspace,
        InvokeRequest $request,
        InvocationOptions $options = new InvocationOptions(),
    ): InvocationStream {
        $response = $this->sender->stream(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->basePath($workspace) . '/invokeStream',
            body: $request->toJson(),
            headers: $options->headers(),
        ));

        return new InvocationStream(
            $response->frames,
            $this->sessionId($response->header('X-Session-ID')),
            $options->customOutput,
        );
    }

    private function basePath(WorkspaceId $workspace): string
    {
        return sprintf(
            '/api/v1/projects/%s/workspaces/%s',
            rawurlencode($this->projectId->value),
            rawurlencode($workspace->value),
        );
    }

    private function sessionId(?string $value): ?SessionId
    {
        return $value === null || $value === '' ? null : SessionId::fromString($value);
    }
}
