<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Auth\ClientCredentialsTokenProvider;
use AgentEngine\Exception\OAuthFailure;
use AgentEngine\Tests\Support\ArrayCache;
use AgentEngine\Tests\Support\FakeTransport;
use AgentEngine\Tests\Support\MutableClock;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ClientCredentials;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ClientCredentialsTokenProviderTest extends TestCase
{
    private const TOKEN_RESPONSE = '{"access_token":"abc","token_type":"Bearer","expires_in":3600}';

    #[Test]
    public function it_exchanges_credentials_and_reuses_the_token(): void
    {
        $transport = new FakeTransport(new Response(200, [], self::TOKEN_RESPONSE));
        $clock = new MutableClock(new \DateTimeImmutable('2026-10-27T10:00:00Z'));
        $provider = $this->provider($transport, $clock);

        self::assertSame('abc', $provider->token()->value);
        $provider->token();
        self::assertCount(1, $transport->requests);

        $request = $transport->requests[0];
        self::assertSame('https://example.test/api/v1/oauth/token', (string) $request->getUri());
        self::assertSame(
            'Basic ' . base64_encode('client-id:client-secret'),
            $request->getHeaderLine('Authorization'),
        );
        self::assertSame('grant_type=client_credentials', (string) $request->getBody());
    }

    #[Test]
    public function it_refreshes_an_expired_token(): void
    {
        $transport = new FakeTransport(
            new Response(200, [], self::TOKEN_RESPONSE),
            new Response(200, [], '{"access_token":"new","token_type":"Bearer","expires_in":3600}'),
        );
        $clock = new MutableClock(new \DateTimeImmutable('2026-10-27T10:00:00Z'));
        $provider = $this->provider($transport, $clock);

        self::assertSame('abc', $provider->token()->value);
        $clock->advance('+61 minutes');

        self::assertSame('new', $provider->token()->value);
        self::assertCount(2, $transport->requests);
    }

    #[Test]
    public function it_reuses_a_token_from_the_cache_in_another_provider(): void
    {
        $cache = new ArrayCache();
        $clock = new MutableClock(new \DateTimeImmutable('2026-10-27T10:00:00Z'));

        $this->provider(new FakeTransport(new Response(200, [], self::TOKEN_RESPONSE)), $clock, $cache)
            ->token();

        $secondTransport = new FakeTransport();
        $second = $this->provider($secondTransport, $clock, $cache);

        self::assertSame('abc', $second->token()->value);
        self::assertCount(0, $secondTransport->requests);
    }

    #[Test]
    public function it_surfaces_rejected_credentials(): void
    {
        $transport = new FakeTransport(new Response(401, [], json_encode([
            'error' => 'invalid_client',
            'error_description' => 'Bad credentials.',
        ]) ?: ''));

        $provider = $this->provider(
            $transport,
            new MutableClock(new \DateTimeImmutable('2026-10-27T10:00:00Z')),
        );

        $this->expectException(OAuthFailure::class);
        $this->expectExceptionMessage('Bad credentials.');

        $provider->token();
    }

    private function provider(
        FakeTransport $transport,
        MutableClock $clock,
        ?ArrayCache $cache = null,
    ): ClientCredentialsTokenProvider {
        return new ClientCredentialsTokenProvider(
            baseUrl: BaseUrl::fromString('https://example.test'),
            credentials: ClientCredentials::fromStrings('client-id', 'client-secret'),
            transport: $transport,
            clock: $clock,
            cache: $cache,
        );
    }
}
