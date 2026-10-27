<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Tests\Unit;

use AgentEngine\SymfonyAi\Platform\PayloadMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class PayloadMessageTest extends TestCase
{
    #[Test]
    public function it_accepts_a_string_payload(): void
    {
        self::assertSame('Hello', PayloadMessage::from('Hello'));
    }

    #[Test]
    public function it_takes_the_last_user_message(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'user', 'content' => 'First'],
                ['role' => 'assistant', 'content' => 'Reply'],
                ['role' => 'user', 'content' => 'Second'],
            ],
        ];

        self::assertSame('Second', PayloadMessage::from($payload));
    }

    #[Test]
    public function it_joins_multipart_text_content(): void
    {
        $payload = [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => 'Hello '],
                        ['type' => 'image', 'url' => 'https://example.test/image.png'],
                        ['type' => 'text', 'text' => 'there'],
                    ],
                ],
            ],
        ];

        self::assertSame('Hello there', PayloadMessage::from($payload));
    }

    #[Test]
    public function it_falls_back_to_the_last_message_when_no_user_message_exists(): void
    {
        $payload = [
            'messages' => [
                ['role' => 'system', 'content' => 'Be concise.'],
                ['role' => 'assistant', 'content' => 'Ready.'],
            ],
        ];

        self::assertSame('Ready.', PayloadMessage::from($payload));
    }

    #[Test]
    public function it_falls_back_to_json_when_there_are_no_messages(): void
    {
        self::assertSame('{"input":"Hello"}', PayloadMessage::from(['input' => 'Hello']));
    }

    #[Test]
    public function it_rejects_an_empty_message(): void
    {
        $this->expectException(InvalidArgumentException::class);
        PayloadMessage::from(['messages' => [['role' => 'user', 'content' => '   ']]]);
    }
}
