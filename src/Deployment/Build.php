<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BuildId;

final readonly class Build
{
    public function __construct(
        public BuildId $buildId,
        public BuildStatus $status,
        public string $rawStatus,
        public ?string $executorType = null,
        public ?string $imageUri = null,
        public ?string $lockfileMode = null,
        public ?string $errorMessage = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        $status = $json->optionalString('status') ?? 'unknown';

        return new self(
            buildId: BuildId::fromString($json->string('build_id')),
            status: BuildStatus::fromApi($status),
            rawStatus: $status,
            executorType: $json->optionalString('executor_type'),
            imageUri: $json->optionalString('image_uri'),
            lockfileMode: $json->optionalString('lockfile_mode'),
            errorMessage: $json->optionalString('error_message'),
        );
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }
}
