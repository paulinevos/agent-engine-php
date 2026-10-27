<?php

declare(strict_types=1);

namespace AgentEngine\Memory;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;

final readonly class MemorySearchQuery
{
    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $query,
        public string $type,
        public ?UserId $userId = null,
        public ?SessionId $sessionId = null,
        public ?int $topK = null,
        public array $tags = [],
        public ?float $similarityThreshold = null,
        public ?JsonObject $metadataFilter = null,
        public ?bool $rank = null,
        public ?bool $deduplicate = null,
        public ?float $deduplicationThreshold = null,
        public ?string $domain = null,
        public ?string $visibility = null,
    ) {
        if ($query === '') {
            throw InvalidArgument::emptyIdentifier('Memory query');
        }

        if ($type === '') {
            throw InvalidArgument::emptyIdentifier('Memory type');
        }

        if ($topK !== null && ($topK < 1 || $topK > 500)) {
            throw InvalidArgument::outOfRange('Top K', $topK, 1, 500);
        }
    }

    public static function of(string $query, string $type): self
    {
        return new self($query, $type);
    }

    public function toJson(): JsonObject
    {
        $body = [
            'query' => $this->query,
            'type' => $this->type,
            'user_id' => $this->userId?->value,
            'session_id' => $this->sessionId?->value,
            'top_k' => $this->topK,
            'tags' => $this->tags === [] ? null : $this->tags,
            'similarity_threshold' => $this->similarityThreshold,
            'metadata_filter' => $this->metadataFilter?->toArray(),
            'rank' => $this->rank,
            'deduplicate' => $this->deduplicate,
            'dedup_threshold' => $this->deduplicationThreshold,
            'domain' => $this->domain,
            'visibility' => $this->visibility,
        ];

        return JsonObject::fromArray(array_filter(
            $body,
            static fn (mixed $value): bool => $value !== null,
        ));
    }
}
