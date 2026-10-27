<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\Execution\ExecutionStatus;
use AgentEngine\Http\SseFrame;
use AgentEngine\Invocation\InvocationStream;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\SymfonyAi\Platform\AgentEngineRawResult;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\SessionId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentEngineRawResultTest extends TestCase
{
    #[Test]
    public function it_exposes_the_buffered_result_data(): void
    {
        $raw = new AgentEngineRawResult($this->invokeResult());

        self::assertSame(
            [
                'response' => 'Hello',
                'execution_id' => 'execution-1',
                'session_id' => 'session-1',
                'status' => 'completed',
            ],
            $raw->getData(),
        );
        self::assertSame([], iterator_to_array($raw->getDataStream(), false));
    }

    #[Test]
    public function it_turns_text_frames_into_stream_data(): void
    {
        $raw = new AgentEngineRawResult($this->stream([
            ['chunk_type' => 'text', 'content' => 'Hel'],
            ['chunk_type' => 'step', 'content' => ['step' => 1]],
            ['chunk_type' => 'text', 'content' => 'lo'],
            ['chunk_type' => 'done'],
        ]));

        self::assertSame(
            [['text' => 'Hel'], ['text' => 'lo']],
            iterator_to_array($raw->getDataStream(), false),
        );
        self::assertSame([], $raw->getData());
    }

    #[Test]
    public function it_surfaces_an_error_frame(): void
    {
        $raw = new AgentEngineRawResult($this->stream([
            ['chunk_type' => 'error', 'content' => 'boom'],
        ]));

        self::assertSame(
            [['error' => 'boom']],
            iterator_to_array($raw->getDataStream(), false),
        );
    }

    private function invokeResult(): InvokeResult
    {
        return new InvokeResult(
            success: true,
            executionId: ExecutionId::fromString('execution-1'),
            status: ExecutionStatus::Completed,
            rawStatus: 'completed',
            response: 'Hello',
            sessionId: SessionId::fromString('session-1'),
        );
    }

    /**
     * @param list<array<string, mixed>> $frames
     */
    private function stream(array $frames): InvocationStream
    {
        return new InvocationStream(array_map(
            static fn (array $frame): SseFrame => new SseFrame(json_encode($frame, JSON_THROW_ON_ERROR)),
            $frames,
        ));
    }
}
