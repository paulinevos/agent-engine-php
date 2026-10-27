<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Platform;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Reduces a Symfony AI request payload to the single message an Agent Engine
 * invocation accepts. Conversation history stays server-side in a session.
 */
final readonly class PayloadMessage
{
    /**
     * @param array<string|int, mixed> $payload
     */
    public static function from(array|string $payload): string
    {
        if (is_string($payload)) {
            return self::guard($payload);
        }

        $messages = self::messages($payload);

        foreach (array_reverse($messages) as $message) {
            if ('user' !== self::role($message)) {
                continue;
            }

            return self::guard(self::content($message));
        }

        return self::guard(self::lastContent($messages) ?? self::encode($payload));
    }

    /**
     * @param array<string|int, mixed> $payload
     *
     * @return list<array<string|int, mixed>>
     */
    private static function messages(array $payload): array
    {
        $messages = $payload['messages'] ?? null;

        if (!is_array($messages)) {
            return [];
        }

        return array_values(array_filter($messages, 'is_array'));
    }

    /**
     * @param array<string|int, mixed> $message
     */
    private static function role(array $message): string
    {
        $role = $message['role'] ?? '';

        return is_string($role) ? $role : '';
    }

    /**
     * @param array<string|int, mixed> $message
     */
    private static function content(array $message): string
    {
        $content = $message['content'] ?? '';

        if (is_string($content)) {
            return $content;
        }

        if (!is_array($content)) {
            return '';
        }

        $texts = [];

        foreach ($content as $part) {
            if (!is_array($part) || !is_string($part['text'] ?? null)) {
                continue;
            }

            $texts[] = $part['text'];
        }

        return implode('', $texts);
    }

    /**
     * @param list<array<string|int, mixed>> $messages
     */
    private static function lastContent(array $messages): ?string
    {
        $last = end($messages);

        return is_array($last) ? self::content($last) : null;
    }

    private static function guard(string $message): string
    {
        if ('' === trim($message)) {
            throw new InvalidArgumentException('The Agent Engine provider received an empty message.');
        }

        return $message;
    }

    /**
     * @param array<string|int, mixed> $payload
     */
    private static function encode(array $payload): string
    {
        return json_encode($payload, JSON_THROW_ON_ERROR);
    }
}
