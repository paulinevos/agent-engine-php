<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Memory;

use AgentEngine\AgentEngineClient;
use Symfony\AI\Agent\Input;
use Symfony\AI\Agent\Memory\Memory;
use Symfony\AI\Agent\Memory\MemoryProviderInterface;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\UserMessage;

final readonly class AgentEngineMemoryProvider implements MemoryProviderInterface
{
    public function __construct(
        private AgentEngineClient $client,
        private MemoryOptions $options,
    ) {
    }

    /**
     * @return list<Memory>
     */
    public function load(Input $input): array
    {
        $query = $this->queryText($input);

        if ('' === $query) {
            return [];
        }

        $context = $this->client->memory->buildContext($this->options->contextQuery($query, $input));
        $formatted = $context->optionalString('formatted_context') ?? '';

        if ('' === $formatted) {
            return [];
        }

        return [new Memory($formatted)];
    }

    private function queryText(Input $input): string
    {
        $messages = $input->getMessageBag()->getMessages();
        $last = end($messages);

        if (!$last instanceof UserMessage) {
            return '';
        }

        foreach ($last->getContent() as $content) {
            if ($content instanceof Text) {
                return $content->getText();
            }
        }

        return '';
    }
}
