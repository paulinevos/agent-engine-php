<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Http\SseParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SseParserTest extends TestCase
{
    #[Test]
    public function it_parses_data_frames_and_ignores_comments(): void
    {
        $frames = iterator_to_array(SseParser::parse([
            'data: {"a":1}',
            '',
            ': keep-alive',
            'event: text',
            'data: one',
            'data: two',
            '',
            'id: 42',
            'retry: 500',
            'data: tail',
        ]));

        self::assertCount(3, $frames);
        self::assertSame('{"a":1}', $frames[0]->data);
        self::assertSame('text', $frames[1]->event);
        self::assertSame("one\ntwo", $frames[1]->data);
        self::assertSame('42', $frames[2]->id);
        self::assertSame(500, $frames[2]->retryMilliseconds);
        self::assertSame('tail', $frames[2]->data);
    }

    #[Test]
    public function it_dispatches_a_final_frame_without_a_trailing_blank_line(): void
    {
        $frames = iterator_to_array(SseParser::parse(['data: last']));

        self::assertCount(1, $frames);
        self::assertSame('last', $frames[0]->data);
    }

    #[Test]
    public function it_ignores_frames_without_data(): void
    {
        self::assertSame([], iterator_to_array(SseParser::parse(['event: ping', '', ''])));
    }
}
