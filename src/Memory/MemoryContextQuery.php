<?php

declare(strict_types=1);

namespace AgentEngine\Memory;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;

final readonly class MemoryContextQuery
{
    /**
     * @param list<string> $enabledSources
     * @param list<float> $queryEmbedding
     */
    public function __construct(
        public string $query,
        public UserId $userId,
        public ?SessionId $sessionId = null,
        public array $enabledSources = [],
        public ?int $maxTokens = null,
        public ?int $topK = null,
        public ?string $modelType = null,
        public ?float $similarityThreshold = null,
        public ?JsonObject $metadataFilter = null,
        public ?bool $includeMemories = null,
        public ?string $formatStyle = null,
        public ?string $visibility = null,
        public array $queryEmbedding = [],
    ) {
        if ($query === '') {
            throw InvalidArgument::emptyIdentifier('Memory query');
        }

        if ($maxTokens !== null && $maxTokens < 1) {
            throw InvalidArgument::belowMinimum('Max tokens', $maxTokens, 1);
        }

        if ($topK !== null && ($topK < 1 || $topK > 200)) {
            throw InvalidArgument::outOfRange('Top K', $topK, 1, 200);
        }
    }

    public static function forQuery(string $query, UserId $userId): self
    {
        return new self($query, $userId);
    }

    public function toJson(): JsonObject
    {
        $body = [
            'query' => $this->query,
            'user_id' => $this->userId->value,
            'session_id' => $this->sessionId?->value,
            'enabled_sources' => $this->enabledSources === [] ? null : $this->enabledSources,
            'max_tokens' => $this->maxTokens,
            'top_k' => $this->topK,
            'model_type' => $this->modelType,
            'similarity_threshold' => $this->similarityThreshold,
            'metadata_filter' => $this->metadataFilter?->toArray(),
            'include_memories' => $this->includeMemories,
            'format_style' => $this->formatStyle,
            'visibility' => $this->visibility,
            'query_embedding' => $this->queryEmbedding === [] ? null : $this->queryEmbedding,
        ];

        return JsonObject::fromArray(array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null,
        ));
    }
}
