<?php

declare(strict_types=1);

namespace AgentEngine\Resource;

use AgentEngine\Http\ApiRequest;
use AgentEngine\Http\ApiRequestSender;
use AgentEngine\Http\HttpMethod;
use AgentEngine\Json\JsonObject;
use AgentEngine\Memory\MemoryContextQuery;
use AgentEngine\Memory\MemorySearchQuery;
use AgentEngine\Memory\RecordTurn;
use AgentEngine\Value\ProjectId;

final readonly class Memory
{
    public function __construct(
        private ProjectId $projectId,
        private ApiRequestSender $sender,
    ) {
    }

    public function recordTurn(RecordTurn $turn): JsonObject
    {
        return $this->post('/memory/turns', $turn->toJson());
    }

    public function buildContext(MemoryContextQuery $query): JsonObject
    {
        return $this->post('/memory/context', $query->toJson());
    }

    public function search(MemorySearchQuery $query, ?string $idempotencyKey = null): JsonObject
    {
        return $this->post(
            '/memory/search',
            $query->toJson(),
            $idempotencyKey === null ? [] : ['Idempotency-Key' => $idempotencyKey],
        );
    }

    /**
     * @param array<string, string> $headers
     */
    private function post(string $suffix, JsonObject $body, array $headers = []): JsonObject
    {
        $response = $this->sender->send(new ApiRequest(
            method: HttpMethod::Post,
            path: sprintf('/api/v1/projects/%s', rawurlencode($this->projectId->value)) . $suffix,
            body: $body,
            headers: $headers,
        ));

        return $response->body;
    }
}
