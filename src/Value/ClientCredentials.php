<?php

declare(strict_types=1);

namespace AgentEngine\Value;

final readonly class ClientCredentials
{
    private function __construct(
        public ClientId $clientId,
        public ClientSecret $clientSecret,
    ) {
    }

    public static function fromStrings(string $clientId, string $clientSecret): self
    {
        return new self(
            ClientId::fromString($clientId),
            ClientSecret::fromString($clientSecret),
        );
    }
}
