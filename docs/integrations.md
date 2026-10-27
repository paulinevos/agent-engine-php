# Framework integration

The core library knows nothing about a framework. An adapter needs to do three things:

1. Map the library's model or agent identifier to a [`WorkspaceId`](https://www.mongodb.com/docs/agentengine/manage/project-org-workspace/).
2. Map its input to `InvokeRequest::message()`, which reaches the agent through the [invocation API](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/).
3. Map [`InvokeResult`](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/) or `InvocationStream` back to the library's result type.

Sessions and [forwarded headers](https://www.mongodb.com/docs/agentengine/deploy/invoke-agent/#std-label-agent-engine-forward-custom-headers) travel through `InvocationOptions`, so an adapter can expose them as options without touching the transport. [Human-in-the-loop](https://www.mongodb.com/docs/agentengine/deploy/human-in-the-loop/) decisions use the same `InvokeResult` and the execution resume call.

## Symfony AI

Packages: [`symfony/ai-platform`](https://symfony.com/doc/current/ai/index.html) (bridge packages are published as `symfony/ai-<provider>-platform`; the source lives in the [Symfony AI monorepo](https://github.com/symfony/ai)). The platform API is experimental and changed repeatedly during 2026; pin the beta you build against.

The extension points are `ModelClientInterface` and `ResultConverterInterface`. A model name selects the workspace, for example `agent-engine:support-agent`.

```php
use AgentEngine\AgentEngineClient;
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Value\WorkspaceId;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\RawResultInterface;

final readonly class AgentEngineModelClient implements ModelClientInterface
{
    public function __construct(
        private AgentEngineClient $client,
        private WorkspaceId $workspace,
    ) {
    }

    public function supports(Model $model): bool
    {
        return str_starts_with($model->getName(), 'agent-engine:');
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $message = is_string($payload)
            ? $payload
            : json_encode($payload, JSON_THROW_ON_ERROR);

        $request = InvokeRequest::message($message);

        return ($options['stream'] ?? false) === true
            ? new AgentEngineRawResult($this->client->invocations->stream($this->workspace, $request))
            : new AgentEngineRawResult($this->client->invocations->invoke($this->workspace, $request));
    }
}
```

```php
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use Symfony\AI\Platform\RawResultInterface;

final readonly class AgentEngineRawResult implements RawResultInterface
{
    public function __construct(private InvokeResult|InvocationStream $result)
    {
    }

    public function getData(): array
    {
        return $this->result instanceof InvokeResult
            ? ['response' => $this->result->text()]
            : [];
    }

    public function getDataStream(): iterable
    {
        return $this->result instanceof InvocationStream
            ? $this->result->textDeltas()
            : [];
    }

    public function getObject(): object
    {
        return $this->result;
    }
}
```

```php
use AgentEngine\Invocation\InvocationStream;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final readonly class AgentEngineResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return str_starts_with($model->getName(), 'agent-engine:');
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $raw = $result->getObject();

        return $raw instanceof InvocationStream
            ? new StreamResult($result->getDataStream())
            : new TextResult($raw->text());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
```

Register the two services and pass them to a [`Symfony\AI\Platform\Provider`](https://symfony.com/doc/current/ai/index.html). The bundle's exact custom-provider registration differs between beta releases; define the services yourself and reference the provider service from `ai.agent.<name>.platform`.

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
