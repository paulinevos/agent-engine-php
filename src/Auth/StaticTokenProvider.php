<?php

declare(strict_types=1);

namespace AgentEngine\Auth;

use AgentEngine\Value\AccessToken;

final readonly class StaticTokenProvider implements TokenProvider
{
    public function __construct(private AccessToken $token)
    {
    }

    public function token(): AccessToken
    {
        return $this->token;
    }
}
