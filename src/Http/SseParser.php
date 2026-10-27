<?php

declare(strict_types=1);

namespace AgentEngine\Http;

/**
 * Incremental parser for the Server-Sent Events wire format. A frame is
 * dispatched on a blank line; `data:` lines are joined with newlines.
 */
final class SseParser
{
    /**
     * @param iterable<string> $lines
     *
     * @return \Generator<int, SseFrame>
     */
    public static function parse(iterable $lines): \Generator
    {
        $builder = new SseFrameBuilder();
        $index = 0;

        foreach ($lines as $line) {
            $frame = $builder->consume($line);

            if ($frame !== null) {
                yield $index++ => $frame;
            }
        }

        $frame = $builder->finish();

        if ($frame !== null) {
            yield $index => $frame;
        }
    }
}
