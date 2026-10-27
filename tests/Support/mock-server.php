<?php

declare(strict_types=1);

/**
 * Router for PHP's built-in server. It emulates the Atlas Agent Engine
 * runtime surface closely enough to exercise the client end to end,
 * including SSE and the two authentication flows.
 */

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$body = file_get_contents('php://input') ?: '';
$headers = request_headers();

record_request($method, $path, $_SERVER['QUERY_STRING'] ?? '', headers: $headers, body: $body);

if ($path === '/health') {
    respond_json(200, ['status' => 'ok']);
}

if ($path === '/api/v1/oauth/token') {
    respond_token($method, $headers);
}

if (!str_starts_with($path, '/api/v1/')) {
    respond_json(404, ['error' => 'not_found']);
}

if (($headers['authorization'] ?? '') !== 'Bearer test-access-token') {
    respond_json(401, ['error' => 'unauthorized']);
}

$payload = json_decode($body === '' ? '{}' : $body, true);
$payload = is_array($payload) ? $payload : [];

handle_invoke($method, $path, $payload, $headers);
handle_execution($method, $path, $payload);
handle_sessions($method, $path);
handle_traces($path);
handle_memory($method, $path);
handle_workspaces($method, $path);

respond_json(404, ['error' => 'not_found', 'path' => $path]);

function handle_invoke(string $method, string $path, array $payload, array $headers): void
{
    if ($method !== 'POST') {
        return;
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces/[^/]+/invokeStream$#', $path) === 1) {
        respond_stream($payload, $headers);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces/[^/]+/invoke$#', $path) !== 1) {
        return;
    }

    $message = (string) ($payload['message'] ?? '');
    $sessionId = $headers['x-session-id'] ?? 'session-generated';

    if ($message === 'suspend') {
        respond_json(200, [
            'success' => true,
            'response' => null,
            'execution_id' => 'exec-suspended',
            'status' => 'suspended',
            'suspend_reason' => 'approval_required',
            'suspend_context' => [
                'allowed_decisions' => ['approve', 'reject'],
                'claim_id' => 'CLM-4821',
            ],
        ], ['X-Session-ID' => $sessionId]);
    }

    if ($message === 'conflict') {
        respond_json(409, [
            'success' => false,
            'code' => 'SESSION_CONFLICT',
            'error' => 'The session already has a running execution.',
            'blocking_execution_id' => 'exec-running',
            'blocking_status' => 'running',
        ]);
    }

    if ($message === 'fail') {
        respond_json(500, [
            'success' => false,
            'code' => 'AGENT_FAILED',
            'error' => 'The agent failed to start.',
        ]);
    }

    respond_json(200, [
        'success' => true,
        'response' => 'Hello from the mock agent: ' . $message,
        'execution_id' => 'exec-completed',
        'status' => 'completed',
        'echo_headers' => custom_headers($headers),
        'echo_payload' => $payload,
    ], ['X-Session-ID' => $sessionId]);
}

function handle_execution(string $method, string $path, array $payload): void
{
    if (preg_match('#^/api/v1/projects/[^/]+/executions/([^/]+)/(cancel|interrupt|resume)$#', $path, $matches) === 1) {
        respond_execution_action($method, $matches[1], $matches[2], $payload);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/executions/([^/]+)$#', $path, $matches) === 1) {
        respond_json(200, [
            'success' => true,
            'execution' => execution_body($matches[1]),
        ]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/executions$#', $path) === 1) {
        respond_json(200, [
            'success' => true,
            'count' => 1,
            'executions' => [execution_body('exec-completed')],
        ]);
    }
}

function respond_execution_action(string $method, string $executionId, string $action, array $payload): void
{
    if ($method !== 'POST') {
        respond_json(405, ['error' => 'method_not_allowed']);
    }

    if ($action === 'cancel') {
        $terminal = str_contains($executionId, 'terminal');
        respond_json(200, [
            'execution_id' => $executionId,
            'cancelled' => !$terminal,
            'status' => $terminal ? 'completed' : 'cancelled',
        ]);
    }

    if ($action === 'interrupt') {
        $step = $payload['step_number'] ?? 7;
        respond_json(200, [
            'execution_id' => $executionId,
            'interrupted' => true,
            'interrupted_steps' => [$step],
            'pending' => false,
            'outcome' => 'aborted',
        ]);
    }

    respond_json(200, [
        'execution_id' => $executionId,
        'status' => 'running',
        'success' => true,
    ]);
}

function execution_body(string $executionId): array
{
    $status = match (true) {
        str_contains($executionId, 'running') => 'running',
        str_contains($executionId, 'suspended') => 'suspended',
        str_contains($executionId, 'error') => 'error',
        default => 'completed',
    };

    return [
        'execution_id' => $executionId,
        'status' => $status,
        'session_id' => 'session-abc',
        'message' => 'Hello, agent!',
        'result' => $status === 'completed' ? ['answer' => 42] : null,
        'error' => $status === 'error' ? 'boom' : null,
        'error_code' => $status === 'error' ? 'BOOM' : null,
        'created_at' => '2026-10-27T10:00:00Z',
        'updated_at' => '2026-10-27T10:00:01Z',
    ];
}

function handle_sessions(string $method, string $path): void
{
    if (preg_match('#^/api/v1/projects/[^/]+/sessions/([^/]+)/(messages|runs)$#', $path, $matches) === 1) {
        respond_session_detail($matches[1], $matches[2]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/sessions$#', $path) === 1) {
        respond_json(200, [
            'sessions' => [[
                'session_id' => 'session-abc',
                'workspace_id' => 'workspace-1',
                'user_id' => 'user-1',
                'turns' => 2,
                'total_tokens' => 123,
                'latest_status' => 'completed',
                'first_message_preview' => 'Hello',
                'total_duration_ms' => 4200,
                'active_duration_ms' => 4000,
                'wait_ms' => 0,
                'created_at' => '2026-10-27T10:00:00Z',
                'last_activity' => '2026-10-27T10:00:05Z',
            ]],
            'next_cursor' => 'cursor-2',
            'has_more' => true,
            'total_count' => 2,
            'truncated' => false,
            'limit' => 1,
        ]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces/[^/]+/runtime-sessions$#', $path) === 1) {
        respond_json(200, [
            'sessions' => [[
                'session_id' => 'session-abc',
                'status' => 'active',
                'last_activity_at' => '2026-10-27T10:00:05Z',
                'scheduled_release_at' => '2026-10-27T11:00:00Z',
                'is_test_session' => false,
            ]],
        ]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces/[^/]+/runtime-sessions/([^/]+)/stop$#', $path, $matches) === 1) {
        respond_json(200, ['session_id' => $matches[1], 'status' => 'stopped']);
    }
}

function respond_session_detail(string $sessionId, string $detail): void
{
    if ($detail === 'messages') {
        respond_json(200, [
            'history_status' => 'ready',
            'latest_status' => 'completed',
            'messages' => [
                ['id' => 'msg-1', 'role' => 'user', 'content' => 'Hello', 'session_id' => $sessionId],
                ['id' => 'msg-2', 'role' => 'assistant', 'content' => 'Hi there', 'session_id' => $sessionId],
            ],
        ]);
    }

    respond_json(200, [
        'count' => 1,
        'truncated' => false,
        'runs' => [[
            'execution_id' => 'exec-completed',
            'session_id' => $sessionId,
            'workspace_id' => 'workspace-1',
            'status' => 'completed',
            'run_number' => 1,
            'summary' => 'Hello',
            'duration_ms' => 4200,
            'active_duration_ms' => 4000,
            'total_tokens' => 123,
            'steps' => [[
                'kind' => 'llm',
                'name' => 'generate',
                'status' => 'completed',
                'step_number' => 1,
                'duration_ms' => 1200.5,
                'total_tokens' => 123,
            ]],
            'slowest_step' => ['kind' => 'llm', 'name' => 'generate', 'step_number' => 1, 'duration_ms' => 1200.5],
        ]],
    ]);
}

function handle_traces(string $path): void
{
    if (preg_match('#^/api/v1/projects/[^/]+/traces$#', $path) !== 1) {
        return;
    }

    respond_json(200, [
        'success' => true,
        'count' => 1,
        'traces' => [[
            'trace_id' => 'trace-1',
            'span_id' => 'span-1',
            'name' => 'invoke',
            'kind' => 'server',
            'session_id' => 'session-abc',
            'start_time_ns' => 1000,
            'end_time_ns' => 2000,
            'duration_ns' => 1000,
            'status' => ['code' => 'OK'],
            'attributes' => ['model' => 'mock'],
            'resource' => [],
            'events' => [],
        ]],
    ]);
}

function handle_workspaces(string $method, string $path): void
{
    if ($method !== 'GET') {
        return;
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces/[^/]+$#', $path) === 1) {
        respond_json(200, workspace_body('workspace-1', 'support-agent'));
    }

    if (preg_match('#^/api/v1/projects/[^/]+/workspaces$#', $path) === 1) {
        respond_json(200, [
            'total' => 1,
            'workspaces' => [workspace_body('workspace-1', 'support-agent')],
        ]);
    }
}

function workspace_body(string $workspaceId, string $name): array
{
    return [
        'id' => $workspaceId,
        'name' => $name,
        'description' => 'Answers support questions',
        'framework' => 'langgraph',
        'language' => 'python',
        'status' => 'active',
        'release_mode' => 'api-only',
        'org_id' => 'org-1',
        'project_id' => 'project-1',
        'created_date' => '2026-10-01T09:00:00Z',
        'last_invoked_at' => '2026-10-27T10:00:00Z',
        'current_deployment_id' => 'deploy-abc123',
        'agent_http_endpoint' => 'https://agent.example.test',
        'auto_deploy' => false,
        'invocations' => 12,
        'p95_latency_ms' => 850.5,
        'completion_rate' => 0.98,
        'features' => ['guardrails' => true, 'memory' => true, 'use_custom_parser' => false],
        'agent_card' => ['summary' => 'Support agent'],
        'gitops' => ['provider' => 'github'],
    ];
}

function handle_memory(string $method, string $path): void
{
    if ($method !== 'POST') {
        return;
    }

    if (preg_match('#^/api/v1/projects/[^/]+/memory/turns$#', $path) === 1) {
        respond_json(201, ['success' => true]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/memory/context$#', $path) === 1) {
        respond_json(200, [
            'formatted_context' => 'The user lives in Boston.',
            'memories' => [['type' => 'semantic', 'content' => 'Home airport is Boston Logan.']],
        ]);
    }

    if (preg_match('#^/api/v1/projects/[^/]+/memory/search$#', $path) === 1) {
        respond_json(200, ['results' => [['type' => 'semantic', 'content' => 'Boston Logan']]]);
    }
}

function respond_token(string $method, array $headers): void
{
    if ($method !== 'POST') {
        respond_json(405, ['error' => 'method_not_allowed']);
    }

    $expected = 'Basic ' . base64_encode('client-id:client-secret');

    if (($headers['authorization'] ?? '') !== $expected) {
        respond_json(401, ['error' => 'invalid_client', 'error_description' => 'Bad credentials.']);
    }

    respond_json(200, [
        'access_token' => 'test-access-token',
        'token_type' => 'Bearer',
        'expires_in' => 3600,
    ]);
}

function respond_stream(array $payload, array $headers): void
{
    $message = (string) ($payload['message'] ?? '');
    $sessionId = $headers['x-session-id'] ?? 'session-generated';

    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Session-ID: ' . $sessionId);

    if ($message === 'custom') {
        stream_frame(['kind' => 'progress', 'percent' => 50]);
        stream_frame(['kind' => 'completed']);
        exit;
    }

    if ($message === 'custom-collision') {
        stream_frame(['chunk_type' => 'progress', 'percent' => 50]);
        exit;
    }

    stream_frame(['chunk_type' => 'text', 'content' => 'Hello', 'metadata' => [], 'execution_id' => 'exec-stream']);
    stream_frame(['chunk_type' => 'step', 'content' => ['name' => 'search'], 'metadata' => ['step' => 1], 'execution_id' => 'exec-stream']);
    stream_frame(['chunk_type' => 'text', 'content' => ' world', 'metadata' => [], 'execution_id' => 'exec-stream']);

    if ($message === 'stream-error') {
        stream_frame(['chunk_type' => 'error', 'content' => 'The agent crashed.', 'metadata' => [], 'execution_id' => 'exec-stream']);
        exit;
    }

    stream_frame(['chunk_type' => 'done', 'content' => null, 'metadata' => [], 'execution_id' => 'exec-stream']);
    exit;
}

function stream_frame(array $frame): void
{
    echo 'data: ' . json_encode($frame, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

function respond_json(int $status, array $body, array $extraHeaders = []): void
{
    http_response_code($status);
    header('Content-Type: application/json');

    foreach ($extraHeaders as $name => $value) {
        header($name . ': ' . $value);
    }

    echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function custom_headers(array $headers): array
{
    $custom = [];

    foreach ($headers as $name => $value) {
        if (str_starts_with($name, 'x-mdb-agent-engine-custom-')) {
            $custom[substr($name, strlen('x-mdb-agent-engine-custom-'))] = $value;
        }
    }

    return $custom;
}

function request_headers(): array
{
    $headers = [];

    foreach ($_SERVER as $key => $value) {
        if (!str_starts_with($key, 'HTTP_')) {
            continue;
        }

        $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }

    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
    }

    return $headers;
}

function record_request(string $method, string $path, string $query, array $headers, string $body): void
{
    $log = getenv('MOCK_REQUEST_LOG');

    if ($log === false || $log === '') {
        return;
    }

    file_put_contents($log, json_encode([
        'method' => $method,
        'path' => $path,
        'query' => $query,
        'headers' => $headers,
        'body' => $body,
    ]) . "\n", FILE_APPEND);
}
