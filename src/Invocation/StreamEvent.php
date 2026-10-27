<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Exception\UnexpectedResponse;
use AgentEngine\Http\SseFrame;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\ExecutionId;

/**
 * One frame from `invokeStream`. Standard chunks carry a `chunk_type`;
 * custom-output agents emit arbitrary JSON with no `chunk_type`.
 */
final readonly class StreamEvent
{
    private function __construct(
        public StreamChunkType $type,
        public mixed $content,
        public JsonObject $metadata,
        public ?ExecutionId $executionId = null,
    ) {
    }

    public static function fromFrame(SseFrame $frame, bool $forceCustom = false): self
    {
        $decoded = json_decode($frame->data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw UnexpectedResponse::invalidJson($frame->data);
        }

        if ($forceCustom || !is_array($decoded) || array_is_list($decoded) || !isset($decoded['chunk_type'])) {
            return new self(StreamChunkType::Custom, $decoded, new JsonObject());
        }

        return new self(
            type: self::chunkType($decoded['chunk_type']),
            content: $decoded['content'] ?? null,
            metadata: is_array($decoded['metadata'] ?? null)
                ? JsonObject::fromArray($decoded['metadata'])
                : new JsonObject(),
            executionId: isset($decoded['execution_id']) && is_string($decoded['execution_id'])
                ? ExecutionId::fromString($decoded['execution_id'])
                : null,
        );
    }

    public function isText(): bool
    {
        return $this->type === StreamChunkType::Text;
    }

    public function isTerminal(): bool
    {
        return $this->type === StreamChunkType::Done || $this->type === StreamChunkType::Error;
    }

    public function textDelta(): string
    {
        if (!$this->isText()) {
            throw UnexpectedResponse::missingField('text chunk');
        }

        return is_string($this->content) ? $this->content : '';
    }

    public function errorMessage(): string
    {
        if ($this->type !== StreamChunkType::Error) {
            throw UnexpectedResponse::missingField('error chunk');
        }

        if (is_string($this->content)) {
            return $this->content;
        }

        return is_array($this->content) && is_string($this->content['message'] ?? null)
            ? $this->content['message']
            : 'The agent run failed.';
    }

    private static function chunkType(mixed $value): StreamChunkType
    {
        if (!is_string($value)) {
            throw InvalidArgument::unknownStreamChunkType(get_debug_type($value));
        }

        return StreamChunkType::tryFrom($value) ?? throw InvalidArgument::unknownStreamChunkType($value);
    }
}
