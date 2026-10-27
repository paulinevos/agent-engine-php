<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\AgentEngineClient;
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Json\JsonObject;
use AgentEngine\SymfonyAi\Platform\AgentEngineModelClient;
use AgentEngine\SymfonyAi\Platform\ExceptionTranslator;
use AgentEngine\SymfonyAi\Platform\InvocationOptionsFactory;
use AgentEngine\SymfonyAi\Platform\WorkspaceMap;
use AgentEngine\SymfonyAi\Tests\Support\RecordingTransport;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ProjectId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;

final class AgentEngineModelClientTest extends TestCase
{
    #[Test]
    public function it_invokes_the_configured_workspace_with_the_last_user_message(): void
    {
        $transport = new RecordingTransport(
            body: json_encode([
                'success' => true,
                'execution_id' => 'execution-1',
                'status' => 'completed',
                'response' => 'Hello',
            ], JSON_THROW_ON_ERROR),
            headers: ['x-session-id' => ['session-1']],
        );

        $result = $this->client($transport)->request(
            new Model('support-agent'),
            [
                'messages' => [
                    ['role' => 'user', 'content' => 'First'],
                    ['role' => 'assistant', 'content' => 'Reply'],
                    ['role' => 'user', 'content' => 'Second'],
                ],
                'model' => 'support-agent',
            ],
            [
                InvocationOptionsFactory::SESSION_ID => 'session-1',
                InvocationOptionsFactory::USER_ID => 'user-1',
                InvocationOptionsFactory::CUSTOM_HEADERS => ['Tenant-Id' => 'acme'],
            ],
        );

        $raw = $result->getObject();
        self::assertInstanceOf(InvokeResult::class, $raw);
        self::assertSame('Hello', $raw->text());
        self::assertSame('session-1', $raw->sessionId?->value);

        $request = $transport->requests[0];

        self::assertSame(
            '/api/v1/projects/project-1/workspaces/workspace-1/invoke',
            $request->getUri()->getPath(),
        );
        self::assertSame('session-1', $request->getHeaderLine('X-Session-ID'));
        self::assertSame('acme', $request->getHeaderLine('X-Mdb-Agent-Engine-Custom-Tenant-Id'));
        self::assertSame(
            ['message' => 'Second', 'user_id' => 'user-1'],
            JsonObject::fromJson((string) $request->getBody())->toArray(),
        );
    }

    #[Test]
    public function it_streams_from_the_configured_workspace(): void
    {
        $transport = new RecordingTransport(lines: [
            'data: {"chunk_type":"text","content":"Hel"}',
            '',
            'data: {"chunk_type":"text","content":"lo"}',
            '',
            'data: {"chunk_type":"done"}',
            '',
        ]);

        $result = $this->client($transport)->request(
            new Model('support-agent'),
            ['messages' => [['role' => 'user', 'content' => 'Hello']]],
            ['stream' => true],
        );

        $stream = $result->getObject();
        self::assertInstanceOf(InvocationStream::class, $stream);
        self::assertSame('Hello', $stream->collectText());

        $request = $transport->requests[0];

        self::assertSame(
            '/api/v1/projects/project-1/workspaces/workspace-1/invokeStream',
            $request->getUri()->getPath(),
        );
        self::assertStringContainsString('text/event-stream', $request->getHeaderLine('Accept'));
    }

    private function client(RecordingTransport $transport): AgentEngineModelClient
    {
        $client = AgentEngineClient::withAccessToken(
            BaseUrl::fromString('https://agent-engine.test'),
            ProjectId::fromString('project-1'),
            AccessToken::fromParts('token', new \DateTimeImmutable('+1 hour')),
            $transport,
        );

        return new AgentEngineModelClient(
            $client,
            WorkspaceMap::fromStrings(['support-agent' => 'workspace-1']),
            new ExceptionTranslator(),
            new InvocationOptionsFactory(),
        );
    }
}
