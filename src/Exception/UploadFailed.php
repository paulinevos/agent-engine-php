<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class UploadFailed extends AgentEngineException
{
    public static function unreadable(string $path): self
    {
        return new self(sprintf('Could not read the archive "%s" for upload.', $path));
    }

    public static function forStatus(int $status): self
    {
        return new self(sprintf('The presigned archive upload failed with HTTP %d.', $status));
    }
}
