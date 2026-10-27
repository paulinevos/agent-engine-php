<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Exception\Conflict;
use AgentEngine\Exception\DeployFailed;
use AgentEngine\Exception\ResourceNotFound;
use AgentEngine\Http\PresignedUploader;
use AgentEngine\Resource\Builds;
use AgentEngine\Resource\Deployments;
use AgentEngine\Value\BuildId;
use AgentEngine\Value\WorkspaceId;

final readonly class AgentDeployer
{
    public function __construct(
        private Builds $builds,
        private Deployments $deployments,
        private PresignedUploader $uploader,
        private AgentArchive $archive,
    ) {
    }

    public function deploy(
        WorkspaceId $workspace,
        DeployRequest $request,
        ?DeployListener $listener = null,
    ): DeployResult {
        $this->notify($listener, DeployStage::Archiving, 'Archiving ' . $request->directory);
        $archive = $this->archive->create($request);

        try {
            return $this->run($workspace, $request, $archive, $listener);
        } finally {
            $archive->delete();
        }
    }

    private function run(
        WorkspaceId $workspace,
        DeployRequest $request,
        Archive $archive,
        ?DeployListener $listener,
    ): DeployResult {
        $init = $this->builds->archiveInit($workspace, $request);
        $this->notify(
            $listener,
            DeployStage::ArchiveInitialized,
            'Build ' . $init->buildId->value . ' initialized',
            ['build_id' => $init->buildId->value],
        );

        $this->uploader->upload($init->uploadUrl, $archive);
        $this->notify(
            $listener,
            DeployStage::Uploaded,
            sprintf('Uploaded %d bytes', $archive->bytes),
        );

        $build = $this->startBuild($workspace, $init);
        $this->notify(
            $listener,
            DeployStage::BuildRequested,
            sprintf('Build %s is %s', $build->buildId->value, $build->rawStatus),
            ['build_id' => $build->buildId->value],
        );

        if (!$request->wait) {
            return new DeployResult($build, $init);
        }

        $build = $this->awaitBuild($workspace, $request, $build, $listener);
        $deployment = $this->deployBuild($workspace, $request, $build, $listener);

        $this->notify(
            $listener,
            DeployStage::Completed,
            sprintf('Deployment %s is %s', $deployment->deploymentId->value, $deployment->rawStatus),
            ['deployment_id' => $deployment->deploymentId->value],
        );

        return new DeployResult($build, $init, $deployment);
    }

    private function startBuild(WorkspaceId $workspace, ArchiveInit $init): Build
    {
        try {
            return $this->builds->start($workspace, $init->buildId);
        } catch (Conflict $failure) {
            $existing = $this->existingBuildId($failure);

            if ($existing === null) {
                throw $failure;
            }

            return $this->builds->get($workspace, $existing);
        }
    }

    private function existingBuildId(Conflict $failure): ?BuildId
    {
        $details = $failure->details->body->optionalObject('details');
        $existing = $details?->optionalString('existing_build_id');

        return $existing === null ? null : BuildId::fromString($existing);
    }

    private function awaitBuild(
        WorkspaceId $workspace,
        DeployRequest $request,
        Build $build,
        ?DeployListener $listener,
    ): Build {
        $deadline = microtime(true) + $request->buildTimeoutSeconds;
        $current = $build;

        while (!$current->isTerminal()) {
            $this->notify(
                $listener,
                DeployStage::BuildStatus,
                sprintf('Build %s is %s', $current->buildId->value, $current->rawStatus),
                ['build_id' => $current->buildId->value, 'status' => $current->rawStatus],
            );
            $this->guardDeadline($deadline, 'build', $request->buildTimeoutSeconds);
            usleep($request->pollIntervalMilliseconds * 1000);
            $current = $this->builds->get($workspace, $current->buildId);
        }

        return $current;
    }

    private function deployBuild(
        WorkspaceId $workspace,
        DeployRequest $request,
        Build $build,
        ?DeployListener $listener,
    ): Deployment {
        if (!$build->isSuccessful()) {
            throw DeployFailed::build($build);
        }

        if (!$request->autoDeploy) {
            $this->requestDeployment($workspace, $build, $listener);
        }

        return $this->awaitDeployment($workspace, $request, $listener);
    }

    private function requestDeployment(
        WorkspaceId $workspace,
        Build $build,
        ?DeployListener $listener,
    ): void {
        try {
            $accepted = $this->deployments->create($workspace, $build->buildId);
            $this->notify(
                $listener,
                DeployStage::DeploymentRequested,
                'Deployment ' . $accepted->deploymentId->value . ' requested',
                ['deployment_id' => $accepted->deploymentId->value],
            );
        } catch (Conflict $failure) {
            if (!$this->isDeployInProgress($failure)) {
                throw $failure;
            }

            $this->notify($listener, DeployStage::DeploymentRequested, 'A deployment is already in progress; waiting for it');
        }
    }

    private function isDeployInProgress(Conflict $failure): bool
    {
        return str_contains(strtoupper($failure->details->errorCode ?? ''), 'DEPLOY_IN_PROGRESS');
    }

    private function awaitDeployment(
        WorkspaceId $workspace,
        DeployRequest $request,
        ?DeployListener $listener,
    ): Deployment {
        $deadline = microtime(true) + $request->deploymentTimeoutSeconds;
        $current = $this->readDeployment($workspace);

        while (!$this->settled($current)) {
            $this->notify(
                $listener,
                DeployStage::DeploymentStatus,
                $this->deploymentMessage($current),
                $current === null
                    ? []
                    : ['deployment_id' => $current->deploymentId->value, 'status' => $current->rawStatus],
            );
            $this->guardDeadline($deadline, 'deployment', $request->deploymentTimeoutSeconds);
            usleep($request->pollIntervalMilliseconds * 1000);
            $current = $this->readDeployment($workspace);
        }

        if ($current === null) {
            throw DeployFailed::timedOut('deployment', $request->deploymentTimeoutSeconds);
        }

        if (!$current->isHealthy()) {
            throw DeployFailed::deployment($current);
        }

        return $current;
    }

    private function readDeployment(WorkspaceId $workspace): ?Deployment
    {
        try {
            return $this->deployments->current($workspace);
        } catch (ResourceNotFound) {
            return null;
        }
    }

    private function settled(?Deployment $deployment): bool
    {
        if ($deployment === null) {
            return false;
        }

        return $deployment->status->isFailed() || $deployment->isHealthy();
    }

    private function deploymentMessage(?Deployment $deployment): string
    {
        if ($deployment === null) {
            return 'Waiting for the deployment to appear';
        }

        return sprintf(
            'Deployment %s is %s',
            $deployment->deploymentId->value,
            $deployment->rawStatus,
        );
    }

    private function guardDeadline(float $deadline, string $stage, int $timeoutSeconds): void
    {
        if (microtime(true) >= $deadline) {
            throw DeployFailed::timedOut($stage, $timeoutSeconds);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function notify(
        ?DeployListener $listener,
        DeployStage $stage,
        string $message,
        array $context = [],
    ): void {
        $listener?->onDeployProgress(new DeployProgress($stage, $message, $context));
    }
}
