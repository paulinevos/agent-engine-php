<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class RateLimited extends ApiFailure
{
    public function retryAfterSeconds(): ?int
    {
        return $this->details->retryAfterSeconds;
    }
}
