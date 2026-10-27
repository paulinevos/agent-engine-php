# Agent Engine Symfony AI bundle

Wires the [Agent Engine PHP client](../..) into [Symfony AI](https://symfony.com/doc/current/ai/index.html): an `ai.platform` provider for Agent Engine workspaces and a `MemoryProviderInterface` implementation backed by Agent Engine memory.

Requires PHP 8.2+, `symfony/ai-*` v1.0.0-BETA1, and `vos/agent-engine`.

## Install

Until the package is published, add both packages as path repositories:

```json
"repositories": [
    { "type": "path", "url": "../agent-engine-php" },
    { "type": "path", "url": "../agent-engine-php/packages/symfony-ai" }
]
```

```bash
composer require vos/agent-engine-symfony-ai:@dev
```

Register the bundle **before** `AiBundle` so its `ai.platform` tag is visible when `AiBundle` builds the platform aliases:

```php
// config/bundles.php
return [
    AgentEngine\SymfonyAi\AgentEngineBundle::class => ['all' => true],
    Symfony\AI\AiBundle\AiBundle::class => ['all' => true],
];
```

## Configure

```yaml
agent_engine:
    project_id: '%env(AGENT_ENGINE_PROJECT_ID)%'
    workspace_id: '%env(AGENT_ENGINE_WORKSPACE_ID)%'
    auth:
        client_id: '%env(AGENT_ENGINE_CLIENT_ID)%'
        client_secret: '%env(AGENT_ENGINE_CLIENT_SECRET)%'
    platform:
        models:
            support-agent: '%env(AGENT_ENGINE_WORKSPACE_ID)%'
    memory:
        user_id: 'demo-user'
```

`workspace_id` is the default workspace for the console commands. When set, `AgentEngine\Value\WorkspaceId` is also autowirable. Pass `--workspace` to either command to override it.

`auth` accepts exactly one method:

| Method | Keys |
| --- | --- |
| Service account | `client_id`, `client_secret` |
| Existing access token | `access_token`, `access_token_expires_at` (ISO 8601) |
| Custom provider | `token_provider` (service ID) |

Other options:

| Key | Default | Purpose |
| --- | --- | --- |
| `base_url` | `https://agentengine.mongodb.com` | API endpoint |
| `contract_version` | SDK default | Pinned API contract |
| `transport` | `StreamTransport` | Service ID of an `AgentEngine\Http\Transport` |
| `psr18_client` | — | Service ID of a PSR-18 client, wrapped in `Psr18Transport` |
| `cache` | in-memory | PSR-16 cache for service-account tokens |
| `platform.name` | `agent_engine` | Platform tag name |
| `platform.models` | — | Model name → workspace ID |
| `memory.user_id` | — | Default user for memory lookups |
| `memory.session_id` | — | Default session scope |
| `memory.max_tokens` | — | Context budget |
| `memory.top_k` | — | Maximum memories (1–200) |
| `memory.enabled_sources` | `[]` | Memory source types |
| `memory.format_style` | — | Server-side formatting |

Omit `platform` to use memory and the client alone. Omit `memory` to skip the memory provider.

## Use with an agent

```yaml
ai:
    agent:
        support:
            platform: agent_engine.platform
            model: support-agent
            memory:
                service: agent_engine.memory_provider
```

`support-agent` resolves to the workspace in `platform.models`. The memory provider calls `memory->buildContext` with the last user message and lets Symfony AI's `MemoryInputProcessor` inject `formatted_context` into the system prompt.

## Deploy

```bash
php bin/console agent-engine:deploy ./agent --label=release-42
```

Archives the agent directory, uploads it, builds it, deploys it, and waits for every component to become healthy.

| Option | Description |
| --- | --- |
| `path` | Agent directory; defaults to `.` |
| `--workspace` | Workspace ID; overrides the configured default |
| `--label` | Build label |
| `--commit` | Git commit SHA for the image tag; detected from `git` when omitted |
| `--branch` | Git branch name; detected from `git` when omitted |
| `--dirty` | Mark the working tree as dirty |
| `--subdirectory` | Agent directory inside the build context |
| `--auto-deploy` | Let a successful build deploy itself |
| `--no-wait` | Return after the build starts |
| `--build-timeout` | Build timeout in seconds; default `1800` |
| `--deploy-timeout` | Deployment timeout in seconds; default `600` |
| `--poll-interval` | Poll interval in milliseconds; default `5000` |
| `--exclude` | Additional archive exclusion pattern; repeatable |
| `--json` | Print a JSON result to stdout |
| `--dry-run` | Build and validate the archive without uploading |

Default exclusions are `.git`, `.venv`, `__pycache__`, `.agentengine`, `.env`, `node_modules`, `vendor`, `var`, `.phpunit.cache`, `.idea`, and `.DS_Store`.

The deploy flow targets archive-source workspaces. A GitHub-connected workspace rejects `archive-init` with `409 UNSUPPORTED_SOURCE_TYPE`.

## Invoke

```bash
php bin/console agent-engine:invoke "Hello, agent!" --stream
```

Omit the message, or pass `-`, to read it from stdin.

| Option | Description |
| --- | --- |
| `message` | Message to send; omit or use `-` for stdin |
| `--workspace` | Workspace ID; overrides the configured default |
| `--session` | Session ID to continue |
| `--user-id` | User ID forwarded to the agent |
| `--payload` | JSON payload forwarded to the agent |
| `--header` | Custom header as `Name: value`; repeatable |
| `--stream` | Stream text as it arrives |
| `--invoke-timeout` | Platform invoke timeout in seconds; `1`–`120` |
| `--json` | Print JSON to stdout |

## Command exit codes

| Code | Meaning |
| --- | --- |
| `0` | Success |
| `1` | General failure |
| `2` | Invalid usage |
| `3` | Authentication or permission failure |
| `4` | Build failed or timed out |
| `5` | Deployment failed or timed out |

## Invocation options

Pass these in the `options` argument of `$platform->invoke(...)` or an agent call:

| Option | Type | Purpose |
| --- | --- | --- |
| `agent_engine_session_id` | string | Continue an Agent Engine session |
| `agent_engine_user_id` | string | User ID sent in the body and used for memory |
| `agent_engine_custom_headers` | `array<string, string>` | Forwarded custom headers |
| `agent_engine_timeout_seconds` | int | Invoke timeout (1–120) |
| `agent_engine_test_session` | bool | Mark the session as a test session |
| `agent_engine_custom_output` | bool | Treat frames as custom events |

The provider sends only the latest user message. Conversation history lives in the Agent Engine session, so pass `agent_engine_session_id` to continue one.

`AgentEngineClient` is autowirable:

```php
final readonly class SupportController
{
    public function __construct(private AgentEngineClient $client)
    {
    }
}
```

## Notes

- Agent Engine is in public preview. Pin `contract_version` when you need a stable API contract.
- The provider sends no Symfony AI system prompt or tools; define those on the Agent Engine agent.
- Memory is read through the bundle but not written. Record turns with `$client->memory->recordTurn(...)`, or let the Agent Engine agent record its own.
- A failed memory lookup propagates and fails the invocation. Wrap `AgentEngineMemoryProvider` if you would rather degrade to no memory.
