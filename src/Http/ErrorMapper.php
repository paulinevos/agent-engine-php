<?php

declare(strict_types=1);

namespace AgentEngine\Http;

use AgentEngine\Exception\ApiFailure;
use AgentEngine\Exception\AuthenticationRejected;
use AgentEngine\Exception\Conflict;
use AgentEngine\Exception\ContractNotAcceptable;
use AgentEngine\Exception\ErrorDetails;
use AgentEngine\Exception\PermissionDenied;
use AgentEngine\Exception\RateLimited;
use AgentEngine\Exception\RequestRejected;
use AgentEngine\Exception\ResourceNotFound;
use AgentEngine\Exception\ServerFailure;
use AgentEngine\Exception\SessionBusy;
use AgentEngine\Json\JsonObject;
use Psr\Http\Message\ResponseInterface;

final class ErrorMapper
{
    public function fromResponse(ResponseInterface $response): ApiFailure
    {
        return $this->from(
            $response->getStatusCode(),
            (string) $response->getBody(),
            $response->getHeaderLine('Retry-After'),
        );
    }

    public function fromStreamingResponse(StreamingResponse $response): ApiFailure
    {
        return $this->from(
            $response->status,
            $response->body(),
            $response->header('Retry-After') ?? '',
        );
    }

    private function from(int $status, string $body, string $retryAfter): ApiFailure
    {
        $details = new ErrorDetails(
            status: $status,
            message: $this->message($status, $body),
            errorCode: $this->errorCode($body),
            reason: $this->reason($body),
            parameters: $this->parameters($body),
            body: $this->json($body),
            retryAfterSeconds: $this->retryAfterSeconds($retryAfter),
        );

        return match (true) {
            $status === 401 => new AuthenticationRejected($details),
            $status === 403 => new PermissionDenied($details),
            $status === 404 => new ResourceNotFound($details),
            $status === 406 => new ContractNotAcceptable($details),
            $status === 409 => $this->conflict($details),
            $status === 429 => new RateLimited($details),
            $status >= 500 => new ServerFailure($details),
            default => new RequestRejected($details),
        };
    }

    private function conflict(ErrorDetails $details): Conflict
    {
        $code = strtoupper($details->errorCode ?? '');

        if ($details->body->has('blocking_execution_id') || str_contains($code, 'SESSION')) {
            return new SessionBusy($details);
        }

        return new Conflict($details);
    }

    private function message(int $status, string $body): string
    {
        $json = $this->json($body);
        $value = $this->firstNonEmpty($json, ['detail', 'error_description', 'message', 'reason', 'error']);

        if ($value !== null) {
            return $value;
        }

        return trim($body) === '' ? 'HTTP ' . $status : trim($body);
    }

    private function errorCode(string $body): ?string
    {
        return $this->firstNonEmpty($this->json($body), ['errorCode', 'error_code', 'code']);
    }

    /**
     * @param list<string> $fields
     */
    private function firstNonEmpty(JsonObject $json, array $fields): ?string
    {
        $values = array_filter(
            array_map(static fn (string $field): ?string => $json->optionalString($field), $fields),
            static fn (?string $value): bool => $value !== null && $value !== '',
        );

        return $values === [] ? null : (string) reset($values);
    }

    private function reason(string $body): ?string
    {
        return $this->json($body)->optionalString('reason');
    }

    /**
     * @return list<string>
     */
    private function parameters(string $body): array
    {
        $parameters = $this->json($body)->optionalArray('parameters') ?? [];

        return array_values(array_filter($parameters, 'is_string'));
    }

    private function retryAfterSeconds(string $header): ?int
    {
        if ($header === '') {
            return null;
        }

        if (ctype_digit($header)) {
            return (int) $header;
        }

        $date = \DateTimeImmutable::createFromFormat(DATE_RFC7231, $header);

        if ($date === false) {
            return null;
        }

        return max(0, $date->getTimestamp() - time());
    }

    private function json(string $body): JsonObject
    {
        $decoded = json_decode($body, true);

        return is_array($decoded) && !array_is_list($decoded)
            ? JsonObject::fromArray($decoded)
            : new JsonObject();
    }
}
