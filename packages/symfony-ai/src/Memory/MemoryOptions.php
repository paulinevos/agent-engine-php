<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Memory;

use AgentEngine\Memory\MemoryContextQuery;
use AgentEngine\SymfonyAi\Platform\InvocationOptionsFactory;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;
use Symfony\AI\Agent\Input;

final readonly class MemoryOptions
{
    /**
     * @param list<string> $enabledSources
     */
    public function __construct(
        private UserId $defaultUserId,
        private ?SessionId $defaultSessionId = null,
        private ?int $maxTokens = null,
        private ?int $topK = null,
        private array $enabledSources = [],
        private ?string $formatStyle = null,
    ) {
    }

    public function contextQuery(string $query, Input $input): MemoryContextQuery
    {
        return new MemoryContextQuery(
            query: $query,
            userId: $this->userId($input),
            sessionId: $this->sessionId($input),
            enabledSources: $this->enabledSources,
            maxTokens: $this->maxTokens,
            topK: $this->topK,
            formatStyle: $this->formatStyle,
        );
    }

    private function userId(Input $input): UserId
    {
        $userId = $input->getOptions()[InvocationOptionsFactory::USER_ID] ?? null;

        if (is_string($userId) && '' !== $userId) {
            return UserId::fromString($userId);
        }

        return $this->defaultUserId;
    }

    private function sessionId(Input $input): ?SessionId
    {
        $sessionId = $input->getOptions()[InvocationOptionsFactory::SESSION_ID] ?? null;

        return is_string($sessionId) && '' !== $sessionId
            ? SessionId::fromString($sessionId)
            : $this->defaultSessionId;
    }
}
