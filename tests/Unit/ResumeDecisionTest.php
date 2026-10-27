<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Execution\ResumeDecision;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResumeDecisionTest extends TestCase
{
    #[Test]
    public function it_builds_a_flat_resume_body(): void
    {
        self::assertSame(
            '{"decision":"approve","reviewer_notes":"looks good"}',
            ResumeDecision::approve('looks good')->toJson()->toJson(),
        );
    }

    #[Test]
    public function it_pins_the_decision_to_a_suspend_generation(): void
    {
        self::assertSame(
            '{"decision":"reject","suspend_generation":2}',
            ResumeDecision::reject()->withSuspendGeneration(2)->toJson()->toJson(),
        );
    }
}
