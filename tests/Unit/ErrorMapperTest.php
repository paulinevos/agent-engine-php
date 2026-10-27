<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\ContractNotAcceptable;
use AgentEngine\Exception\PermissionDenied;
use AgentEngine\Exception\RateLimited;
use AgentEngine\Exception\RequestRejected;
use AgentEngine\Exception\ResourceNotFound;
use AgentEngine\Exception\ServerFailure;
use AgentEngine\Exception\SessionBusy;
use AgentEngine\Http\ErrorMapper;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ErrorMapperTest extends TestCase
{
    private ErrorMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ErrorMapper();
    }

    #[Test]
    public function it_maps_authentication_and_permission_failures(): void
    {
        self::assertInstanceOf(
            AuthenticationRejected::class,
            $this->mapper->fromResponse(new Response(401, [], '{"error":"unauthorized"}')),
        );

        self::assertInstanceOf(
            PermissionDenied::class,
            $this->mapper->fromResponse(new Response(403, [], '{"error":"forbidden"}')),
        );
    }

    #[Test]
    public function it_maps_not_found_and_contract_failures(): void
    {
        self::assertInstanceOf(
            ResourceNotFound::class,
            $this->mapper->fromResponse(new Response(404, [], '{"error":"missing"}')),
        );

        $failure = $this->mapper->fromResponse(new Response(406, [], json_encode([
            'error' => 406,
            'errorCode' => 'UNSUPPORTED_API_VERSION',
            'detail' => 'The requested API version is not supported.',
            'reason' => 'Not Acceptable',
            'parameters' => ['Accept'],
        ]) ?: ''));

        self::assertInstanceOf(ContractNotAcceptable::class, $failure);
        self::assertSame('UNSUPPORTED_API_VERSION', $failure->details->errorCode);
        self::assertSame(['Accept'], $failure->details->parameters);
    }

    #[Test]
    public function it_maps_a_session_conflict_with_its_blocking_execution(): void
    {
        $failure = $this->mapper->fromResponse(new Response(409, [], json_encode([
            'code' => 'SESSION_CONFLICT',
            'error' => 'Session is busy.',
            'blocking_execution_id' => 'exec-9',
            'blocking_status' => 'running',
        ]) ?: ''));

        self::assertInstanceOf(SessionBusy::class, $failure);
        self::assertSame('exec-9', $failure->blockingExecutionId());
        self::assertSame('running', $failure->blockingStatus());
    }

    #[Test]
    public function it_reads_retry_after_on_a_rate_limit(): void
    {
        $failure = $this->mapper->fromResponse(
            new Response(429, ['Retry-After' => '7'], '{"error":"slow down"}'),
        );

        self::assertInstanceOf(RateLimited::class, $failure);
        self::assertSame(7, $failure->retryAfterSeconds());
    }

    #[Test]
    public function it_maps_server_failures_and_other_client_errors(): void
    {
        self::assertInstanceOf(
            ServerFailure::class,
            $this->mapper->fromResponse(new Response(503, [], '{"error":"down"}')),
        );

        $failure = $this->mapper->fromResponse(new Response(422, [], '{"error":"invalid ask"}'));

        self::assertInstanceOf(RequestRejected::class, $failure);
        self::assertSame('invalid ask', $failure->details->message);
    }

    #[Test]
    public function it_falls_back_to_the_body_when_it_is_not_json(): void
    {
        $failure = $this->mapper->fromResponse(new Response(400, [], 'plain text failure'));

        self::assertSame('plain text failure', $failure->details->message);
    }
}
