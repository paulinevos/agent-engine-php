<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\SymfonyAi\Platform\WorkspaceMap;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\ModelNotFoundException;

final class WorkspaceMapTest extends TestCase
{
    #[Test]
    public function it_resolves_a_configured_model_to_its_workspace(): void
    {
        $map = WorkspaceMap::fromStrings(['support-agent' => 'workspace-1']);

        self::assertTrue($map->contains('support-agent'));
        self::assertSame('workspace-1', $map->workspaceFor('support-agent')->value);
    }

    #[Test]
    public function it_reports_an_unknown_model(): void
    {
        $map = WorkspaceMap::fromStrings(['support-agent' => 'workspace-1']);

        self::assertFalse($map->contains('billing-agent'));

        $this->expectException(ModelNotFoundException::class);
        $map->workspaceFor('billing-agent');
    }

    #[Test]
    public function it_rejects_an_empty_map(): void
    {
        $this->expectException(InvalidArgumentException::class);
        WorkspaceMap::fromStrings([]);
    }

    #[Test]
    public function it_lists_the_configured_model_names(): void
    {
        $map = WorkspaceMap::fromStrings([
            'support-agent' => 'workspace-1',
            'billing-agent' => 'workspace-2',
        ]);

        self::assertSame(['support-agent', 'billing-agent'], $map->modelNames());
    }
}
