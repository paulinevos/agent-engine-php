<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Exception\ExecutionTimedOut;
use AgentEngine\Execution\CancelResult;
use AgentEngine\Execution\Execution;
use AgentEngine\Execution\ExecutionPage;
use AgentEngine\Execution\ExecutionQuery;
use AgentEngine\Execution\InterruptResult;
use AgentEngine\Execution\ResumeDecision;
use AgentEngine\Execution\ResumeResult;
use AgentEngine\Execution\WaitOptions;
use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\CustomHeaders;
use AgentEngine\Value\ExecutionId;
use AgentEngine\Value\ProjectId;
use AgentEngine\Value\WorkspaceId;

final readonly class Executions
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function list(ExecutionQuery $query = new ExecutionQuery()): ExecutionPage
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->basePath() . '/executions',
            query: $query->query(),
        ));

        return ExecutionPage::fromJson($response->body);
    }

    public function get(WorkspaceId $workspace, ExecutionId $execution): Execution
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Get,
            path: $this->executionPath($execution),
            query: ['workspace_id' => $workspace->value],
        ));

        return Execution::fromJson($response->body->object('execution'));
    }

    public function cancel(WorkspaceId $workspace, ExecutionId $execution): CancelResult
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->executionPath($execution) . '/cancel',
            query: ['workspace_id' => $workspace->value],
        ));

        return CancelResult::fromJson($response->body);
    }

    public function interrupt(
        WorkspaceId $workspace,
        ExecutionId $execution,
        ?int $stepNumber = null,
    ): InterruptResult {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->executionPath($execution) . '/interrupt',
            query: ['workspace_id' => $workspace->value],
            body: $stepNumber === null
                ? new JsonObject()
                : JsonObject::fromArray(['step_number' => $stepNumber]),
        ));

        return InterruptResult::fromJson($response->body);
    }

    public function resume(
        WorkspaceId $workspace,
        ExecutionId $execution,
        ResumeDecision $decision,
        CustomHeaders $customHeaders = new CustomHeaders(),
    ): ResumeResult {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: $this->executionPath($execution) . '/resume',
            query: ['workspace_id' => $workspace->value],
            body: $decision->toJson(),
            headers: $customHeaders->prefixed(),
        ));

        return ResumeResult::fromJson($response->body);
    }

    public function wait(
        WorkspaceId $workspace,
        ExecutionId $execution,
        WaitOptions $options = new WaitOptions(),
    ): Execution {
        $deadline = microtime(true) + $options->timeoutSeconds;
        $current = $this->get($workspace, $execution);

        while (!$current->isTerminal() && !$current->isSuspended()) {
            $this->guardDeadline($execution, $options, $deadline);
            usleep(max(1, $options->pollIntervalMilliseconds) * 1000);
            $current = $this->get($workspace, $execution);
        }

        return $current;
    }

    private function guardDeadline(ExecutionId $execution, WaitOptions $options, float $deadline): void
    {
        if (microtime(true) >= $deadline) {
            throw ExecutionTimedOut::waitingFor($execution, $options->timeoutSeconds);
        }
    }

    private function basePath(): string
    {
        return sprintf('/api/v1/projects/%s', rawurlencode($this->projectId->value));
    }

    private function executionPath(ExecutionId $execution): string
    {
        return $this->basePath() . '/executions/' . rawurlencode($execution->value);
    }
}
