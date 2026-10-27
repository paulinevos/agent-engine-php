<?php

declare(strict_types=1);

namespace AgentEngine\Auth;

use AgentEngine\Clock\Clock;
use AgentEngine\Clock\SystemClock;
use AgentEngine\Exception\OAuthFailure;
use AgentEngine\Exception\UnexpectedResponse;
use AgentEngine\Http\Transport;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\SimpleCache\CacheInterface;

/**
 * Exchanges a service account's client credentials for a bearer token and
 * caches it in memory. Supplying a PSR-16 cache shares the token across
 * processes so a fleet does not hit the token endpoint once per worker.
 */
final class ClientCredentialsTokenProvider implements TokenProvider
{
    private ?AccessToken $cached = null;

    public function __construct(
        private readonly BaseUrl $baseUrl,
        private readonly ClientCredentials $credentials,
        private readonly Transport $transport,
        private readonly RequestFactoryInterface $requestFactory = new Psr17Factory(),
        private readonly StreamFactoryInterface $streamFactory = new Psr17Factory(),
        private readonly Clock $clock = new SystemClock(),
        private readonly ?CacheInterface $cache = null,
    ) {
    }

    public function token(): AccessToken
    {
        $now = $this->clock->now();

        if ($this->cached !== null && $this->cached->isUsableAt($now)) {
            return $this->cached;
        }

        $stored = $this->readCache();

        if ($stored !== null && $stored->isUsableAt($now)) {
            return $this->cached = $stored;
        }

        return $this->cached = $this->requestToken($now);
    }

    private function requestToken(\DateTimeImmutable $now): AccessToken
    {
        $request = $this->requestFactory
            ->createRequest('POST', $this->baseUrl->endpoint('/api/v1/oauth/token'))
            ->withHeader('Authorization', 'Basic ' . base64_encode(
                $this->credentials->clientId->value . ':' . $this->credentials->clientSecret->value,
            ))
            ->withHeader('Accept', 'application/json')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withBody($this->streamFactory->createStream('grant_type=client_credentials'));

        $response = $this->transport->send($request);
        $body = (string) $response->getBody();

        if ($response->getStatusCode() >= 400) {
            throw $this->oauthFailure($response->getStatusCode(), $body);
        }

        $json = JsonObject::fromJson($body);
        $token = $json->optionalString('access_token');

        if ($token === null) {
            throw UnexpectedResponse::missingField('access_token');
        }

        $accessToken = AccessToken::fromResponse($token, $json->integer('expires_in'), $now);
        $this->writeCache($accessToken, $now);

        return $accessToken;
    }

    private function oauthFailure(int $status, string $body): OAuthFailure
    {
        $decoded = json_decode($body, true);
        $error = is_array($decoded) ? ($decoded['error'] ?? 'unknown_error') : 'unknown_error';
        $description = is_array($decoded) ? ($decoded['error_description'] ?? $body) : $body;

        return OAuthFailure::fromResponse(
            $status,
            is_string($error) ? $error : 'unknown_error',
            is_string($description) ? $description : $body,
        );
    }

    private function readCache(): ?AccessToken
    {
        if ($this->cache === null) {
            return null;
        }

        $stored = $this->cache->get($this->cacheKey());

        if (!is_array($stored) || !is_string($stored['access_token'] ?? null) || !is_int($stored['expires_at'] ?? null)) {
            return null;
        }

        return AccessToken::fromParts(
            $stored['access_token'],
            (new \DateTimeImmutable())->setTimestamp($stored['expires_at']),
        );
    }

    private function writeCache(AccessToken $token, \DateTimeImmutable $now): void
    {
        if ($this->cache === null) {
            return;
        }

        $ttl = $token->expiresAt->getTimestamp() - $now->getTimestamp();

        if ($ttl <= 0) {
            return;
        }

        $this->cache->set($this->cacheKey(), [
            'access_token' => $token->value,
            'expires_at' => $token->expiresAt->getTimestamp(),
        ], $ttl);
    }

    private function cacheKey(): string
    {
        return 'agent-engine.access-token.' . hash(
            'sha256',
            $this->baseUrl->value . '|' . $this->credentials->clientId->value,
        );
    }
}
