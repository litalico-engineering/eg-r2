<?php

declare(strict_types=1);

namespace Tests\Unit;

use Litalico\EgR2\Services\SchemaTypeResolver;
use OpenApi\Attributes\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(SchemaTypeResolver::class)]
class SchemaTypeResolverTest extends TestCase
{
    #[Test]
    #[DataProvider('schemaPattern')]
    public function canResolveNullableAndType(Property $schema, bool $nullable, string|array|null $baseType): void
    {
        self::assertSame($nullable, SchemaTypeResolver::isNullable($schema));
        self::assertSame($baseType, SchemaTypeResolver::baseType($schema));
    }

    /**
     * @return iterable<string, array{Property, bool, string|array<string>|null}>
     */
    public static function schemaPattern(): iterable
    {
        return [
            'undefined' => [new Property('key'), false, null],
            '3.0 nullable true' => [new Property('key', type: 'string', nullable: true), true, 'string'],
            '3.0 nullable false' => [new Property('key', type: 'string', nullable: false), false, 'string'],
            '3.1 type with null' => [new Property('key', type: ['string', 'null']), true, 'string'],
            '3.1 null first' => [new Property('key', type: ['null', 'integer']), true, 'integer'],
            '3.1 type array without null' => [new Property('key', type: ['string']), false, 'string'],
            '3.1 multiple types with null' => [new Property('key', type: ['string', 'integer', 'null']), true, ['string', 'integer']],
            '3.1 null only' => [new Property('key', type: ['null']), true, null],
        ];
    }

    #[Test]
    #[DataProvider('acceptsNullPattern')]
    public function canResolveAcceptsNull(Property $schema, bool $expected): void
    {
        self::assertSame($expected, SchemaTypeResolver::acceptsNull($schema));
    }

    /**
     * @return iterable<string, array{Property, bool}>
     */
    public static function acceptsNullPattern(): iterable
    {
        return [
            'undefined' => [new Property('key'), false],
            '3.0 nullable true' => [new Property('key', type: 'string', nullable: true), true],
            '3.0 nullable true with enum class' => [new Property('key', type: 'string', nullable: true, enum: Status::class), true],
            '3.0 nullable false' => [new Property('key', type: 'string', nullable: false), false],
            '3.1 type with null' => [new Property('key', type: ['string', 'null']), true],
            '3.1 type with null, enum class' => [new Property('key', type: ['string', 'null'], enum: Status::class), false],
            '3.1 type with null, enum without null' => [new Property('key', type: ['string', 'null'], enum: [Status::class]), false],
            '3.1 type with null, enum with null' => [new Property('key', type: ['string', 'null'], enum: [Status::class, null]), true],
            '3.1 type without null, enum with null' => [new Property('key', type: ['string'], enum: [Status::class, null]), false],
        ];
    }

    #[Test]
    public function isChecksBaseType(): void
    {
        self::assertTrue(SchemaTypeResolver::is(new Property('key', type: ['array', 'null']), 'array'));
        self::assertFalse(SchemaTypeResolver::is(new Property('key', type: ['array', 'null']), 'object'));
        self::assertFalse(SchemaTypeResolver::is(new Property('key'), 'array'));
    }
}
