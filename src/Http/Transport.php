<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

interface Transport
{
    public function send(RequestInterface $request): ResponseInterface;

    public function stream(RequestInterface $request): StreamingResponse;
}
