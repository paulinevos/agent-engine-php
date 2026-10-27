<?php

declare(strict_types=1);

namespace AgentEngine\Tests\Unit;

use AgentEngine\Exception\UnexpectedResponse;
use AgentEngine\Json\JsonObject;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonObjectTest extends TestCase
{
    #[Test]
    public function it_reads_typed_fields(): void
    {
        $json = JsonObject::fromJson('{"name":"agent","count":2,"active":true,"nested":{"a":1},"list":["x"]}');

        self::assertSame('agent', $json->string('name'));
        self::assertSame(2, $json->integer('count'));
        self::assertTrue($json->boolean('active'));
        self::assertSame(['a' => 1], $json->object('nested')->toArray());
        self::assertSame(['x'], $json->array('list'));
        self::assertNull($json->optionalString('missing'));
    }

    #[Test]
    public function it_rejects_malformed_json(): void
    {
        $this->expectException(UnexpectedResponse::class);

        JsonObject::fromJson('{not json');
    }

    #[Test]
    public function it_rejects_a_missing_required_field(): void
    {
        $this->expectException(UnexpectedResponse::class);

        JsonObject::fromArray([])->string('name');
    }

    #[Test]
    public function it_encodes_an_empty_object_as_a_json_object(): void
    {
        self::assertSame('{}', JsonObject::fromArray([])->toJson());
    }

    #[Test]
    public function it_encodes_nested_objects_and_lists(): void
    {
        $json = JsonObject::fromArray([
            'object' => ['a' => 1],
            'list' => [1, 2],
            'empty' => [],
        ]);

        self::assertSame('{"object":{"a":1},"list":[1,2],"empty":{}}', $json->toJson());
    }
}
