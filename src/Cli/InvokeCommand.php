<?php

declare(strict_types=1);

namespace AgentEngine\Cli;

use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\OAuthFailure;
use AgentEngine\Exception\PermissionDenied;
use AgentEngine\Exception\UnexpectedResponse;
use AgentEngine\Invocation\InvocationOptions;
use AgentEngine\Invocation\InvokeRequest;
use AgentEngine\Invocation\InvokeResult;
use AgentEngine\Invocation\StreamChunkType;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\CustomHeaders;
use AgentEngine\Value\SessionId;
use AgentEngine\Value\UserId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'invoke', description: 'Invoke a deployed agent and print its response')]
final class InvokeCommand extends Command
{
    protected function configure(): void
    {
        ConnectionOptions::addOptions($this);

        $this
            ->addArgument('message', InputArgument::OPTIONAL, 'Message to send; omit or use - to read stdin')
            ->addOption('session', null, InputOption::VALUE_REQUIRED, 'Session ID to continue')
            ->addOption('user-id', null, InputOption::VALUE_REQUIRED, 'User ID forwarded to the agent')
            ->addOption('payload', null, InputOption::VALUE_REQUIRED, 'JSON payload forwarded to the agent')
            ->addOption('header', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Custom header as "Name: value"')
            ->addOption('stream', null, InputOption::VALUE_NONE, 'Stream the response')
            ->addOption('invoke-timeout', null, InputOption::VALUE_REQUIRED, 'Platform invoke timeout in seconds (1-120)')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Print JSON to stdout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $json = (bool) $input->getOption('json');

        try {
            $connection = ConnectionOptions::fromInput($input, $output);
            $request = $this->request($input);
            $options = $this->options($input);
            $client = $connection->client();

            if ((bool) $input->getOption('stream')) {
                return $this->stream($output, $client, $connection, $request, $options, $json);
            }

            $result = $client->invocations->invoke($connection->workspaceId, $request, $options);
            $this->render($output, $result, $json);

            return $result->success ? Command::SUCCESS : Command::FAILURE;
        } catch (AuthenticationRejected|PermissionDenied|OAuthFailure $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, 3);
        } catch (\InvalidArgumentException|UnexpectedResponse $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, Command::INVALID);
        } catch (\Throwable $failure) {
            return CliOutput::error($output, $failure->getMessage(), $json, Command::FAILURE);
        }
    }

    private function stream(
        OutputInterface $output,
        \AgentEngine\AgentEngineClient $client,
        ConnectionOptions $connection,
        InvokeRequest $request,
        InvocationOptions $options,
        bool $json,
    ): int {
        $stream = $client->invocations->stream($connection->workspaceId, $request, $options);

        foreach ($stream as $event) {
            if ($event->type === StreamChunkType::Error) {
                return CliOutput::error($output, $event->errorMessage(), $json, Command::FAILURE);
            }

            if ($json) {
                $output->writeln((string) json_encode([
                    'type' => $event->type->value,
                    'content' => $event->content,
                    'execution_id' => $event->executionId?->value,
                ], JSON_UNESCAPED_SLASHES));

                continue;
            }

            if ($event->isText()) {
                $output->write($event->textDelta());
            }
        }

        if (!$json) {
            $output->writeln('');
        }

        if ($stream->sessionId !== null) {
            CliOutput::errorOutput($output)->writeln('session: ' . $stream->sessionId->value);
        }

        return Command::SUCCESS;
    }

    private function render(OutputInterface $output, InvokeResult $result, bool $json): void
    {
        if ($json) {
            $output->writeln((string) json_encode([
                'success' => $result->success,
                'response' => $result->response,
                'execution_id' => $result->executionId->value,
                'status' => $result->rawStatus,
                'session_id' => $result->sessionId?->value,
                'suspend_reason' => $result->suspendReason,
                'suspend_context' => $result->suspendContext?->toArray(),
                'error' => $result->error,
                'error_code' => $result->errorCode,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return;
        }

        $output->writeln($result->text());

        $errorOutput = CliOutput::errorOutput($output);
        $errorOutput->writeln('execution: ' . $result->executionId->value);

        if ($result->sessionId !== null) {
            $errorOutput->writeln('session: ' . $result->sessionId->value);
        }

        if ($result->requiresReview()) {
            $errorOutput->writeln('suspended for review: ' . ($result->suspendReason ?? 'unknown'));
        }

        if (!$result->success) {
            $errorOutput->writeln('error: ' . ($result->error ?? 'unknown'));
        }
    }

    private function request(InputInterface $input): InvokeRequest
    {
        $request = InvokeRequest::message($this->message($input));
        $payload = $this->stringOption($input, 'payload');

        if ($payload !== null) {
            $request = $request->withPayload(JsonObject::fromJson($payload));
        }

        $userId = $this->stringOption($input, 'user-id');

        if ($userId !== null) {
            $request = $request->withUserId(UserId::fromString($userId));
        }

        return $request;
    }

    private function message(InputInterface $input): string
    {
        $message = $input->getArgument('message');

        if (is_string($message) && $message !== '' && $message !== '-') {
            return $message;
        }

        $stdin = stream_get_contents(STDIN);

        if (!is_string($stdin) || trim($stdin) === '') {
            throw new \InvalidArgumentException('Provide a message argument or pipe one on stdin.');
        }

        return trim($stdin);
    }

    private function options(InputInterface $input): InvocationOptions
    {
        $headers = [];

        foreach ((array) $input->getOption('header') as $header) {
            if (!is_string($header)) {
                continue;
            }

            $colon = strpos($header, ':');

            if ($colon === false) {
                throw new \InvalidArgumentException('Custom headers must use the form "Name: value".');
            }

            $headers[trim(substr($header, 0, $colon))] = trim(substr($header, $colon + 1));
        }

        $session = $this->stringOption($input, 'session');
        $timeout = $input->getOption('invoke-timeout');

        return new InvocationOptions(
            customHeaders: CustomHeaders::fromArray($headers),
            sessionId: $session === null ? null : SessionId::fromString($session),
            timeoutSeconds: is_numeric($timeout) ? (int) $timeout : null,
        );
    }

    private function stringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
