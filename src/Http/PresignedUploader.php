<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Deployment\Archive;
use AgentEngine\Exception\UploadFailed;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Uploads an archive to a presigned URL. The URL is the credential, so this
 * deliberately does not reuse ApiRequestSender: that would add the bearer
 * token, and the docs require no Authorization header on this request.
 */
final readonly class PresignedUploader
{
    public function __construct(
        private Transport $transport,
        private RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private StreamFactoryInterface $streamFactory = new Psr17Factory(),
    ) {
    }

    public function upload(string $url, Archive $archive): void
    {
        if (!is_readable($archive->path)) {
            throw UploadFailed::unreadable($archive->path);
        }

        $request = $this->requestFactory
            ->createRequest('PUT', $url)
            ->withHeader('Content-Type', 'application/gzip')
            ->withHeader('Content-Length', (string) $archive->bytes)
            ->withBody($this->streamFactory->createStreamFromFile($archive->path, 'rb'));

        $response = $this->transport->send($request);

        if ($response->getStatusCode() >= 300) {
            throw UploadFailed::forStatus($response->getStatusCode());
        }
    }
}
