<?php

declare(strict_types=1);

namespace AgentEngine\Invocation;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Value\CustomHeaders;
use AgentEngine\Value\SessionId;

final readonly class InvocationOptions
{
    public function __construct(
        public CustomHeaders $customHeaders = new CustomHeaders(),
        public ?SessionId $sessionId = null,
        public ?int $timeoutSeconds = null,
        public bool $testSession = false,
        public bool $customOutput = false,
    ) {
        if ($timeoutSeconds !== null && ($timeoutSeconds < 1 || $timeoutSeconds > 120)) {
            throw InvalidArgument::timeoutOutOfRange($timeoutSeconds, 1, 120);
        }
    }

    public static function none(): self
    {
        return new self();
    }

    public function withSession(SessionId $sessionId): self
    {
        return new self($this->customHeaders, $sessionId, $this->timeoutSeconds, $this->testSession, $this->customOutput);
    }

    public function withCustomHeader(string $name, string $value): self
    {
        return new self(
            $this->customHeaders->with($name, $value),
            $this->sessionId,
            $this->timeoutSeconds,
            $this->testSession,
            $this->customOutput,
        );
    }

    public function withTimeout(int $timeoutSeconds): self
    {
        return new self($this->customHeaders, $this->sessionId, $timeoutSeconds, $this->testSession, $this->customOutput);
    }

    public function asTestSession(): self
    {
        return new self($this->customHeaders, $this->sessionId, $this->timeoutSeconds, true, $this->customOutput);
    }

    /**
     * Treat every streamed frame as a custom event, for agents that set
     * `features.use_custom_parser` and may emit their own `chunk_type`.
     */
    public function asCustomOutput(): self
    {
        return new self($this->customHeaders, $this->sessionId, $this->timeoutSeconds, $this->testSession, true);
    }

    /**
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = $this->customHeaders->prefixed();

        if ($this->sessionId !== null) {
            $headers['X-Session-ID'] = $this->sessionId->value;
        }

        if ($this->timeoutSeconds !== null) {
            $headers['X-Agent-Engine-Invoke-Timeout-Seconds'] = (string) $this->timeoutSeconds;
        }

        if ($this->testSession) {
            $headers['X-Agent-Engine-Test-Session'] = 'true';
        }

        return $headers;
    }
}
