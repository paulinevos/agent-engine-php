<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\SymfonyAi\Memory\MemoryOptions;
use AgentEngine\SymfonyAi\Platform\InvocationOptionsFactory;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Input;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;

final class MemoryOptionsTest extends TestCase
{
    #[Test]
    public function it_builds_a_query_with_the_configured_defaults(): void
    {
        $options = new MemoryOptions(
            defaultUserId: UserId::fromString('user-1'),
            defaultSessionId: SessionId::fromString('session-1'),
            maxTokens: 1000,
            topK: 8,
            enabledSources: ['semantic'],
            formatStyle: 'bullet',
        );

        $query = $options->contextQuery('Where do I live?', $this->input([]));

        self::assertSame(
            [
                'query' => 'Where do I live?',
                'user_id' => 'user-1',
                'session_id' => 'session-1',
                'enabled_sources' => ['semantic'],
                'max_tokens' => 1000,
                'top_k' => 8,
                'format_style' => 'bullet',
            ],
            $query->toJson()->toArray(),
        );
    }

    #[Test]
    public function it_prefers_the_per_invocation_identity(): void
    {
        $options = new MemoryOptions(UserId::fromString('user-1'));

        $query = $options->contextQuery('Where do I live?', $this->input([
            InvocationOptionsFactory::USER_ID => 'user-2',
            InvocationOptionsFactory::SESSION_ID => 'session-2',
        ]));

        self::assertSame('user-2', $query->userId->value);
        self::assertSame('session-2', $query->sessionId?->value);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function input(array $options): Input
    {
        return new Input(
            'support-agent',
            new MessageBag(new UserMessage(new Text('Where do I live?'))),
            $options,
        );
    }
}
