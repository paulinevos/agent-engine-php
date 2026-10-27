<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class ContractVersion
{
    private const SUPPORTED_PATTERN = '/^\d{4}-\d{2}-\d{2}-preview$/';
    private const PREVIEW_VERSION = '2026-09-20-preview';

    private function __construct(public string $value)
    {
        if (preg_match(self::SUPPORTED_PATTERN, $value) !== 1) {
            throw InvalidArgument::invalidContractVersion($value);
        }
    }

    public static function default(): self
    {
        return new self(self::PREVIEW_VERSION);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function acceptHeader(): string
    {
        return 'application/vnd.agent-engine-' . $this->value . '+json';
    }

    public function streamingAcceptHeader(): string
    {
        return 'text/event-stream, ' . $this->acceptHeader();
    }
}
