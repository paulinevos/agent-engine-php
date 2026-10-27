<?php

declare(strict_types=1);

namespace AgentEngine\Http;

/**
 * Status and headers parsed from the raw response header block. Redirects
 * produce several blocks in one list; the last block is the final response.
 */
final readonly class ResponseMeta
{
    /**
     * @param array<string, list<string>> $headers
     */
    private function __construct(
        public int $status,
        public array $headers,
    ) {
    }

    /**
     * @param list<string> $lines
     */
    public static function fromHeaderLines(array $lines): self
    {
        $status = 0;
        $headers = [];

        foreach ($lines as $line) {
            if (str_starts_with($line, 'HTTP/')) {
                $status = (int) (explode(' ', $line)[1] ?? 0);
                $headers = [];
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $headers[strtolower(substr($line, 0, $colon))][] = trim(substr($line, $colon + 1));
        }

        return new self($status === 0 ? 200 : $status, $headers);
    }
}
