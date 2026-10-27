<?php

declare(strict_types=1);

namespace AgentEngine\Http;

final class SseFrameBuilder
{
    /** @var list<string> */
    private array $data = [];

    private ?string $event = null;

    private ?string $id = null;

    private ?int $retryMilliseconds = null;

    public function consume(string $line): ?SseFrame
    {
        if ($line === '') {
            return $this->drain();
        }

        if (str_starts_with($line, ':')) {
            return null;
        }

        [$field, $value] = $this->split($line);
        $this->apply($field, $value);

        return null;
    }

    public function finish(): ?SseFrame
    {
        return $this->drain();
    }

    private function drain(): ?SseFrame
    {
        if ($this->data === []) {
            $this->reset();

            return null;
        }

        $frame = new SseFrame(
            implode("\n", $this->data),
            $this->event,
            $this->id,
            $this->retryMilliseconds,
        );

        $this->reset();

        return $frame;
    }

    private function apply(string $field, string $value): void
    {
        match ($field) {
            'data' => $this->data[] = $value,
            'event' => $this->event = $value,
            'id' => $this->id = $value,
            'retry' => $this->retryMilliseconds = $this->retry($value),
            default => null,
        };
    }

    private function retry(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : $this->retryMilliseconds;
    }

    private function reset(): void
    {
        $this->data = [];
        $this->event = null;
        $this->id = null;
        $this->retryMilliseconds = null;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function split(string $line): array
    {
        $colon = strpos($line, ':');

        if ($colon === false) {
            return [$line, ''];
        }

        $value = substr($line, $colon + 1);

        return [substr($line, 0, $colon), str_starts_with($value, ' ') ? substr($value, 1) : $value];
    }
}
