<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class OAuthFailure extends AgentEngineException
{
    private function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function fromResponse(int $status, string $error, string $description): self
    {
        return new self($status, sprintf(
            'Could not obtain an access token (%d %s): %s',
            $status,
            $error,
            $description,
        ));
    }

    public function isRateLimited(): bool
    {
        return $this->status === 429;
    }
}
