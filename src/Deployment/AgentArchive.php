<?php

declare(strict_types=1);

namespace AgentEngine\Deployment;

use AgentEngine\Exception\ArchiveFailed;

/**
 * Builds a ustar + gzip archive of the agent directory. Written in PHP so the
 * CLI works in PHP-only images without a tar binary.
 */
final class AgentArchive
{
    public function create(DeployRequest $request): Archive
    {
        if (!is_file($request->agentManifest())) {
            throw ArchiveFailed::missingManifest($request->directory);
        }

        $directory = rtrim($request->directory, '/\\');
        $paths = $this->files($directory, $request->excludes);

        if (!in_array('agent.yaml', $paths, true)) {
            throw ArchiveFailed::missingManifest($directory);
        }

        $archive = gzencode($this->tar($directory, $paths), 9);

        if ($archive === false) {
            throw ArchiveFailed::compressionFailed();
        }

        $path = $this->temporaryPath();
        $written = file_put_contents($path, $archive);

        if ($written === false) {
            @unlink($path);

            throw ArchiveFailed::temporaryFile();
        }

        return new Archive($path, strlen($archive));
    }

    /**
     * @param list<string> $excludes
     *
     * @return list<string>
     */
    private function files(string $directory, array $excludes): array
    {
        $paths = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo || !$file->isFile() || $file->isLink()) {
                continue;
            }

            $relative = $this->relativePath($directory, $file->getPathname());

            if (!$this->excluded($relative, $excludes)) {
                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    private function relativePath(string $directory, string $path): string
    {
        $prefix = str_replace('\\', '/', $directory) . '/';
        $normalised = str_replace('\\', '/', $path);

        if (!str_starts_with($normalised, $prefix)) {
            throw ArchiveFailed::unreadable($path);
        }

        return substr($normalised, strlen($prefix));
    }

    /**
     * @param list<string> $excludes
     */
    private function excluded(string $relative, array $excludes): bool
    {
        return array_reduce(
            $excludes,
            fn (bool $excluded, string $pattern): bool => $excluded || $this->matches($relative, $pattern),
            false,
        );
    }

    private function matches(string $relative, string $exclude): bool
    {
        $pattern = trim($exclude, '/');

        if ($pattern === '') {
            return false;
        }

        $candidates = [$relative, basename($relative), ...explode('/', $relative)];

        foreach ($candidates as $candidate) {
            if (fnmatch($pattern, $candidate)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $paths
     */
    private function tar(string $directory, array $paths): string
    {
        $tar = '';

        foreach ($paths as $relative) {
            $absolute = $directory . '/' . $relative;
            $contents = @file_get_contents($absolute);

            if ($contents === false) {
                throw ArchiveFailed::unreadable($relative);
            }

            $permissions = fileperms($absolute);
            $modified = filemtime($absolute);

            $tar .= $this->header(
                $relative,
                strlen($contents),
                $permissions === false ? 0644 : $permissions & 0777,
                $modified === false ? time() : $modified,
            );
            $tar .= $contents;
            $tar .= str_repeat("\0", $this->padding(strlen($contents)));
        }

        return $tar . str_repeat("\0", 1024);
    }

    private function padding(int $size): int
    {
        return (512 - $size % 512) % 512;
    }

    private function header(string $name, int $size, int $mode, int $modified): string
    {
        [$prefix, $shortName] = $this->splitName($name);

        $header = pack(
            'a100a8a8a8a12a12a8a1a100a6a2a32a32a8a8a155a12',
            $shortName,
            sprintf('%07o', $mode),
            '0000000',
            '0000000',
            sprintf('%011o', $size),
            sprintf('%011o', $modified),
            '        ',
            '0',
            '',
            "ustar\0",
            '00',
            '',
            '',
            '',
            '',
            $prefix,
            '',
        );

        $checksum = array_sum(array_map('ord', str_split($header)));

        return substr_replace($header, sprintf('%06o', $checksum) . "\0 ", 148, 8);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $name): array
    {
        if (strlen($name) <= 100) {
            return ['', $name];
        }

        $slash = strrpos(substr($name, 0, 156), '/');

        if ($slash === false || $slash > 155) {
            throw ArchiveFailed::pathTooLong($name);
        }

        $shortName = substr($name, $slash + 1);

        if (strlen($shortName) > 100) {
            throw ArchiveFailed::pathTooLong($name);
        }

        return [substr($name, 0, $slash), $shortName];
    }

    private function temporaryPath(): string
    {
        $temporary = tempnam(sys_get_temp_dir(), 'agent-engine-');

        if ($temporary === false) {
            throw ArchiveFailed::temporaryFile();
        }

        $path = $temporary . '.tar.gz';

        if (!rename($temporary, $path)) {
            @unlink($temporary);

            throw ArchiveFailed::temporaryFile();
        }

        return $path;
    }
}
