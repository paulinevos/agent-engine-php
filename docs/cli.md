# CLI

The package installs `vendor/bin/agent-engine` with two commands: `deploy` and `invoke`.

For Symfony applications, use the [Symfony AI bundle](../packages/symfony-ai/README.md). It provides `agent-engine:deploy` and `agent-engine:invoke` with configuration and autowiring.

## Credentials

| Variable | Purpose |
| --- | --- |
| `AGENT_ENGINE_BASE_URL` | API base URL; defaults to `https://agentengine.mongodb.com` |
| `AGENT_ENGINE_PROJECT_ID` | Project scope |
| `AGENT_ENGINE_WORKSPACE_ID` | Workspace scope |
| `AGENT_ENGINE_CLIENT_ID` | Service account client ID |
| `AGENT_ENGINE_CLIENT_SECRET` | Service account client secret |

Flags override environment variables. Prefer the environment for the secret. If it is missing in an interactive terminal, the command prompts with hidden input.

## deploy

```bash
agent-engine deploy [path] [options]
```

`path` defaults to `.` and must contain `agent.yaml`.

| Option | Description |
| --- | --- |
| `--base-url` | API base URL |
| `--project` | Project ID |
| `--workspace` | Workspace ID |
| `--client-id` | Service account client ID |
| `--client-secret` | Service account client secret |
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

The command archives the directory, uploads it to the presigned URL, starts the build, waits for it, creates the deployment, and waits for every component to become healthy.

Default exclusions: `.git`, `.venv`, `__pycache__`, `.agentengine`, `.env`, `node_modules`, `vendor`, `var`, `.phpunit.cache`, `.idea`, `.DS_Store`.

The flow targets archive-source workspaces. A GitHub-connected workspace rejects `archive-init` with `409 UNSUPPORTED_SOURCE_TYPE`; use its webhook pipeline or create an archive-source workspace.

## invoke

```bash
agent-engine invoke [message] [options]
```

Omit `message`, or pass `-`, to read the message from stdin.

| Option | Description |
| --- | --- |
| `--base-url` | API base URL |
| `--project` | Project ID |
| `--workspace` | Workspace ID |
| `--client-id` | Service account client ID |
| `--client-secret` | Service account client secret |
| `--session` | Session ID to continue |
| `--user-id` | User ID forwarded to the agent |
| `--payload` | JSON payload forwarded to the agent |
| `--header` | Custom header as `Name: value`; repeatable |
| `--stream` | Stream text as it arrives |
| `--invoke-timeout` | Platform invoke timeout in seconds; `1`–`120` |
| `--json` | Print JSON to stdout |

## Exit codes

| Code | Meaning |
| --- | --- |
| `0` | Success |
| `1` | General failure |
| `2` | Invalid usage |
| `3` | Authentication or permission failure |
| `4` | Build failed or timed out |
| `5` | Deployment failed or timed out |

## Examples

```bash
# Deploy the agent in the current directory, with git info detected
vendor/bin/agent-engine deploy . --label=release-42

# Validate the archive locally without credentials or a network call
vendor/bin/agent-engine deploy . --dry-run

# Start a build and return immediately
vendor/bin/agent-engine deploy . --no-wait --json

# Invoke and stream the response
vendor/bin/agent-engine invoke "Summarise the open tickets" --stream

# Continue a session and emit JSON
vendor/bin/agent-engine invoke "And the oldest one?" --session=my-session --json

# Forward a tenant header and a payload
vendor/bin/agent-engine invoke "Show the policy list" \
  --header="Tenant-Id: acme" \
  --payload='{"screen":"policy-list"}'
```
