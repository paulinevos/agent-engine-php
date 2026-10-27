<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use AgentEngine\Invocation\InvocationOptions;
use AgentEngine\Value\CustomHeaders;
use AgentEngine\Value\SessionId;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final readonly class InvocationOptionsFactory
{
    public const SESSION_ID = 'agent_engine_session_id';
    public const USER_ID = 'agent_engine_user_id';
    public const CUSTOM_HEADERS = 'agent_engine_custom_headers';
    public const TIMEOUT_SECONDS = 'agent_engine_timeout_seconds';
    public const TEST_SESSION = 'agent_engine_test_session';
    public const CUSTOM_OUTPUT = 'agent_engine_custom_output';

    /**
     * @param array<string, mixed> $options
     */
    public function from(array $options): InvocationOptions
    {
        return new InvocationOptions(
            customHeaders: $this->customHeaders($options),
            sessionId: $this->sessionId($options),
            timeoutSeconds: $this->timeoutSeconds($options),
            testSession: $this->boolean($options, self::TEST_SESSION),
            customOutput: $this->boolean($options, self::CUSTOM_OUTPUT),
        );
    }

    /**
     * @param array<string, mixed> $options
     */
    private function customHeaders(array $options): CustomHeaders
    {
        $headers = $options[self::CUSTOM_HEADERS] ?? [];

        if (!is_array($headers)) {
            throw new InvalidArgumentException(sprintf('Option "%s" must be an array of header names to values.', self::CUSTOM_HEADERS));
        }

        $mapped = [];

        foreach ($headers as $name => $value) {
            if (!is_string($name) || !is_string($value)) {
                throw new InvalidArgumentException(sprintf('Option "%s" must map string header names to string values.', self::CUSTOM_HEADERS));
            }

            $mapped[$name] = $value;
        }

        return CustomHeaders::fromArray($mapped);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function sessionId(array $options): ?SessionId
    {
        $sessionId = $options[self::SESSION_ID] ?? null;

        if ($sessionId === null) {
            return null;
        }

        if (!is_string($sessionId)) {
            throw new InvalidArgumentException(sprintf('Option "%s" must be a string.', self::SESSION_ID));
        }

        return SessionId::fromString($sessionId);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function timeoutSeconds(array $options): ?int
    {
        $timeout = $options[self::TIMEOUT_SECONDS] ?? null;

        if ($timeout === null) {
            return null;
        }

        if (!is_int($timeout)) {
            throw new InvalidArgumentException(sprintf('Option "%s" must be an integer.', self::TIMEOUT_SECONDS));
        }

        return $timeout;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function boolean(array $options, string $key): bool
    {
        $value = $options[$key] ?? false;

        if (!is_bool($value)) {
            throw new InvalidArgumentException(sprintf('Option "%s" must be a boolean.', $key));
        }

        return $value;
    }
}
