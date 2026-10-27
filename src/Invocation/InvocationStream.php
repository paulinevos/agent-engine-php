<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

use AgentEngine\Http\SseFrame;
use AgentEngine\Value\SessionId;

/**
 * Single-pass iterator over the streamed frames. Iterate it once, or call
 * `collectText()` once; the underlying SSE generator cannot be rewound.
 *
 * @implements \IteratorAggregate<int, StreamEvent>
 */
final readonly class InvocationStream implements \IteratorAggregate
{
    /**
     * @param iterable<SseFrame> $frames
     */
    public function __construct(
        private iterable $frames,
        public ?SessionId $sessionId = null,
        private bool $forceCustom = false,
    ) {
    }

    /**
     * @return \Generator<int, StreamEvent>
     */
    public function getIterator(): \Generator
    {
        foreach ($this->frames as $frame) {
            yield StreamEvent::fromFrame($frame, $this->forceCustom);
        }
    }

    /**
     * @return \Generator<int, string>
     */
    public function textDeltas(): \Generator
    {
        foreach ($this as $event) {
            if (!$event->isText()) {
                continue;
            }

            yield $event->textDelta();
        }
    }

    public function collectText(): string
    {
        $text = '';

        foreach ($this as $event) {
            if ($event->type === StreamChunkType::Error) {
                throw new \AgentEngine\Exception\StreamFailed($event->errorMessage(), $event->executionId);
            }

            if ($event->isText()) {
                $text .= $event->textDelta();
            }
        }

        return $text;
    }
}
