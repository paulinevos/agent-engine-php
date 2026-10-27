<?php

declare(strict_types=1);

namespace AgentEngine\Value;

use AgentEngine\Exception\InvalidArgument;

final readonly class AccessToken
{
    private function __construct(
        public string $value,
        public \DateTimeImmutable $expiresAt,
    ) {
        if ($value === '') {
            throw InvalidArgument::emptyIdentifier('Access token');
        }
    }

    public static function fromResponse(
        string $value,
        int $expiresInSeconds,
        \DateTimeImmutable $issuedAt,
    ): self {
        return new self($value, $issuedAt->modify('+' . $expiresInSeconds . ' seconds'));
    }

    public static function fromParts(string $value, \DateTimeImmutable $expiresAt): self
    {
        return new self($value, $expiresAt);
    }

    public function isUsableAt(\DateTimeImmutable $moment, int $leewaySeconds = 60): bool
    {
        return $moment->modify('+' . $leewaySeconds . ' seconds') < $this->expiresAt;
    }

    public function headerValue(): string
    {
        return 'Bearer ' . $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value
            && $this->expiresAt->getTimestamp() === $other->expiresAt->getTimestamp();
    }

    public function __debugInfo(): array
    {
        return ['value' => '[redacted]', 'expiresAt' => $this->expiresAt];
    }
}
