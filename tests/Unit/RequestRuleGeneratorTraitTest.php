<?php
declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use Litalico\EgR2\Http\Requests\RequestRuleGeneratorTrait;
use Litalico\EgR2\Rules\Integer;
use OpenApi\Annotations\Schema;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use ReflectionException;
use Tests\FullAccessWrapper;
use Tests\TestCase;

/**
 * Testing of Validation Rule Generation Trait
 * @package Tests\Unit
 * @coversDefaultClass \Litalico\EgR2\Http\Requests\RequestRuleGeneratorTrait
 * @covers \Litalico\EgR2\Http\Requests\RequestRuleGeneratorTrait
 * @covers \Litalico\EgR2\Rules\Integer
 */
class RequestRuleGeneratorTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @test
     * @dataProvider singlePropertyConversionPattern
     * @covers ::convertRule
     * @param Schema $property
     * @param array $expected
     */
    public function testCanConvertOpenApiSchemaToLaravelRules(Schema $property, array $expected): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
        };
        /** @var $instance RequestRuleGeneratorTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $actual = $instance->convertRule($property);

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @return array
     */
    public static function singlePropertyConversionPattern(): iterable
    {
        return [
            'nullable-true' => [new Property('key', nullable: true), ['key' => ['nullable']]],
            'nullable-false' => [new Property('key', nullable: false), ['key' => []]],
            'string' => [new Property('key', type: 'string'), ['key' => ['string']]],
            'number' => [new Property('key', type: 'number'), ['key' => ['numeric']]],
            'integer' => [new Property('key', type: 'integer'), ['key' => [new Integer(), 'integer']]],
            'boolean' => [new Property('key', type: 'boolean'), ['key' => ['boolean']]],
            'array' => [new Property('key', type: 'array'), ['key' => ['array']]],
            'object' => [new Property('key', type: 'object'), ['key' => ['array']]],
            'enum' => [new Property('key', enum: [1, 2, 3]), ['key' => [Rule::in([1, 2,3])]]],
            'enum-class-string' => [new Property('key', enum: Status::class), ['key' => [new Enum(Status::class)]]],
            'maximum' => [new Property('key', maximum: 5), ['key' => ['max:5']]],
            'maxLength' => [new Property('key', maxLength: 5), ['key' => ['max:5']]],
            'minimum' => [new Property('key', minimum: 5), ['key' => ['min:5']]],
            'minLength' => [new Property('key', minLength: 5), ['key' => ['min:5']]],
            'minItems' => [new Property('key', minItems: 5), ['key' => ['min:5']]],
            'maxItems' => [new Property('key', maxItems: 5), ['key' => ['max:5']]],
            'pattern' => [new Property('key', pattern: '^[a-z]{3}_[0-9]+'), ['key' => ['regex:/^[a-z]{3}_[0-9]+/']]],
            'date_format:Ymd' => [new Property('key', maxLength: 8, minLength: 8, pattern: '^[0-9]{4}(0[1-9]|1[0-2])[0-3][0-9]', x: ['date_format' => 'Ymd']), ['key' => ['date_format:Ymd']]],
            'date_format:Ym' => [new Property('key', maxLength: 6, minLength: 6, pattern: '^[0-9]{4}(0[1-9]|1[0-2])', x: ['date_format' => 'Ym']), ['key' => ['date_format:Ym']]],
            'date_format:Hi' => [new Property('key', maxLength: 4, minLength: 4, pattern: '^[0-2][0-9][0-5][0-9]', x: ['date_format' => 'Hi']), ['key' => ['date_format:Hi']]],
        ];
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testObjectRequiredFieldsCanBeConverted(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
            #[Property(
                'test',
                required: [
                    'key1',
                    'key2',
                    'key3',
                    'key5',
                ],
                properties: [
                    new Property('key1', type: 'string'),
                    new Property('key2', type: 'string', nullable: true),
                    new Property('key3', type: 'string', nullable: false),
                    new Property('key4', type: 'string'),
                    new Property(
                        'key5',
                        type: 'array',
                        items: new Items(required: ['nestKey1'], properties: [new Property('nestKey1', type: 'integer')])
                    ),
                ],
                type: 'object'
            )]
            public array $test;
        };

        $expected = [
            'test.key1' => ['required_with:test', 'string'],
            'test.key2' => ['nullable', 'string'],
            'test.key3' => ['required_with:test', 'string'],
            'test.key4' => ['string'],
            'test.key5' => ['required_with:test', 'array'],
            'test.key5.*.nestKey1' => ['required_with:test.key5.*', new Integer(), 'integer'],
            'test' => ['array'],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testRequiredOnlyIfTheParentElementExists(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;

            #[Property(
                'parent',
                required: ['key1'],
                properties: [

                    new Property('key1', type: 'string'),
                    new Property('key2', type: 'string'),
                    new Property('key3', type: 'string'),
                ],
                type: 'object'
            )]
            public array $test;
        };

        $expected = [
            'parent.key1' => ['required_with:parent', 'string'],
            'parent.key2' => ['string'],
            'parent.key3' => ['string'],
            'parent' => ['array'],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     * @dataProvider schemaDefinitionPropertyPattern
     * @dataProvider schemaDefinitionParameterPattern
     */
    public function testErrorOccursIfThereIsAConflictBetweenPropertyAndSchemaDefinition(FormRequest $instance): void
    {
        expect:
        $this->expectException(InvalidOpenApiDefinitionException::class);

        $instance->rules();
    }

    /**
     * @return iterable
     */
    public static function schemaDefinitionPropertyPattern(): iterable
    {
        return [
            'property|schema:nullable, property:required' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[Property(
                        'status',
                        type: 'string',
                        enum: Status::class,
                        example: Status::AVAILABLE,
                        nullable: true
                    )]
                    public string $status;
                },
            ],
            'property|schema:required, property:nullable' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[Property(
                        'status',
                        type: 'string',
                        enum: Status::class,
                        example: Status::AVAILABLE,
                    )]
                    public ?string $status;
                },
            ],
            'property|schema:integer, property:string' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[Property(
                        'status',
                        type: 'integer',
                        enum: Status::class,
                        example: Status::AVAILABLE,
                        nullable: true
                    )]
                    public ?string $status;
                },
            ],
            'property|schema:nullable integer, property:required string' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[Property(
                        'status',
                        type: 'integer',
                        enum: Status::class,
                        example: Status::AVAILABLE,
                        nullable: true
                    )]
                    public string $status;
                },
            ],
        ];
    }

    /**
     * @return iterable
     */
    public static function schemaDefinitionParameterPattern(): iterable
    {
        return [
            'Parameters|Schema:required, Property:nullable' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[
                        Parameter('code', name: 'Code', in: 'path', required: true),
                        \OpenApi\Attributes\Schema(type: 'string', maxLength: 19, pattern: '^[0-9]{1,19}', example: '1000000004')
                    ]
                    public ?string $code;
                },
            ],
            'Parameters|Schema:nullable, Property:required' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[
                        Parameter('code', name: 'Code', in: 'path', required: true),
                        \OpenApi\Attributes\Schema(type: 'string', maxLength: 19, pattern: '^[0-9]{1,19}', example: '1000000004', nullable: true)
                    ]
                    public string $code;
                },
            ],
            'Parameters|Schema:string, Property:integer' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[
                        Parameter('code', name: 'Code', in: 'path', required: true),
                        \OpenApi\Attributes\Schema(type: 'string', maxLength: 19, pattern: '^[0-9]{1,19}', example: '1000000004')
                    ]
                    public int $code;
                },
            ],
            'Parameters|schema:required string, property:nullable integer' => [
                new class() extends FormRequest
                {
                    use RequestRuleGeneratorTrait;

                    #[
                        Parameter('code', name: 'Code', in: 'path', required: true),
                        \OpenApi\Attributes\Schema(type: 'string', maxLength: 19, pattern: '^[0-9]{1,19}', example: '1000000004')
                    ]
                    public ?int $code;
                },
            ],
        ];
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testArrayRequiredFieldsCanBeConverted(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
            #[Property(
                title: 'test',
                type: 'array',
                items: new Items(
                    required: [
                        'key1',
                        'key2',
                        'key3',
                    ],
                    properties: [
                        new Property('key1', type: 'string'),
                        new Property('key2', type: 'string', nullable: false),
                        new Property('key3', type: 'string', nullable: true),
                        new Property('key4', type: 'string'),
                    ]
                )
            )]
            public array $test;
        };

        $expected = [
            'test' => ['array'],
            'test.*.key1' => ['required_with:test.*', 'string'],
            'test.*.key2' => ['required_with:test.*', 'string'],
            'test.*.key3' => ['nullable', 'string'],
            'test.*.key4' => ['string'],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertEqualsCanonicalizing($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testMultiTieredPropertiesCanBeConverted(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
            #[Property(
                title: 'test',
                type: 'array',
                items: new Items(
                    required: [
                        'key1',
                        'key2',
                    ],
                    properties: [
                        new Property('key1', type: 'string'),
                        new Property('key2', type: 'string'),
                        new Property(
                            'key3',
                            type: 'array',
                            items: new Items(
                                properties:  [
                                    new Property('key3-1', type: 'string'),
                                ],
                            )
                        ),
                        new Property(
                            'key4',
                            properties: [
                                new Property('key4-1', type: 'string'),
                                new Property(
                                    'key4-2',
                                    properties: [
                                        new Property('key4-2-1', type: 'string'),
                                    ],
                                    type: 'object'
                                ),
                            ],
                            type: 'object',
                        ),
                    ]
                )
            )]
            public array $test;
        };

        $expected = [
            'test' => ['array'],
            'test.*.key1' => ['required_with:test.*', 'string'],
            'test.*.key2' => ['required_with:test.*', 'string'],
            'test.*.key3' => ['array'],
            'test.*.key3.*.key3-1' => ['string'],
            'test.*.key4.key4-1' => ['string'],
            'test.*.key4.key4-2.key4-2-1' => ['string'],
            'test.*.key4' => ['array'],
            'test.*.key4.key4-2' => ['array'],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testCombinationOfParameterAndSchemaCanBeConverted(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
            #[
                Parameter('Key', name: 'key', required: true),
                \OpenApi\Attributes\Schema(type: 'string', maxLength: 19, pattern: '^[0-9]{1,19}')
            ]
            public string $test;
        };

        $expected = [
            'key' => [
                'required',
                'string',
                'max:19',
                'regex:/^[0-9]{1,19}/',
            ],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertSame($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     */
    public function testAllowsRulesToBeSpecifiedForAllElementsOfAChildElementOfAnArrayOmittingThePropertyName(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use RequestRuleGeneratorTrait;
            #[
                Property(
                    'parent',
                    type: 'array',
                    items: new Items(
                        properties: [
                            new Property(
                                'key',
                                type: 'array',
                                items: new Items(
                                    type: 'integer',
                                    format: 'int32',
                                    maximum:12,
                                    minimum:9,
                                ),
                                maxItems: 5,
                                minItems: 1,
                                nullable: false,
                            ),
                        ],
                        type: 'array',
                    ),
                ),
            ]
            public array $test;
        };

        $expected = [
            'parent' => ['array'],
            'parent.*.key' => [
                'array',
                'max:5',
                'min:1',
            ],
            'parent.*.key.*' => [
                new Integer(),
                'integer',
                'max:12',
                'min:9',
            ],
        ];

        when:
        $actual = $class->rules();

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @test
     * @covers ::convertRule
     * @throws ReflectionException
     */
    public function testPropertyAndSchemaCombinationsCanBeConverted(): void
    {
        setup:
        $class = new ForClassSchemaAndPropertyTest();
        $expected = [
            'key1' => ['required', 'string'],
            'key2' => [new Integer(), 'integer'],
            'key3' => ['required', 'string'],
            'key4' => ['present', 'array', 'max:2', 'min:1'],
            'key4.*.nestKey1' => ['required_with:key4.*', new Integer(), 'integer'],
            'key5' => ['present', 'array'],
            'key5.key5-1' => ['string'],
            'key6' => ['nullable', 'array','max:5','min:0'],
            'key6.*' => ['string', new Enum(Status::class)],
            'key7' => ['present', 'array'],
            'key7.*' => [new Integer(), 'integer', 'max:10', 'min:0'],
        ];

        when:
        $actual = $class->rules();

        then:
        // Enum object is different, so it is not SAME
        self::assertEquals($expected, $actual);
    }
}

// This is a test class for "Property/Schema combination can be converted". Because it cannot be defined in the class attribute anonymous class
#[
    \OpenApi\Attributes\Schema(
        required: ['key1', 'key3', 'key4', 'key5', 'key7']
    )
]
class ForClassSchemaAndPropertyTest extends FormRequest
{
    use RequestRuleGeneratorTrait;
    #[Property(title: 'key1', type: 'string')]
    public string $key1;

    #[Property(title: 'key2', type: 'integer')]
    public int $key2;

    #[Property(title: 'key3', type: 'string')]
    public string $key3;

    #[Property(
        title: 'key4',
        type: 'array',
        items: new Items(required: ['nestKey1'], properties: [new Property('nestKey1', type: 'integer')]),
        maxItems: 2,
        minItems: 1
    )]
    public array $key4;

    #[Property(
        'key5',
        properties: [
            new Property('key5-1', type: 'string'),
        ],
        type: 'object'
    )]
    public array $key5;

    #[Property(
        'key6',
        type: 'array',
        items: new Items(
            type: 'string',
            enum: Status::class,
        ),
        maxItems: 5,
        minItems: 0,
        nullable: true,
    )]
    public ?array $key6;

    #[Property(
        'key7',
        type: 'array',
        items: new Items(
            type: 'integer',
            maximum: 10,
            minimum: 0,
        ),
        nullable: false,
    )]
    public array $key7;
}

enum Status:string {
    case AVAILABLE = "available";
    case PENDING = "pending";
    case SOLD = "sold";
}