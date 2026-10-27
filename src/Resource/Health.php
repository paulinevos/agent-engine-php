<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ErrorMapper;
use AgentEngine\Http\Transport;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BaseUrl;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;

final readonly class Health
{
    public function __construct(
        private BaseUrl $baseUrl,
        private Transport $transport,
        private RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private ErrorMapper $errorMapper = new ErrorMapper(),
    ) {
    }

    public function status(): string
    {
        $response = $this->transport->send(
            $this->requestFactory
                ->createRequest('GET', $this->baseUrl->endpoint('/health'))
                ->withHeader('Accept', 'application/json'),
        );

        if ($response->getStatusCode() >= 400) {
            throw $this->errorMapper->fromResponse($response);
        }

        return JsonObject::fromJson((string) $response->getBody())->optionalString('status') ?? 'unknown';
    }
}
