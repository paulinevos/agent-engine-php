<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Execution\Execution;
use AgentEngine\Execution\ExecutionStatus;
use AgentEngine\Json\JsonObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ExecutionTest extends TestCase
{
    #[Test]
    public function it_reads_an_execution_and_its_suspend_context(): void
    {
        $execution = Execution::fromJson(JsonObject::fromJson(json_encode([
            'execution_id' => 'exec-1',
            'status' => 'suspended',
            'session_id' => 'session-1',
            'message' => 'Please refund',
            'result' => null,
            'suspend_reason' => 'approval_required',
            'suspend_context' => [
                'allowed_decisions' => ['approve', 'reject'],
                'claim_id' => 'CLM-1',
            ],
        ]) ?: ''));

        self::assertSame(ExecutionStatus::Suspended, $execution->status);
        self::assertTrue($execution->isSuspended());
        self::assertFalse($execution->isTerminal());
        self::assertSame('session-1', $execution->sessionId?->value);
        $context = $execution->suspendContext;
        self::assertNotNull($context);
        self::assertSame(['approve', 'reject'], $context->allowedDecisions());
        self::assertSame('CLM-1', $context->toArray()['claim_id']);
    }

    #[Test]
    public function it_keeps_an_unknown_status_verbatim(): void
    {
        $execution = Execution::fromJson(JsonObject::fromJson('{"execution_id":"exec-1","status":"archived"}'));

        self::assertSame(ExecutionStatus::Unknown, $execution->status);
        self::assertSame('archived', $execution->rawStatus);
        self::assertFalse($execution->isTerminal());
    }
}
