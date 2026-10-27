<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\AgentEngineClient;
use AgentEngine\Exception\AgentEngineException;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Value\UserId;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawResultInterface;

final readonly class AgentEngineModelClient implements ModelClientInterface
{
    public function __construct(
        private AgentEngineClient $client,
        private WorkspaceMap $workspaces,
        private ExceptionTranslator $exceptions,
        private InvocationOptionsFactory $options,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $this->workspaces->contains($model->getName());
    }

    /**
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     *
     * @throws ExceptionInterface
     */
    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        try {
            return $this->send($model, PayloadMessage::from($payload), $options);
        } catch (AgentEngineException $exception) {
            throw $this->exceptions->translate($exception);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function send(Model $model, string $message, array $options): AgentEngineRawResult
    {
        $workspace = $this->workspaces->workspaceFor($model->getName());
        $request = $this->invokeRequest($message, $options);
        $invocation = $this->options->from($options);

        return ($options['stream'] ?? false) === true
            ? new AgentEngineRawResult($this->client->invocations->stream($workspace, $request, $invocation))
            : new AgentEngineRawResult($this->client->invocations->invoke($workspace, $request, $invocation));
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invokeRequest(string $message, array $options): InvokeRequest
    {
        $request = InvokeRequest::message($message);
        $userId = $options[InvocationOptionsFactory::USER_ID] ?? null;

        if (!is_string($userId) || '' === $userId) {
            return $request;
        }

        return $request->withUserId(UserId::fromString($userId));
    }
}
