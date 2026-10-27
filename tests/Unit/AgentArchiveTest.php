<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Deployment\AgentArchive;
use AgentEngine\Deployment\DeployRequest;
use AgentEngine\Exception\ArchiveFailed;
use AgentEngine\Tests\Support\TempDirectory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AgentArchiveTest extends TestCase
{
    #[Test]
    public function it_archives_the_agent_and_skips_default_excludes(): void
    {
        $directory = TempDirectory::create();

        try {
            TempDirectory::write($directory, 'agent.yaml', "name: support-agent\nentrypoint: src/main.py\n");
            TempDirectory::write($directory, 'src/main.py', 'print("hello")');
            TempDirectory::write($directory, '.env', 'SECRET=value');
            TempDirectory::write($directory, '.git/config', '[core]');
            TempDirectory::write($directory, 'node_modules/pkg/index.js', 'module.exports = {};');

            $archive = (new AgentArchive())->create(DeployRequest::forDirectory($directory));

            try {
                self::assertFileExists($archive->path);
                self::assertGreaterThan(0, $archive->bytes);

                $phar = new \PharData($archive->path);

                self::assertTrue(isset($phar['agent.yaml']));
                self::assertTrue(isset($phar['src/main.py']));
                self::assertFalse(isset($phar['.env']));
                self::assertFalse(isset($phar['.git/config']));
                self::assertFalse(isset($phar['node_modules/pkg/index.js']));
            } finally {
                $archive->delete();
            }
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_applies_additional_excludes(): void
    {
        $directory = TempDirectory::create();

        try {
            TempDirectory::write($directory, 'agent.yaml', "name: support-agent\n");
            TempDirectory::write($directory, 'keep.txt', 'keep');
            TempDirectory::write($directory, 'debug.log', 'drop');

            $request = DeployRequest::forDirectory($directory)->withAdditionalExcludes(['*.log']);
            $archive = (new AgentArchive())->create($request);

            try {
                $phar = new \PharData($archive->path);

                self::assertTrue(isset($phar['keep.txt']));
                self::assertFalse(isset($phar['debug.log']));
            } finally {
                $archive->delete();
            }
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_requires_an_agent_manifest(): void
    {
        $directory = TempDirectory::create();

        try {
            $this->expectException(ArchiveFailed::class);

            (new AgentArchive())->create(DeployRequest::forDirectory($directory));
        } finally {
            TempDirectory::remove($directory);
        }
    }
}
