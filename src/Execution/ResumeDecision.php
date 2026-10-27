<?php

declare(strict_types=1);

namespace AgentEngine\Execution;

use AgentEngine\Exception\InvalidArgument;
use AgentEngine\Json\JsonObject;

final readonly class ResumeDecision
{
    private function __construct(
        public string $decision,
        public ?string $reviewerNotes = null,
        public ?int $suspendGeneration = null,
    ) {
        if ($decision === '') {
            throw InvalidArgument::emptyIdentifier('Review decision');
        }

        if ($suspendGeneration !== null && $suspendGeneration < 0) {
            throw InvalidArgument::negativeInt('Suspend generation', $suspendGeneration);
        }
    }

    public static function of(string $decision, ?string $reviewerNotes = null): self
    {
        return new self($decision, $reviewerNotes);
    }

    public static function approve(?string $reviewerNotes = null): self
    {
        return new self('approve', $reviewerNotes);
    }

    public static function reject(?string $reviewerNotes = null): self
    {
        return new self('reject', $reviewerNotes);
    }

    public function withSuspendGeneration(int $suspendGeneration): self
    {
        return new self($this->decision, $this->reviewerNotes, $suspendGeneration);
    }

    public function toJson(): JsonObject
    {
        $body = ['decision' => $this->decision];

        if ($this->reviewerNotes !== null) {
            $body['reviewer_notes'] = $this->reviewerNotes;
        }

        if ($this->suspendGeneration !== null) {
            $body['suspend_generation'] = $this->suspendGeneration;
        }

        return JsonObject::fromArray($body);
    }
}
