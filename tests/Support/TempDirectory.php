<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Support;

final class TempDirectory
{
    public static function create(): string
    {
        $path = sys_get_temp_dir() . '/agent-engine-' . bin2hex(random_bytes(6));

        if (!mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException('Could not create a temporary directory.');
        }

        return $path;
    }

    public static function write(string $directory, string $relativePath, string $contents): void
    {
        $path = $directory . '/' . $relativePath;
        $parent = dirname($path);

        if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
            throw new \RuntimeException('Could not create ' . $parent);
        }

        file_put_contents($path, $contents);
    }

    public static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if (!$file instanceof \SplFileInfo) {
                continue;
            }

            if ($file->isDir()) {
                rmdir($file->getPathname());
                continue;
            }

            unlink($file->getPathname());
        }

        rmdir($directory);
    }
}
