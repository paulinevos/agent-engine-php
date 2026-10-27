<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Auth\TokenProvider;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ContractVersion;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * Adds authentication, contract pinning and error mapping to every request.
 * Resources build an ApiRequest and never touch the transport directly.
 */
final readonly class ApiRequestSender
{
    public function __construct(
        private BaseUrl $baseUrl,
        private TokenProvider $tokenProvider,
        private Transport $transport,
        private ContractVersion $contractVersion,
        private UserAgent $userAgent = new UserAgent(),
        private RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private StreamFactoryInterface $streamFactory = new Psr17Factory(),
        private ErrorMapper $errorMapper = new ErrorMapper(),
    ) {
    }

    public function send(ApiRequest $request): ApiResponse
    {
        $response = $this->transport->send(
            $this->psrRequest($request, $this->contractVersion->acceptHeader()),
        );

        if ($response->getStatusCode() >= 400) {
            throw $this->errorMapper->fromResponse($response);
        }

        $body = (string) $response->getBody();

        return new ApiResponse(
            trim($body) === '' ? new JsonObject() : JsonObject::fromJson($body),
            $this->normaliseHeaders($response->getHeaders()),
        );
    }

    public function stream(ApiRequest $request): StreamedResponse
    {
        $response = $this->transport->stream(
            $this->psrRequest($request, $this->contractVersion->streamingAcceptHeader()),
        );

        if ($response->status >= 400) {
            throw $this->errorMapper->fromStreamingResponse($response);
        }

        return new StreamedResponse($response->headers, SseParser::parse($response->lines));
    }

    /**
     * @param array<string, array<string>> $headers
     *
     * @return array<string, list<string>>
     */
    private function normaliseHeaders(array $headers): array
    {
        return array_map('array_values', $headers);
    }

    private function psrRequest(ApiRequest $request, string $accept): RequestInterface
    {
        $psrRequest = $this->requestFactory
            ->createRequest($request->method->value, $this->uri($request))
            ->withHeader('Authorization', $this->tokenProvider->token()->headerValue())
            ->withHeader('Accept', $accept)
            ->withHeader('User-Agent', $this->userAgent->value);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        return $request->body === null ? $psrRequest : $this->withBody($psrRequest, $request->body);
    }

    private function withBody(RequestInterface $request, JsonObject $body): RequestInterface
    {
        return $request
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream($body->toJson()));
    }

    private function uri(ApiRequest $request): string
    {
        $uri = $this->baseUrl->endpoint($request->path);

        if ($request->query === []) {
            return $uri;
        }

        $query = array_filter(
            $request->query,
            static fn (mixed $value): bool => $value !== null,
        );

        return $query === [] ? $uri : $uri . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }
}
