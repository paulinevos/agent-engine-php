<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\Exception\AgentEngineException;
use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\RateLimited;
use AgentEngine\Exception\RequestRejected;
use AgentEngine\Exception\ServerFailure;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ExceptionInterface;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;

final readonly class ExceptionTranslator
{
    public function translate(AgentEngineException $exception): ExceptionInterface
    {
        return match (true) {
            $exception instanceof AuthenticationRejected => new AuthenticationException($exception->getMessage(), 0, $exception),
            $exception instanceof RateLimited => new RateLimitExceededException($exception->retryAfterSeconds(), $exception->getMessage()),
            $exception instanceof RequestRejected => new BadRequestException($exception->getMessage(), 0, $exception),
            $exception instanceof ServerFailure => new ServerException($exception->details->status, $exception->getMessage()),
            default => new RuntimeException($exception->getMessage(), 0, $exception),
        };
    }
}
