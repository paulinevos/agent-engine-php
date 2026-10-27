<?php

declare(strict_types=1);

namespace AgentEngine\SymfonyAi\Command;

use AgentEngine\AgentEngineClient;
use AgentEngine\Cli\CliOutput;
use AgentEngine\Cli\ConsoleDeployListener;
use AgentEngine\Cli\GitInfo;
use AgentEngine\Deployment\AgentArchive;
use AgentEngine\Deployment\DeployRequest;
use AgentEngine\Deployment\DeployResult;
use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\DeployFailed;
use AgentEngine\Exception\OAuthFailure;
use AgentEngine\Exception\PermissionDenied;
use AgentEngine\Value\WorkspaceId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'agent-engine:deploy', description: 'Archive, build and deploy an agent')]
final class DeployCommand extends Command
{
    public function __construct(
        private readonly AgentEngineClient $client,
        private readonly ?WorkspaceId $defaultWorkspace = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('path', InputArgument::OPTIONAL, 'Agent directory', '.')
            ->addOption('workspace', null, InputOption::VALUE_REQUIRED, 'Workspace ID; overrides the configured default')
            ->addOption('label', null, InputOption::VALUE_REQUIRED, 'Build label')
            ->addOption('commit', null, InputOption::VALUE_REQUIRED, 'Git commit SHA for the build tag')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Git branch name')
            ->addOption('dirty', null, InputOption::VALUE_NONE, 'Mark the working tree as dirty')
            ->addOption('subdirectory', null, InputOption::VALUE_REQUIRED, 'Agent subdirectory in the archive', '')
            ->addOption('auto-deploy', null, InputOption::VALUE_NONE, 'Deploy automatically after a successful build')
            ->addOption('no-wait', null, InputOption::VALUE_NONE, 'Return after the build starts')
            ->addOption('build-timeout', null, InputOption::VALUE_REQUIRED, 'Build timeout in seconds', '1800')
            ->addOption('deploy-timeout', null, InputOption::VALUE_REQUIRED, 'Deployment timeout in seconds', '600')
            ->addOption('poll-interval', null, InputOption::VALUE_REQUIRED, 'Poll interval in milliseconds', '5000')
            ->addOption('exclude', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Additional archive exclusion pattern')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print a JSON result to stdout')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Build and validate the archive without uploading');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');

        try {
            $request = $this->request($input);

            if ((bool) $input->getOption('dry-run')) {
                return $this->dryRun($output, $request, $json);
            }

            $result = $this->client->deployer->deploy(
                $this->workspace($input),
                $request,
                new ConsoleDeployListener($output, $json),
            );

            $this->render($output, $result, $json);

            return Command::SUCCESS;
        } catch (DeployFailed $failure) {
            return CliOutput::error(
                $output,
                $failure->getMessage(),
                $json,
                $this->deployExitCode($failure->stage),
            );
        } catch (AuthenticationRejected|PermissionDenied|OAuthFailure $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, 3);
        } catch (\InvalidArgumentException $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, Command::INVALID);
        } catch (\Throwable $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, Command::FAILURE);
        }
    }

    private function workspace(InputInterface $input): WorkspaceId
    {
        $value = $input->getOption('workspace');

        if (is_string($value) && $value !== '') {
            return WorkspaceId::fromString($value);
        }

        if ($this->defaultWorkspace !== null) {
            return $this->defaultWorkspace;
        }

        throw new \InvalidArgumentException(
            'Set agent_engine.workspace_id or pass --workspace.',
        );
    }

    private function request(InputInterface $input): DeployRequest
    {
        $directory = (string) $input->getArgument('path');
        $git = GitInfo::forDirectory($directory);
        $excludes = array_values(array_filter(
            (array) $input->getOption('exclude'),
            static fn (mixed $exclude): bool => is_string($exclude),
        ));

        return (new DeployRequest(
            directory: $directory,
            label: $this->stringOption($input, 'label'),
            commitSha: $this->stringOption($input, 'commit') ?? $git->commitSha,
            branch: $this->stringOption($input, 'branch') ?? $git->branch,
            dirty: (bool) $input->getOption('dirty') || $git->dirty,
            subdirectory: (string) $input->getOption('subdirectory'),
            autoDeploy: (bool) $input->getOption('auto-deploy'),
            wait: !(bool) $input->getOption('no-wait'),
            buildTimeoutSeconds: (int) $input->getOption('build-timeout'),
            deploymentTimeoutSeconds: (int) $input->getOption('deploy-timeout'),
            pollIntervalMilliseconds: (int) $input->getOption('poll-interval'),
        ))->withAdditionalExcludes($excludes);
    }

    private function dryRun(OutputInterface $output, DeployRequest $request, bool $json): int
    {
        $archive = (new AgentArchive())->create($request);

        try {
            if ($json) {
                $output->writeln((string) json_encode([
                    'bytes' => $archive->bytes,
                    'dry_run' => true,
                ], JSON_UNESCAPED_SLASHES));
            } else {
                $output->writeln(sprintf('Archive ready: %d bytes', $archive->bytes));
            }
        } finally {
            $archive->delete();
        }

        return Command::SUCCESS;
    }

    private function render(OutputInterface $output, DeployResult $result, bool $json): void
    {
        if ($json) {
            $output->writeln((string) json_encode([
                'build_id' => $result->build->buildId->value,
                'build_status' => $result->build->rawStatus,
                'image_uri' => $result->build->imageUri,
                'deployment_id' => $result->deployment?->deploymentId->value,
                'deployment_status' => $result->deployment?->rawStatus,
                'healthy' => $result->isHealthy(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $output->writeln(sprintf('build: %s (%s)', $result->build->buildId->value, $result->build->rawStatus));

        if ($result->build->imageUri !== null) {
            $output->writeln('image: ' . $result->build->imageUri);
        }

        if ($result->deployment !== null) {
            $output->writeln(sprintf(
                'deployment: %s (%s)',
                $result->deployment->deploymentId->value,
                $result->deployment->rawStatus,
            ));
            $output->writeln('healthy: ' . ($result->isHealthy() ? 'yes' : 'no'));
        }
    }

    private function deployExitCode(string $stage): int
    {
        return match ($stage) {
            'build' => 4,
            'deployment' => 5,
            default => Command::FAILURE,
        };
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
