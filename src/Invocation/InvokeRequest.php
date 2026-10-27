<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\UserId;

final readonly class InvokeRequest
{
    private const RESERVED_FIELDS = ['message', 'session_id', 'user_id', 'resume_map'];

    /**
     * @param array<string, mixed> $extra Agent-specific top-level fields.
     */
    private function __construct(
        public ?string $message = null,
        public ?JsonObject $payload = null,
        public ?JsonObject $resumeMap = null,
        public ?UserId $userId = null,
        public array $extra = [],
    ) {
    }

    public static function message(string $message): self
    {
        return new self(message: $message);
    }

    public static function resumeMap(JsonObject $resumeMap): self
    {
        return new self(resumeMap: $resumeMap);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function withMessage(string $message): self
    {
        return new self($message, $this->payload, $this->resumeMap, $this->userId, $this->extra);
    }

    public function withPayload(JsonObject $payload): self
    {
        return new self($this->message, $payload, $this->resumeMap, $this->userId, $this->extra);
    }

    public function withUserId(UserId $userId): self
    {
        return new self($this->message, $this->payload, $this->resumeMap, $userId, $this->extra);
    }

    public function withField(string $name, mixed $value): self
    {
        if (in_array($name, self::RESERVED_FIELDS, true)) {
            throw InvalidArgument::reservedField($name);
        }

        return new self(
            $this->message,
            $this->payload,
            $this->resumeMap,
            $this->userId,
            [...$this->extra, $name => $value],
        );
    }

    public function toJson(): JsonObject
    {
        $body = $this->extra;

        if ($this->message !== null) {
            $body['message'] = $this->message;
        }

        if ($this->payload !== null) {
            $body['payload'] = $this->payload->toArray();
        }

        if ($this->resumeMap !== null) {
            $body['resume_map'] = $this->resumeMap->toArray();
        }

        if ($this->userId !== null) {
            $body['user_id'] = $this->userId->value;
        }

        return JsonObject::fromArray($body);
    }
}
