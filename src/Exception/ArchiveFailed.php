<?php

declare(strict_types=1);

namespace AgentEngine\Exception;

final class ArchiveFailed extends AgentEngineException
{
    public static function missingManifest(string $directory): self
    {
        return new self(sprintf(
            'No agent.yaml found in %s. Agent Engine deploys the agent directory root.',
            $directory,
        ));
    }

    public static function unreadable(string $path): self
    {
        return new self(sprintf('Could not read "%s" while building the archive.', $path));
    }

    public static function pathTooLong(string $path): self
    {
        return new self(sprintf(
            'Path "%s" is too long for a ustar archive entry; shorten it or move the agent higher in the tree.',
            $path,
        ));
    }

    public static function temporaryFile(): self
    {
        return new self('Could not create a temporary archive file.');
    }

    public static function compressionFailed(): self
    {
        return new self('Could not compress the agent archive.');
    }
}
