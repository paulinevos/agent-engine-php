<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class SessionBusy extends Conflict
{
    public function blockingExecutionId(): ?string
    {
        return $this->details->body->optionalString('blocking_execution_id');
    }

    public function blockingStatus(): ?string
    {
        return $this->details->body->optionalString('blocking_status');
    }
}
