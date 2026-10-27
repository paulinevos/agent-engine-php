<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

use AgentEngine\AgentEngineClient;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

final readonly class ConnectionOptions
{
    public function __construct(
        public BaseUrl $baseUrl,
        public ProjectId $projectId,
        public WorkspaceId $workspaceId,
        public ClientCredentials $credentials,
    ) {
    }

    public static function addOptions(Command $command): void
    {
        $command
            ->addOption('base-url', null, InputOption::VALUE_REQUIRED, 'Agent Engine base URL (or AGENT_ENGINE_BASE_URL)')
            ->addOption('project', null, InputOption::VALUE_REQUIRED, 'Project ID (or AGENT_ENGINE_PROJECT_ID)')
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Workspace ID (or AGENT_ENGINE_WORKSPACE_ID)')
            ->addOption('client-id', null, InputOption::VALUE_REQUIRED, 'Service account client ID (or AGENT_ENGINE_CLIENT_ID)')
            ->addOption('client-secret', null, InputOption::VALUE_REQUIRED, 'Service account client secret (or AGENT_ENGINE_CLIENT_SECRET)');
    }

    public static function fromInput(InputInterface $input, OutputInterface $output): self
    {
        return new self(
            baseUrl: BaseUrl::fromString(self::required(
                $input,
                'base-url',
                'AGENT_ENGINE_BASE_URL',
                'https://agentengine.mongodb.com',
            )),
            projectId: ProjectId::fromString(self::required($input, 'project', 'AGENT_ENGINE_PROJECT_ID')),
            workspaceId: WorkspaceId::fromString(self::required($input, 'workspace', 'AGENT_ENGINE_WORKSPACE_ID')),
            credentials: ClientCredentials::fromStrings(
                self::required($input, 'client-id', 'AGENT_ENGINE_CLIENT_ID'),
                self::secret($input, $output),
            ),
        );
    }

    public function client(): AgentEngineClient
    {
        return AgentEngineClient::forServiceAccount(
            $this->baseUrl,
            $this->projectId,
            $this->credentials,
        );
    }

    private static function required(
        InputInterface $input,
        string $option,
        string $environment,
        ?string $default = null,
    ): string {
        $value = $input->getOption($option);

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $environmentValue = getenv($environment);

        if (is_string($environmentValue) && $environmentValue !== '') {
            return $environmentValue;
        }

        if ($default !== null) {
            return $default;
        }

        throw new \InvalidArgumentException(sprintf(
            'Provide --%s or set %s.',
            $option,
            $environment,
        ));
    }

    private static function secret(InputInterface $input, OutputInterface $output): string
    {
        $value = $input->getOption('client-secret');

        if (is_string($value) && $value !== '') {
            return $value;
        }

        $environmentValue = getenv('AGENT_ENGINE_CLIENT_SECRET');

        if (is_string($environmentValue) && $environmentValue !== '') {
            return $environmentValue;
        }

        if (!$input->isInteractive()) {
            throw new \InvalidArgumentException(
                'Provide --client-secret or set AGENT_ENGINE_CLIENT_SECRET.',
            );
        }

        $answer = (new QuestionHelper())->ask(
            $input,
            $output,
            (new Question('Client secret: '))->setHidden(true),
        );

        if (!is_string($answer) || $answer === '') {
            throw new \InvalidArgumentException('A client secret is required.');
        }

        return $answer;
    }
}
