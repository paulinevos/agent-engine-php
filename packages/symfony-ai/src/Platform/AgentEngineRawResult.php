<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Invocation\StreamChunkType;
use Symfony\AI\Platform\Result\RawResultInterface;

final readonly class AgentEngineRawResult implements RawResultInterface
{
    public function __construct(private InvokeResult|InvocationStream $result)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        if (!$this->result instanceof InvokeResult) {
            return [];
        }

        return [
            'response' => $this->result->text(),
            'execution_id' => $this->result->executionId->value,
            'session_id' => $this->result->sessionId?->value,
            'status' => $this->result->status->value,
        ];
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function getDataStream(): iterable
    {
        if (!$this->result instanceof InvocationStream) {
            return;
        }

        foreach ($this->result as $event) {
            if (StreamChunkType::Error === $event->type) {
                yield ['error' => $event->errorMessage()];

                return;
            }

            if (!$event->isText()) {
                continue;
            }

            yield ['text' => $event->textDelta()];
        }
    }

    public function getObject(): object
    {
        return $this->result;
    }
}
