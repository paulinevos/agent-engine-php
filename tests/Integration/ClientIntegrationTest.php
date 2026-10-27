<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Integration;

use AgentEngine\AgentEngineClient;
use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\OAuthFailure;
use AgentEngine\Exception\SessionBusy;
use AgentEngine\Exception\StreamFailed;
use AgentEngine\Execution\ExecutionStatus;
use AgentEngine\Execution\InterruptOutcome;
use AgentEngine\Execution\ResumeDecision;
use AgentEngine\Invocation\InvocationOptions;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Invocation\StreamChunkType;
use AgentEngine\Memory\MemoryContextQuery;
use AgentEngine\Memory\MemorySearchQuery;
use AgentEngine\Memory\RecordTurn;
use AgentEngine\Session\MessageRole;
use AgentEngine\Session\SessionQuery;
use AgentEngine\Tests\Support\MockRequest;
use AgentEngine\Tests\Support\MockServer;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\CustomHeaders;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;
use AgentEngine\Value\WorkspaceId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientIntegrationTest extends TestCase
{
    private static MockServer $server;

    public static function setUpBeforeClass(): void
    {
        self::$server = MockServer::start();
    }

    public static function tearDownAfterClass(): void
    {
        self::$server->stop();
    }

    protected function setUp(): void
    {
        self::$server->clearRequests();
    }

    #[Test]
    public function it_invokes_an_agent_with_a_session_and_custom_headers(): void
    {
        $result = $this->client()->invocations->invoke(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('hi'),
            new InvocationOptions(
                customHeaders: CustomHeaders::fromArray(['Tenant-Id' => 'acme']),
                sessionId: SessionId::fromString('my-session'),
                timeoutSeconds: 30,
            ),
        );

        self::assertTrue($result->success);
        self::assertSame('Hello from the mock agent: hi', $result->response);
        self::assertSame('exec-completed', $result->executionId->value);
        self::assertSame(ExecutionStatus::Completed, $result->status);
        self::assertSame('my-session', $result->sessionId?->value);

        $request = $this->lastRequest();
        self::assertSame('Bearer test-access-token', $request->headers['authorization']);
        self::assertStringStartsWith('application/vnd.agent-engine-', $request->headers['accept']);
        self::assertSame('my-session', $request->headers['x-session-id']);
        self::assertSame('acme', $request->headers['x-mdb-agent-engine-custom-tenant-id']);
        self::assertSame('30', $request->headers['x-agent-engine-invoke-timeout-seconds']);
        self::assertSame('hi', $request->json()['message']);
    }

    #[Test]
    public function it_streams_text_steps_and_completion(): void
    {
        $stream = $this->client()->invocations->stream(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('hi'),
        );

        $events = iterator_to_array($stream);

        self::assertCount(4, $events);
        self::assertSame(StreamChunkType::Text, $events[0]->type);
        self::assertSame('Hello', $events[0]->textDelta());
        self::assertSame(StreamChunkType::Step, $events[1]->type);
        self::assertSame('search', $events[1]->content['name']);
        self::assertSame(' world', $events[2]->textDelta());
        self::assertSame(StreamChunkType::Done, $events[3]->type);
        self::assertSame('session-generated', $stream->sessionId?->value);

        $request = $this->lastRequest();
        self::assertStringStartsWith('text/event-stream', $request->headers['accept']);
    }

    #[Test]
    public function it_collects_text_and_treats_an_error_chunk_as_a_failure(): void
    {
        $client = $this->client();

        $text = $client->invocations->stream(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('hi'),
        )->collectText();

        self::assertSame('Hello world', $text);

        $this->expectException(StreamFailed::class);
        $this->expectExceptionMessage('The agent crashed.');

        $client->invocations->stream(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('stream-error'),
        )->collectText();
    }

    #[Test]
    public function it_streams_custom_events_from_an_output_parser_agent(): void
    {
        $events = iterator_to_array($this->client()->invocations->stream(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('custom'),
        ));

        self::assertCount(2, $events);
        self::assertSame(StreamChunkType::Custom, $events[0]->type);
        self::assertSame(['kind' => 'progress', 'percent' => 50], $events[0]->content);
        self::assertSame(['kind' => 'completed'], $events[1]->content);
    }

    #[Test]
    public function it_can_force_custom_output_parsing(): void
    {
        $events = iterator_to_array($this->client()->invocations->stream(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('custom-collision'),
            InvocationOptions::none()->asCustomOutput(),
        ));

        self::assertCount(1, $events);
        self::assertSame(StreamChunkType::Custom, $events[0]->type);
        self::assertSame(['chunk_type' => 'progress', 'percent' => 50], $events[0]->content);
    }

    #[Test]
    public function it_returns_a_suspended_invocation_for_human_review(): void
    {
        $result = $this->client()->invocations->invoke(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('suspend'),
        );

        self::assertTrue($result->requiresReview());
        self::assertSame(ExecutionStatus::Suspended, $result->status);
        self::assertSame('approval_required', $result->suspendReason);
        self::assertSame(['approve', 'reject'], $result->suspendContext?->allowedDecisions());
    }

    #[Test]
    public function it_resumes_a_suspended_execution_with_a_flat_body(): void
    {
        $result = $this->client()->executions->resume(
            WorkspaceId::fromString('workspace-1'),
            ExecutionId::fromString('exec-suspended'),
            ResumeDecision::approve('looks good'),
            CustomHeaders::fromArray(['Tenant-Id' => 'acme']),
        );

        self::assertTrue($result->success);
        self::assertSame('running', $result->status);

        $request = $this->lastRequest();
        self::assertSame('workspace_id=workspace-1', $request->query);
        self::assertSame(
            ['decision' => 'approve', 'reviewer_notes' => 'looks good'],
            $request->json(),
        );
    }

    #[Test]
    public function it_gets_cancels_and_interrupts_executions(): void
    {
        $client = $this->client();
        $workspace = WorkspaceId::fromString('workspace-1');

        $running = $client->executions->get($workspace, ExecutionId::fromString('exec-running'));
        self::assertSame(ExecutionStatus::Running, $running->status);
        self::assertSame('session-abc', $running->sessionId?->value);

        $cancelled = $client->executions->cancel($workspace, ExecutionId::fromString('exec-1'));
        self::assertTrue($cancelled->cancelled);
        self::assertSame('cancelled', $cancelled->status);

        $interrupted = $client->executions->interrupt($workspace, ExecutionId::fromString('exec-1'), 12);
        self::assertTrue($interrupted->interrupted);
        self::assertSame([12], $interrupted->interruptedSteps);
        self::assertSame(InterruptOutcome::Aborted, $interrupted->outcome);
        self::assertSame(['step_number' => 12], $this->lastRequest()->json());
    }

    #[Test]
    public function it_waits_until_an_execution_is_terminal(): void
    {
        $execution = $this->client()->executions->wait(
            WorkspaceId::fromString('workspace-1'),
            ExecutionId::fromString('exec-completed'),
        );

        self::assertTrue($execution->isTerminal());
    }

    #[Test]
    public function it_lists_sessions_with_a_cursor(): void
    {
        $page = $this->client()->sessions->list(new SessionQuery(limit: 1));

        self::assertCount(1, $page->sessions);
        self::assertSame('session-abc', $page->sessions[0]->sessionId->value);
        self::assertTrue($page->hasMore);
        self::assertSame('cursor-2', $page->nextPageCursor());
        self::assertSame('limit=1', $this->lastRequest()->query);
    }

    #[Test]
    public function it_reads_session_messages_and_runs(): void
    {
        $client = $this->client();
        $workspace = WorkspaceId::fromString('workspace-1');
        $session = SessionId::fromString('session-abc');

        $messages = $client->sessions->messages($workspace, $session);
        self::assertCount(2, $messages->messages);
        self::assertSame(MessageRole::User, $messages->messages[0]->role);
        self::assertSame('Hi there', $messages->messages[1]->content);

        $runs = $client->sessions->runs($workspace, $session);
        self::assertSame(1, $runs->count);
        self::assertSame('exec-completed', $runs->runs[0]->executionId?->value);
        self::assertSame('llm', $runs->runs[0]->steps[0]->kind);
        self::assertSame(1200.5, $runs->runs[0]->slowestStep?->durationMilliseconds);
    }

    #[Test]
    public function it_reads_traces_runtime_sessions_and_health(): void
    {
        $client = $this->client();
        $workspace = WorkspaceId::fromString('workspace-1');

        $traces = $client->traces->forSession($workspace, SessionId::fromString('session-abc'));
        self::assertSame(1, $traces->count);
        self::assertSame('trace-1', $traces->traces[0]->traceId);

        $runtime = $client->sessions->runtimeSessions($workspace);
        self::assertSame('active', $runtime->sessions[0]->status);

        $stopped = $client->sessions->stopRuntimeSession($workspace, SessionId::fromString('session-abc'));
        self::assertSame('stopped', $stopped->status);

        self::assertSame('ok', $client->health->status());
    }

    #[Test]
    public function it_lists_and_reads_workspaces(): void
    {
        $client = $this->client();

        $list = $client->workspaces->list();
        self::assertSame(1, $list->total);
        self::assertSame('support-agent', $list->workspaces[0]->name);

        $workspace = $client->workspaces->get(WorkspaceId::fromString('workspace-1'));
        self::assertSame('workspace-1', $workspace->workspaceId->value);
        self::assertSame('langgraph', $workspace->framework);
        self::assertSame(850.5, $workspace->p95LatencyMilliseconds);
        self::assertFalse($workspace->usesCustomParser());
    }

    #[Test]
    public function it_records_turns_and_builds_memory_context(): void
    {
        $client = $this->client();

        $client->memory->recordTurn(RecordTurn::user(
            SessionId::fromString('session-abc'),
            UserId::fromString('user-1'),
            'My home airport is Boston Logan.',
        ));

        $recorded = $this->lastRequest()->json();
        self::assertSame('user', $recorded['role']);
        self::assertSame('My home airport is Boston Logan.', $recorded['content']);

        $context = $client->memory->buildContext(
            MemoryContextQuery::forQuery('What is my home airport?', UserId::fromString('user-1')),
        );

        self::assertSame('The user lives in Boston.', $context->string('formatted_context'));

        $results = $client->memory->search(MemorySearchQuery::of('Boston', 'semantic'), 'idempotency-1');
        self::assertSame('Boston Logan', $results->array('results')[0]['content']);
        self::assertSame('idempotency-1', $this->lastRequest()->headers['idempotency-key']);
    }

    #[Test]
    public function it_surfaces_a_busy_session_conflict(): void
    {
        $this->expectException(SessionBusy::class);

        $this->client()->invocations->invoke(
            WorkspaceId::fromString('workspace-1'),
            InvokeRequest::message('conflict'),
        );
    }

    #[Test]
    public function it_surfaces_rejected_credentials(): void
    {
        $client = AgentEngineClient::forServiceAccount(
            BaseUrl::fromString(self::$server->baseUrl),
            ProjectId::fromString('project-1'),
            ClientCredentials::fromStrings('client-id', 'wrong-secret'),
        );

        $this->expectException(OAuthFailure::class);

        $client->executions->list();
    }

    #[Test]
    public function it_surfaces_an_unauthorized_access_token(): void
    {
        $client = AgentEngineClient::withAccessToken(
            BaseUrl::fromString(self::$server->baseUrl),
            ProjectId::fromString('project-1'),
            AccessToken::fromParts('wrong-token', new \DateTimeImmutable('+1 hour')),
        );

        $this->expectException(AuthenticationRejected::class);

        $client->executions->list();
    }

    private function client(): AgentEngineClient
    {
        return AgentEngineClient::forServiceAccount(
            BaseUrl::fromString(self::$server->baseUrl),
            ProjectId::fromString('project-1'),
            ClientCredentials::fromStrings('client-id', 'client-secret'),
        );
    }

    private function lastRequest(): MockRequest
    {
        $request = self::$server->lastRequest();

        self::assertNotNull($request, 'No request was recorded.');

        return $request;
    }
}
