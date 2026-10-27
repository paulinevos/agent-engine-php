<?php

declare(strict_types=1);

namespace AgentEngine\Auth;

use AgentEngine\Value\AccessToken;

interface TokenProvider
{
    public function token(): AccessToken;
}
