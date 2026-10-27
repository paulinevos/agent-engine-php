# Agent Engine PHP

Framework-agnostic PHP client and CLI for the [MongoDB Atlas Agent Engine](https://www.mongodb.com/docs/agentengine/) API.

Agent Engine is in [public preview](https://www.mongodb.com/docs/preview-features/) and its API can change without a migration path. This client pins the published `2026-09-20-preview` [contract](https://www.mongodb.com/docs/agentengine/reference/limitations/#std-label-agent-engine-api-contract-pinning) by default.

## Install

```bash
composer require vos/agent-engine
```

Requires [PHP 8.2](https://www.php.net/releases/8.2/en.php) or later and a [service account](https://www.mongodb.com/docs/agentengine/api-keys-service-accounts/) with `PROJECT_OWNER`.

## Quick start

This calls the [invocation API](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/):

```bash
export AGENT_ENGINE_CLIENT_ID='ae_sa_id_...'
export AGENT_ENGINE_CLIENT_SECRET='ae_sa_sk_...'
export AGENT_ENGINE_PROJECT_ID='...'
export AGENT_ENGINE_WORKSPACE_ID='...'
```

```php
use AgentEngine\AgentEngineClient;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;

$environment = static fn (string $name): string => (string) getenv($name);

$client = AgentEngineClient::forServiceAccount(
    BaseUrl::fromString('https://agentengine.mongodb.com'),
    ProjectId::fromString($environment('AGENT_ENGINE_PROJECT_ID')),
    ClientCredentials::fromStrings(
        $environment('AGENT_ENGINE_CLIENT_ID'),
        $environment('AGENT_ENGINE_CLIENT_SECRET'),
    ),
);

$workspace = WorkspaceId::fromString($environment('AGENT_ENGINE_WORKSPACE_ID'));

$result = $client->invocations->invoke(
    $workspace,
    InvokeRequest::message('Hello, agent!'),
);

echo $result->text();           // the agent's final output
echo $result->executionId->value;
echo $result->sessionId?->value;
```

### Continue a conversation

```php
use AgentEngine\Invocation\InvocationOptions;

if ($result->sessionId !== null) {
    $next = $client->invocations->invoke(
        $workspace,
        InvokeRequest::message('And then?'),
        InvocationOptions::none()->withSession($result->sessionId),
    );
}
```

The session travels in `X-Session-ID`, as described in the [invocation docs](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/). The gateway ignores `session_id` in the body.

### Stream output

The platform sends [Server-Sent Events](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/#std-label-agent-engine-invoke-agent-stream).

```php
$stream = $client->invocations->stream($workspace, InvokeRequest::message('Draft a reply'));

foreach ($stream as $event) {
    if ($event->isText()) {
        echo $event->textDelta();
    }
}
```

For text only, with an exception on an error chunk:

```php
echo $client->invocations->stream($workspace, InvokeRequest::message('Draft a reply'))->collectText();
```

A stream is single-pass. Iterate it once, or call one of the text helpers once.

`StreamEvent::$type` is one of `text`, `step`, `subagent_start`, `subagent_end`, `done`, `error`, or `custom`. [Custom-output agents](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/) (with `features.use_custom_parser`) emit only `custom` events.

### Human in the loop

See [Human-in-the-Loop Agent Execution](https://www.mongodb.com/docs/agentengine/deploy/human-in-the-loop/) for the suspend, review, and resume lifecycle.

```php
use AgentEngine\Execution\ResumeDecision;

if ($result->requiresReview()) {
    $result->suspendReason;                              // why the agent paused
    $result->suspendContext?->allowedDecisions();        // e.g. ['approve', 'reject']
    $result->suspendContext?->toArray();                 // agent-defined fields

    $client->executions->resume(
        $workspace,
        $result->executionId,
        ResumeDecision::approve('Verified the customer'),
        // ->withSuspendGeneration(1) to pin the review snapshot
    );
}
```

### Poll, wait, cancel, interrupt

```php
$execution = $client->executions->get($workspace, $result->executionId);
$execution = $client->executions->wait($workspace, $result->executionId);

$client->executions->cancel($workspace, $result->executionId);
$client->executions->interrupt($workspace, $result->executionId, stepNumber: 12);
```

`wait()` returns on a terminal status or when the execution suspends for review, and throws `ExecutionTimedOut` after `WaitOptions::$timeoutSeconds` (600 by default).

### Other resources

| Resource | Methods | Docs |
| --- | --- | --- |
| `$client->invocations` | `invoke`, `stream` | [Invoke an Agent](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/) |
| `$client->executions` | `list`, `get`, `wait`, `cancel`, `interrupt`, `resume` | [Invocations and executions](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/) |
| `$client->sessions` | `list`, `messages`, `runs`, `runtimeSessions`, `stopRuntimeSession` | [Inspect traces](https://www.mongodb.com/docs/agentengine/manage/inspect-traces/) |
| `$client->traces` | `forSession` | [Inspect traces](https://www.mongodb.com/docs/agentengine/manage/inspect-traces/) |
| `$client->memory` | `recordTurn`, `buildContext`, `search` | [Agent memory](https://www.mongodb.com/docs/agentengine/add-features/memory-types/) |
| `$client->workspaces` | `list`, `get` | [Projects and workspaces](https://www.mongodb.com/docs/agentengine/manage/project-org-workspace/) |
| `$client->builds` | `archiveInit`, `start`, `get` | [Build the agent image](https://www.mongodb.com/docs/agentengine/deploy/agent-image/) |
| `$client->deployments` | `create`, `current` | [Deploy your build](https://www.mongodb.com/docs/agentengine/deploy/deploy-build/) |
| `$client->deployer` | `deploy` | [CI/CD pipeline](https://www.mongodb.com/docs/agentengine/deploy/ci-cd/) |
| `$client->health` | `status` | [API reference](https://www.mongodb.com/docs/api/doc/agentengine) |

## CLI

The package installs `vendor/bin/agent-engine` with `deploy` and `invoke`.

For Symfony applications, the [Symfony AI bundle](packages/symfony-ai/README.md) provides `agent-engine:deploy` and `agent-engine:invoke` with configuration and autowiring.

```bash
export AGENT_ENGINE_PROJECT_ID='...'
export AGENT_ENGINE_WORKSPACE_ID='...'
export AGENT_ENGINE_CLIENT_ID='...'
export AGENT_ENGINE_CLIENT_SECRET='...'

vendor/bin/agent-engine deploy ./agent --label=release-42
vendor/bin/agent-engine invoke "Hello, agent!" --stream
```

`deploy` archives the directory, uploads it to the presigned URL, starts the build, waits for it, creates the deployment, and waits for health. It excludes `.git`, `.venv`, `.env`, `node_modules`, `vendor`, and similar by default; add more with `--exclude`.

`invoke` sends a message and prints the response; `--stream` prints text deltas as they arrive. Both accept `--json` for CI.

See [docs/cli.md](docs/cli.md) for all options and exit codes. The deploy flow targets archive-source workspaces; a GitHub-connected workspace rejects `archive-init` with `409 UNSUPPORTED_SOURCE_TYPE`.

### Custom headers

The gateway [forwards custom headers](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/#std-label-agent-engine-forward-custom-headers) prefixed with `X-Mdb-Agent-Engine-Custom-` to the agent. The client adds the prefix and enforces the platform's limits (50 headers, 8 KiB each).

```php
use AgentEngine\Value\CustomHeaders;

$options = InvocationOptions::none()
    ->withCustomHeader('Tenant-Id', 'acme-corp')
    ->withCustomHeader('Authorization', 'user-123')
    ->withTimeout(60);
```

Custom headers are not persisted: send them again on every resume request.

## Client construction

| Constructor | Use |
| --- | --- |
| `forServiceAccount(...)` | Client-credentials flow with an in-memory token cache |
| `withAccessToken(...)` | A token you already obtained |
| `withTokenProvider(...)` | Your own `TokenProvider` |

All three accept an optional `Transport`, [`ContractVersion`](https://www.mongodb.com/docs/agentengine/reference/limitations/#std-label-agent-engine-api-contract-pinning), and `UserAgent`. `forServiceAccount` also accepts a [PSR-16](https://www.php-fig.org/psr/psr-16/) cache so token refreshes are shared across workers.

```php
$client = AgentEngineClient::forServiceAccount(
    baseUrl: $baseUrl,
    projectId: $projectId,
    credentials: $credentials,
    cache: $psr16Cache,
);
```

## Transports

| Transport | Streaming | Notes |
| --- | --- | --- |
| `StreamTransport` | Yes, line by line | Default; uses [PHP's HTTP stream wrapper](https://www.php.net/manual/en/wrappers.http.php) |
| `Psr18Transport` | If your client streams | Wraps any [PSR-18](https://www.php-fig.org/psr/psr-18/) client |
| Your own | Your choice | Implement `AgentEngine\Http\Transport` |

`StreamTransport` has a 300-second HTTP timeout; pass a different value as the first constructor argument.

## Errors

Every exception extends `AgentEngine\Exception\AgentEngineException`. Error semantics follow the [API reference](https://www.mongodb.com/docs/api/doc/agentengine).

| Exception | Cause |
| --- | --- |
| `AuthenticationRejected` | `401` |
| `PermissionDenied` | `403` |
| `ResourceNotFound` | `404` |
| `ContractNotAcceptable` | Unsupported `Accept` contract or streaming media type |
| `RequestRejected` | `400`, `422`, `425` |
| `Conflict` / `SessionBusy` | `409`; `SessionBusy` exposes `blockingExecutionId()` |
| `RateLimited` | `429`; exposes `retryAfterSeconds()` |
| `ServerFailure` | `5xx` |
| `OAuthFailure` | Token endpoint rejected the credentials |
| `TransportFailure` | Connection or read failure |
| `StreamFailed` | The stream delivered an `error` chunk while collecting text |
| `DeployFailed` | Build or deployment failed, or the wait timed out |
| `ArchiveFailed` | Missing `agent.yaml`, unreadable file, or an overlong path |
| `UploadFailed` | The presigned archive upload failed |
| `InvalidArgument` | Invalid base URL, identifier, header, or option |

`ApiFailure::$details` carries the status, message, error code, reason, body, and `Retry-After`.

## Framework integration

The SDK core uses PSR-18/17 and has no framework coupling; the bundled CLI uses Symfony Console. See [docs/integrations.md](docs/integrations.md) for the adapter seams in [Symfony AI](https://symfony.com/doc/current/ai/index.html), [Laravel AI](https://laravel.com/docs/12.x/ai), and [Prism](https://prismphp.com/). A ready-made Symfony AI bundle lives in [`packages/symfony-ai`](packages/symfony-ai), including `agent-engine:deploy` and `agent-engine:invoke`; see its [README](packages/symfony-ai/README.md).

## Scope

This covers the runtime surface plus the archive-source deploy flow: invocation, streaming, executions, human-in-the-loop, sessions, traces, memory, workspaces, builds, deployments, the deployer, and health. Secrets, service accounts, organization management, promotions, rollbacks, and GitHub-connected builds are not wrapped yet; their shapes are in the [API reference](https://www.mongodb.com/docs/api/doc/agentengine) and the [OpenAPI document](https://www.mongodb.com/docs/api/doc/agentengine.json). The authenticated sender is exposed for those:

```php
use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\HttpMethod;

$builds = $client->sender->send(new ApiRequest(
    method: HttpMethod::Get,
    path: '/api/v1/projects/' . rawurlencode($projectId->value) . '/workspaces/' . rawurlencode($workspace->value) . '/builds',
    query: ['limit' => 100],
));
```

## Development

```bash
composer install
composer test     # PHPUnit: unit + integration tests
composer stan     # PHPStan level 8
```

The integration suite starts a local mock server on a free port and exercises the client over real HTTP, including SSE and both auth flows. See `tests/Support/mock-server.php`.
