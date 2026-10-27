<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

final readonly class Archive
{
    public function __construct(
        public string $path,
        public int $bytes,
    ) {
    }

    public function delete(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
    }
}
