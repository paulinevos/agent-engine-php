<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\Execution\ExecutionStatus;
use AgentEngine\Http\SseFrame;
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\SymfonyAi\Platform\AgentEngineRawResult;
use AgentEngine\SymfonyAi\Platform\AgentEngineResultConverter;
use AgentEngine\Value\ExecutionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;

final class AgentEngineResultConverterTest extends TestCase
{
    #[Test]
    public function it_converts_a_successful_result_to_text(): void
    {
        $result = (new AgentEngineResultConverter())->convert(new AgentEngineRawResult($this->invokeResult()));

        self::assertInstanceOf(TextResult::class, $result);
        self::assertSame('Hello', $result->getContent());
    }

    #[Test]
    public function it_rejects_a_failed_result(): void
    {
        $this->expectException(RuntimeException::class);

        (new AgentEngineResultConverter())->convert(new AgentEngineRawResult($this->invokeResult(
            success: false,
            error: 'The agent crashed.',
        )));
    }

    #[Test]
    public function it_converts_text_frames_to_text_deltas(): void
    {
        $stream = new InvocationStream([
            new SseFrame(json_encode(['chunk_type' => 'text', 'content' => 'Hel'], JSON_THROW_ON_ERROR)),
            new SseFrame(json_encode(['chunk_type' => 'text', 'content' => 'lo'], JSON_THROW_ON_ERROR)),
            new SseFrame(json_encode(['chunk_type' => 'done'], JSON_THROW_ON_ERROR)),
        ]);

        $result = (new AgentEngineResultConverter())->convert(new AgentEngineRawResult($stream));

        self::assertInstanceOf(StreamResult::class, $result);

        $texts = [];

        foreach ($result->getContent() as $delta) {
            self::assertInstanceOf(TextDelta::class, $delta);
            $texts[] = $delta->getText();
        }

        self::assertSame(['Hel', 'lo'], $texts);
    }

    #[Test]
    public function it_throws_on_an_error_frame(): void
    {
        $stream = new InvocationStream([
            new SseFrame(json_encode(['chunk_type' => 'error', 'content' => 'boom'], JSON_THROW_ON_ERROR)),
        ]);

        $result = (new AgentEngineResultConverter())->convert(new AgentEngineRawResult($stream));
        self::assertInstanceOf(StreamResult::class, $result);

        $this->expectException(RuntimeException::class);

        iterator_to_array($result->getContent());
    }

    private function invokeResult(bool $success = true, ?string $error = null): InvokeResult
    {
        return new InvokeResult(
            success: $success,
            executionId: ExecutionId::fromString('execution-1'),
            status: $success ? ExecutionStatus::Completed : ExecutionStatus::Error,
            rawStatus: $success ? 'completed' : 'error',
            response: $success ? 'Hello' : null,
            error: $error,
        );
    }
}
