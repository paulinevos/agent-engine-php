<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Auth\StaticTokenProvider;
use AgentEngine\Exception\RateLimited;
use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Json\JsonObject;
use AgentEngine\Value\AccessToken;
use AgentEngine\Value\BaseUrl;
use AgentEngine\Value\ContractVersion;
use AgentEngine\Tests\Support\FakeTransport;
use AgentEngine\Http\UserAgent;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiRequestSenderTest extends TestCase
{
    #[Test]
    public function it_authenticates_pins_the_contract_and_sends_json(): void
    {
        $transport = new FakeTransport(new Response(200, [], '{"ok":true}'));
        $sender = $this->sender($transport);

        $response = $sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: '/api/v1/projects/p1/workspaces/w1/invoke',
            query: ['workspace_id' => 'w1', 'skipped' => null],
            body: JsonObject::fromArray(['message' => 'Hello']),
            headers: ['X-Session-ID' => 'session-1'],
        ));

        self::assertTrue($response->body->boolean('ok'));

        $request = $transport->requests[0];
        self::assertSame(
            'https://example.test/api/v1/projects/p1/workspaces/w1/invoke?workspace_id=w1',
            (string) $request->getUri(),
        );
        self::assertSame('Bearer token-1', $request->getHeaderLine('Authorization'));
        self::assertSame(
            'application/vnd.agent-engine-2026-09-20-preview+json',
            $request->getHeaderLine('Accept'),
        );
        self::assertSame('application/json', $request->getHeaderLine('Content-Type'));
        self::assertSame('session-1', $request->getHeaderLine('X-Session-ID'));
        self::assertSame('test-agent/1.0', $request->getHeaderLine('User-Agent'));
        self::assertSame('{"message":"Hello"}', (string) $request->getBody());
    }

    #[Test]
    public function it_normalises_response_header_names(): void
    {
        $transport = new FakeTransport(new Response(200, ['X-Session-ID' => 'session-1'], '{}'));

        $response = $this->sender($transport)->send(new ApiRequest(
            method: HttpMethod::Post,
            path: '/api/v1/projects/p1/workspaces/w1/invoke',
        ));

        self::assertSame('session-1', $response->header('X-Session-ID'));
    }

    #[Test]
    public function it_maps_an_api_error_response(): void
    {
        $transport = new FakeTransport(new Response(429, ['Retry-After' => '3'], '{"error":"slow down"}'));

        $this->expectException(RateLimited::class);

        $this->sender($transport)->send(new ApiRequest(HttpMethod::Get, '/api/v1/projects/p1/executions'));
    }

    private function sender(FakeTransport $transport): ApiRequestSender
    {
        return new ApiRequestSender(
            baseUrl: BaseUrl::fromString('https://example.test'),
            tokenProvider: new StaticTokenProvider(
                AccessToken::fromParts('token-1', new \DateTimeImmutable('+1 hour')),
            ),
            transport: $transport,
            contractVersion: ContractVersion::default(),
            userAgent: UserAgent::fromString('test-agent/1.0'),
        );
    }
}
