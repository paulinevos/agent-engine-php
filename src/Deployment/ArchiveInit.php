<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BuildId;

final readonly class ArchiveInit
{
    public function __construct(
        public BuildId $buildId,
        public string $uploadUrl,
        public ?string $uploadExpiresAt = null,
        public string $sourceType = 'archive',
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            buildId: BuildId::fromString($json->string('build_id')),
            uploadUrl: $json->string('upload_url'),
            uploadExpiresAt: $json->optionalString('upload_expires_at'),
            sourceType: $json->optionalString('source_type') ?? 'archive',
        );
    }
}
