<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Litalico\EgR2\Http\Responses\ResponseExampleGeneratorTrait;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use function strlen;

#[CoversTrait(ResponseExampleGeneratorTrait::class)]
class ResponseExampleGeneratorTraitTest extends TestCase
{
    #[Test]
    public function propertyExamplesEnumsAndDefaultsHaveDefinedPrecedence(): void
    {
        $response = new class
        {
            use ResponseExampleGeneratorTrait;

            #[Property(property: 'example', example: 'example value', enum: ['enum value'], default: 'default value')]
            public string $example;

            #[Property(property: 'enum', enum: ['first enum value', 'second enum value'], default: 'default value')]
            public string $enum;

            #[Property(property: 'default', default: 'default value')]
            public string $default;
        };

        self::assertSame([
            'example' => 'example value',
            'enum' => 'first enum value',
            'default' => 'default value',
        ], $response->generatedExample());
    }

    #[Test]
    public function scalarFormatsAndConstraintsProduceStableValidValues(): void
    {
        $response = new class
        {
            use ResponseExampleGeneratorTrait;

            #[Property(property: 'date', type: 'string', format: 'date')]
            public string $date;

            #[Property(property: 'dateTime', type: 'string', format: 'date-time')]
            public string $dateTime;

            #[Property(property: 'email', type: 'string', format: 'email')]
            public string $email;

            #[Property(property: 'uuid', type: 'string', format: 'uuid')]
            public string $uuid;

            #[Property(property: 'integer', type: 'integer', minimum: 5, maximum: 9, exclusiveMinimum: 3)]
            public int $integer;

            #[Property(property: 'number', type: 'number', exclusiveMinimum: 1.5, exclusiveMaximum: 3.5)]
            public float $number;

            #[Property(property: 'issueKey', type: 'string', minLength: 7, maxLength: 7, pattern: '^[a-z]{3}_[0-9]+$')]
            public string $issueKey;

            #[Property(property: 'referenceCode', type: 'string', minLength: 10, pattern: '^[a-z]+_[0-9]{3}$')]
            public string $referenceCode;
        };

        $example = $response->generatedExample();

        self::assertSame($example, $response->generatedExample());
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $example['date']);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $example['date']);
        self::assertNotFalse($date);
        self::assertSame($example['date'], $date->format('Y-m-d'));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/', $example['dateTime']);
        self::assertNotFalse(filter_var($example['email'], FILTER_VALIDATE_EMAIL));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $example['uuid']);
        self::assertGreaterThanOrEqual(5, $example['integer']);
        self::assertLessThanOrEqual(9, $example['integer']);
        self::assertGreaterThan(1.5, $example['number']);
        self::assertLessThan(3.5, $example['number']);
        self::assertSame(7, strlen($example['issueKey']));
        self::assertSame(1, preg_match('/^[a-z]{3}_[0-9]+$/', $example['issueKey']));
        self::assertSame(10, strlen($example['referenceCode']));
        self::assertSame(1, preg_match('/^[a-z]+_[0-9]{3}$/', $example['referenceCode']));
    }

    #[Test]
    public function classSchemaInlinePropertiesGenerateNestedObjectsAndArrays(): void
    {
        $response = new #[Schema(properties: [
            new Property(
                property: 'profile',
                properties: [
                    new Property(property: 'name', example: 'Ada'),
                    new Property(
                        property: 'tags',
                        minItems: 2,
                        maxItems: 2,
                        items: new Items(example: 'tag'),
                    ),
                ],
            ),
            new Property(
                property: 'matrix',
                minItems: 1,
                maxItems: 1,
                items: new Items(
                    minItems: 2,
                    maxItems: 2,
                    items: new Items(example: 'cell'),
                ),
            ), ])] class
            {
                use ResponseExampleGeneratorTrait;
            };

        self::assertSame([
            'profile' => [
                'name' => 'Ada',
                'tags' => ['tag', 'tag'],
            ],
            'matrix' => [['cell', 'cell']],
        ], $response->generatedExample());
    }

    #[Test]
    public function classExampleWinsOverInlinePropertiesAndEnumClassesUseTheirFirstCase(): void
    {
        $explicitExampleResponse = new #[Schema(
            example: ['source' => 'class example'],
            properties: [new Property(property: 'source', example: 'inline property')],
        )] class
        {
            use ResponseExampleGeneratorTrait;
        };

        $enumResponse = new class
        {
            use ResponseExampleGeneratorTrait;

            #[Property(property: 'backed', enum: ResponseExampleBackedStatus::class)]
            public string $backed;

            #[Property(property: 'unit', enum: ResponseExampleUnitStatus::class)]
            public string $unit;
        };

        self::assertSame(['source' => 'class example'], $explicitExampleResponse->generatedExample());
        self::assertSame([
            'backed' => 'active',
            'unit' => 'Draft',
        ], $enumResponse->generatedExample());
    }

    #[Test]
    public function publicAnnotatedPropertiesUseOpenApiOrPhpNamesAndExcludeEverythingElse(): void
    {
        $response = new class
        {
            use ResponseExampleGeneratorTrait;

            #[Property(example: 'php name')]
            public string $fallbackName;

            #[Property(property: 'openApiName', example: 'OpenAPI name')]
            public string $differentPhpName;

            #[Property]
            public int $inferredInteger;

            #[Property(property: 'hidden', example: 'must not appear')]
            private string $privateProperty;

            public string $unannotatedProperty;
        };

        self::assertSame([
            'fallbackName' => 'php name',
            'openApiName' => 'OpenAPI name',
            'inferredInteger' => 0,
        ], $response->generatedExample());
    }
}

enum ResponseExampleBackedStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

enum ResponseExampleUnitStatus
{
    case Draft;
    case Published;
}
