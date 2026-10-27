<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Http\SseFrame;
use AgentEngine\Invocation\StreamChunkType;
use AgentEngine\Invocation\StreamEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StreamEventTest extends TestCase
{
    #[Test]
    public function it_reads_a_text_chunk(): void
    {
        $event = StreamEvent::fromFrame(new SseFrame(
            '{"chunk_type":"text","content":"Hello","metadata":{},"execution_id":"exec-1"}',
        ));

        self::assertSame(StreamChunkType::Text, $event->type);
        self::assertSame('Hello', $event->textDelta());
        self::assertSame('exec-1', $event->executionId?->value);
        self::assertFalse($event->isTerminal());
    }

    #[Test]
    public function it_reads_a_done_chunk_as_terminal(): void
    {
        $event = StreamEvent::fromFrame(new SseFrame('{"chunk_type":"done"}'));

        self::assertTrue($event->isTerminal());
    }

    #[Test]
    public function it_reads_an_error_chunk(): void
    {
        $event = StreamEvent::fromFrame(new SseFrame('{"chunk_type":"error","content":"boom"}'));

        self::assertSame('boom', $event->errorMessage());
        self::assertTrue($event->isTerminal());
    }

    #[Test]
    public function it_treats_an_object_without_a_chunk_type_as_a_custom_event(): void
    {
        $event = StreamEvent::fromFrame(new SseFrame('{"kind":"progress","percent":50}'));

        self::assertSame(StreamChunkType::Custom, $event->type);
        self::assertSame(['kind' => 'progress', 'percent' => 50], $event->content);
    }

    #[Test]
    public function it_rejects_an_unknown_chunk_type(): void
    {
        $this->expectException(InvalidArgument::class);

        StreamEvent::fromFrame(new SseFrame('{"chunk_type":"teleport"}'));
    }
}
