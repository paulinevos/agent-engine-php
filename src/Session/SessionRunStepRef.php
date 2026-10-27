<?php

declare(strict_types=1);

namespace AgentEngine\Session;

use AgentEngine\Json\JsonObject;

final readonly class SessionRunStepRef
{
    public function __construct(
        public ?string $kind = null,
        public ?string $name = null,
        public ?int $stepNumber = null,
        public ?float $durationMilliseconds = null,
    ) {
    }

    public static function fromJson(JsonObject $json): self
    {
        return new self(
            kind: $json->optionalString('kind'),
            name: $json->optionalString('name'),
            stepNumber: $json->optionalInteger('step_number'),
            durationMilliseconds: (float) ($json->optionalNumber('duration_ms') ?? 0.0),
        );
    }
}
