# Framework integration

The core library knows nothing about a framework. An adapter needs to do three things:

1. Map the library's model or agent identifier to a [`WorkspaceId`](https://www.mongodb.com/docs/agentengine/manage/project-org-workspace/).
2. Map its input to `InvokeRequest::message()`, which reaches the agent through the [invocation API](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/).
3. Map [`InvokeResult`](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/) or `InvocationStream` back to the library's result type.

Sessions and [forwarded headers](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/#std-label-agent-engine-forward-custom-headers) travel through `InvocationOptions`, so an adapter can expose them as options without touching the transport. [Human-in-the-loop](https://www.mongodb.com/docs/agentengine/deploy/human-in-the-loop/) decisions use the same `InvokeResult` and the execution resume call.

## Symfony AI

A ready-made bundle lives in [`packages/symfony-ai`](../packages/symfony-ai). It registers an `ai.platform` provider, an autowirable `AgentEngineClient`, and a `MemoryProviderInterface` backed by Agent Engine memory. It targets `symfony/ai-*` v1.0.0-BETA1.

```yaml
agent_engine:
    project_id: '%env(AGENT_ENGINE_PROJECT_ID)%'
    auth:
        client_id: '%env(AGENT_ENGINE_CLIENT_ID)%'
        client_secret: '%env(AGENT_ENGINE_CLIENT_SECRET)%'
    platform:
        models:
            support-agent: '%env(AGENT_ENGINE_WORKSPACE_ID)%'
    memory:
        user_id: 'demo-user'
```

```yaml
ai:
    agent:
        support:
            platform: agent_engine.platform
            model: support-agent
            memory:
                service: agent_engine.memory_provider
```

Register `AgentEngineBundle` before `AiBundle` in `config/bundles.php` so the provider's `ai.platform` tag is visible when `AiBundle` builds its aliases.

Building the bridge by hand instead means implementing `ModelClientInterface`, `ResultConverterInterface`, and `ModelCatalogInterface`, then registering them in a `Symfony\AI\Platform\Provider` tagged `ai.platform`. The platform API is experimental and changed repeatedly during 2026; pin the beta you build against.

## Laravel AI

Package: [`laravel/ai`](https://laravel.com/docs/12.x/ai) (`Laravel\Ai`), PHP 8.3+ ([source](https://github.com/laravel/ai)).

`Laravel\Ai\AiManager` resolves providers through `extend()`. A provider extends `Laravel\Ai\Providers\Provider` and delegates HTTP work to a `Gateway`. `AgentPrompt`, `AgentResponse`, and `StreamableAgentResponse` are the boundary types to map.

```php
use Illuminate\Contracts\Foundation\Application;
use Laravel\Ai\AiManager;

$app->afterResolving(AiManager::class, function (AiManager $ai): void {
    $ai->extend('agent-engine', static fn (Application $app, array $config) => new AgentEngineProvider(
        $app->make(AgentEngineClient::class),
        WorkspaceId::fromString($config['workspace_id']),
    ));
});
```

```php
// config/ai.php
'providers' => [
    'agent-engine' => [
        'driver' => 'agent-engine',
        'workspace_id' => env('AGENT_ENGINE_WORKSPACE_ID'),
    ],
],
```

Implement the provider's `prompt()` by taking the latest user message from the prompt, calling `invocations->invoke()`, and building an `AgentResponse`. Implement `stream()` by returning the `InvocationStream` wrapped in a `StreamableAgentResponse`. The constructor signatures of the Laravel response types are the version-sensitive part: read them from the installed tag before finalising the mapping.

## Prism

Package: [`prism-php/prism`](https://prismphp.com/), PHP 8.2+ ([source](https://github.com/prism-php/prism)). It requires Laravel.

```php
use Prism\Prism\Facades\Prism;

Prism::extend('agent-engine', static fn ($app, array $config) => new AgentEnginePrismProvider(
    $app->make(AgentEngineClient::class),
    WorkspaceId::fromString($config['workspace_id']),
));
```

Extend [`Prism\Prism\Providers\Provider`](https://prismphp.com/) and implement `text(TextRequest $request): TextResponse` and `stream(TextRequest $request): Generator`. Map the request's prompt text to `InvokeRequest::message()`, and the `InvokeResult` text or `InvocationStream` deltas to `TextResponse` / stream chunks.

## Libraries without a provider contract

Wrap the client in whatever interface the library exposes; the three operations above are the whole mapping. Keep the wrapper thin, and pass `InvocationOptions` through so callers can set a session, [custom headers](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/#std-label-agent-engine-forward-custom-headers), or the invoke timeout.

For framework-side plumbing, the usual complements are [Symfony Messenger](https://symfony.com/doc/current/messenger.html) or [Laravel queues](https://laravel.com/docs/queues) for long runs, [Symfony's `StreamedResponse`](https://symfony.com/doc/current/components/http_foundation.html#streaming-a-response) or [Laravel streamed responses](https://laravel.com/docs/responses#streamed-responses) for SSE delivery, and the framework's own authorization for review screens.

## Symfony AI bundle

The [`packages/symfony-ai`](../packages/symfony-ai/README.md) sub-package (`vos/agent-engine-symfony-ai`) wires the client into Symfony AI and provides the `agent-engine:deploy` and `agent-engine:invoke` console commands. Start with its README for installation and configuration.

## Laravel

A Laravel package can expose Artisan commands that delegate to `AgentDeployer` and `Invocations`. Keep the wrapper to option mapping and output. The deploy sequence, polling, and error handling live in `AgentDeployer`; the package should not reimplement them.
