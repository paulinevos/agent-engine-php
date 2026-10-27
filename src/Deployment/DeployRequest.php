<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Exception\InvalidArgument;

final readonly class DeployRequest
{
    public const DEFAULT_EXCLUDES = [
        '.git',
        '.venv',
        '__pycache__',
        '.agentengine',
        '.env',
        'node_modules',
        'vendor',
        'var',
        '.phpunit.cache',
        '.idea',
        '.DS_Store',
    ];

    public string $subdirectory;

    /**
     * @param list<string> $excludes
     */
    public function __construct(
        public string $directory,
        public ?string $label = null,
        public ?string $commitSha = null,
        public ?string $branch = null,
        public bool $dirty = false,
        string $subdirectory = '',
        public bool $autoDeploy = false,
        public array $excludes = self::DEFAULT_EXCLUDES,
        public bool $wait = true,
        public int $buildTimeoutSeconds = 1800,
        public int $deploymentTimeoutSeconds = 600,
        public int $pollIntervalMilliseconds = 5000,
    ) {
        if (!is_dir($directory)) {
            throw InvalidArgument::directoryNotFound($directory);
        }

        if ($buildTimeoutSeconds < 1) {
            throw InvalidArgument::belowMinimum('Build timeout in seconds', $buildTimeoutSeconds, 1);
        }

        if ($deploymentTimeoutSeconds < 1) {
            throw InvalidArgument::belowMinimum('Deployment timeout in seconds', $deploymentTimeoutSeconds, 1);
        }

        if ($pollIntervalMilliseconds < 1) {
            throw InvalidArgument::belowMinimum('Poll interval in milliseconds', $pollIntervalMilliseconds, 1);
        }

        $this->subdirectory = trim($subdirectory, '/\\');
    }

    public static function forDirectory(string $directory): self
    {
        return new self($directory);
    }

    public function withLabel(string $label): self
    {
        return $this->copy(['label' => $label]);
    }

    public function withGitInfo(?string $commitSha, ?string $branch, bool $dirty): self
    {
        return $this->copy([
            'commitSha' => $commitSha,
            'branch' => $branch,
            'dirty' => $dirty,
        ]);
    }

    public function withSubdirectory(string $subdirectory): self
    {
        return $this->copy(['subdirectory' => $subdirectory]);
    }

    public function withAutoDeploy(): self
    {
        return $this->copy(['autoDeploy' => true]);
    }

    public function withoutWaiting(): self
    {
        return $this->copy(['wait' => false]);
    }

    /**
     * @param list<string> $excludes
     */
    public function withAdditionalExcludes(array $excludes): self
    {
        return $this->copy([
            'excludes' => array_values(array_unique([...$this->excludes, ...$excludes])),
        ]);
    }

    public function withTimeouts(int $buildTimeoutSeconds, int $deploymentTimeoutSeconds): self
    {
        return $this->copy([
            'buildTimeoutSeconds' => $buildTimeoutSeconds,
            'deploymentTimeoutSeconds' => $deploymentTimeoutSeconds,
        ]);
    }

    public function withPollInterval(int $pollIntervalMilliseconds): self
    {
        return $this->copy(['pollIntervalMilliseconds' => $pollIntervalMilliseconds]);
    }

    public function agentManifest(): string
    {
        return rtrim($this->directory, '/\\') . '/agent.yaml';
    }

    /**
     * @param array<string, mixed> $changes
     */
    private function copy(array $changes): self
    {
        return new self(
            directory: $changes['directory'] ?? $this->directory,
            label: $changes['label'] ?? $this->label,
            commitSha: $changes['commitSha'] ?? $this->commitSha,
            branch: $changes['branch'] ?? $this->branch,
            dirty: $changes['dirty'] ?? $this->dirty,
            subdirectory: $changes['subdirectory'] ?? $this->subdirectory,
            autoDeploy: $changes['autoDeploy'] ?? $this->autoDeploy,
            excludes: $changes['excludes'] ?? $this->excludes,
            wait: $changes['wait'] ?? $this->wait,
            buildTimeoutSeconds: $changes['buildTimeoutSeconds'] ?? $this->buildTimeoutSeconds,
            deploymentTimeoutSeconds: $changes['deploymentTimeoutSeconds'] ?? $this->deploymentTimeoutSeconds,
            pollIntervalMilliseconds: $changes['pollIntervalMilliseconds'] ?? $this->pollIntervalMilliseconds,
        );
    }
}
