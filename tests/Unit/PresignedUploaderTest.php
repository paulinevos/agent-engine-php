<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Deployment\Archive;
use AgentEngine\Exception\UploadFailed;
use AgentEngine\Http\PresignedUploader;
use AgentEngine\Tests\Support\FakeTransport;
use AgentEngine\Tests\Support\TempDirectory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PresignedUploaderTest extends TestCase
{
    #[Test]
    public function it_uploads_without_an_authorization_header(): void
    {
        $directory = TempDirectory::create();
        $path = $directory . '/agent.tar.gz';
        file_put_contents($path, 'archive-bytes');

        try {
            $transport = new FakeTransport(new Response(200, [], ''));
            $uploader = new PresignedUploader($transport);
            $uploader->upload('https://upload.test/bld_1', new Archive($path, filesize($path) ?: 0));

            $request = $transport->requests[0];

            self::assertSame('PUT', $request->getMethod());
            self::assertSame('https://upload.test/bld_1', (string) $request->getUri());
            self::assertSame('', $request->getHeaderLine('Authorization'));
            self::assertSame('application/gzip', $request->getHeaderLine('Content-Type'));
            self::assertSame('archive-bytes', (string) $request->getBody());
        } finally {
            TempDirectory::remove($directory);
        }
    }

    #[Test]
    public function it_surfaces_a_failed_upload(): void
    {
        $directory = TempDirectory::create();
        $path = $directory . '/agent.tar.gz';
        file_put_contents($path, 'archive-bytes');

        try {
            $uploader = new PresignedUploader(new FakeTransport(new Response(403, [], '')));

            $this->expectException(UploadFailed::class);

            $uploader->upload('https://upload.test/bld_1', new Archive($path, 13));
        } finally {
            TempDirectory::remove($directory);
        }
    }
}
