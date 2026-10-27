<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Invocation\StreamChunkType;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final readonly class AgentEngineResultConverter implements ResultConverterInterface
{
    /**
     * Only registered on the Agent Engine provider, so every model it sees
     * belongs to an Agent Engine workspace.
     */
    public function supports(Model $model): bool
    {
        return true;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $raw = $result->getObject();

        if ($raw instanceof InvocationStream) {
            return new StreamResult($this->textDeltas($raw));
        }

        if (!$raw instanceof InvokeResult) {
            throw new RuntimeException(sprintf('Unexpected Agent Engine result of type "%s".', get_debug_type($raw)));
        }

        if (!$raw->success) {
            throw new RuntimeException($raw->error ?? 'The Agent Engine run failed.');
        }

        return new TextResult($raw->text());
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    /**
     * @return \Generator<int, TextDelta>
     */
    private function textDeltas(InvocationStream $stream): \Generator
    {
        foreach ($stream as $event) {
            if (StreamChunkType::Error === $event->type) {
                throw new RuntimeException($event->errorMessage());
            }

            if (!$event->isText()) {
                continue;
            }

            yield new TextDelta($event->textDelta());
        }
    }
}
