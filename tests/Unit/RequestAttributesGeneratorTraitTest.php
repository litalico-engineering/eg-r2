<?php

declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Litalico\EgR2\Http\Requests\RequestAttributesGeneratorTrait;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Testing of Request Attributes Generator Trait
 */
#[CoversTrait(RequestAttributesGeneratorTrait::class)]
class RequestAttributesGeneratorTraitTest extends TestCase
{
    #[Test]
    public function canGenerateAttributesFromSimpleProperties(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'facilityCode',
                description: 'facility code',
                type: 'string'
            )]
            public string $facilityCode;

            #[Property(
                property: 'serviceType',
                description: 'service type',
                type: 'string'
            )]
            public string $serviceType;
        };

        $expected = [
            'facilityCode' => 'facility code',
            'serviceType' => 'service type',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromArrayWithNestedProperties(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'items',
                description: 'items array',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'code',
                            description: 'item code',
                            type: 'string'
                        ),
                        new Property(
                            property: 'name',
                            description: 'item name',
                            type: 'string'
                        ),
                    ]
                )
            )]
            public array $items;
        };

        $expected = [
            'items' => 'items array',
            'items.*' => 'items arrayの各項目',
            'items.*.code' => 'items arrayの :position 行目の「item code」',
            'items.*.name' => 'items arrayの :position 行目の「item name」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function descriptionHasPriorityOverTitle(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'field1',
                title: 'title1',
                description: 'description1',
                type: 'string'
            )]
            public string $field1;

            #[Property(
                property: 'field2',
                title: 'title2',
                type: 'string'
            )]
            public string $field2;

            #[Property(
                property: 'field3',
                type: 'string'
            )]
            public string $field3;
        };

        $expected = [
            'field1' => 'description1',
            'field2' => 'title2',
            'field3' => 'field3',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromObjectType(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(property: 'address', description: 'address object', properties: [
                new Property(
                    property: 'zipCode',
                    description: 'zip code',
                    type: 'string'
                ),
                new Property(
                    property: 'prefecture',
                    description: 'prefecture name',
                    type: 'string'
                ),
            ], type: 'object')]
            public array $address;
        };

        $expected = [
            'address' => 'address object',
            'address.zipCode' => 'zip code',
            'address.prefecture' => 'prefecture name',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromMultiLevelNestedArrays(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'departments',
                description: 'departments',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'name',
                            description: 'dept name',
                            type: 'string'
                        ),
                        new Property(
                            property: 'employees',
                            description: 'employees',
                            type: 'array',
                            items: new Items(
                                properties: [
                                    new Property(
                                        property: 'employeeId',
                                        description: 'employee id',
                                        type: 'string'
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )]
            public array $departments;
        };

        $expected = [
            'departments' => 'departments',
            'departments.*' => 'departmentsの各項目',
            'departments.*.name' => 'departmentsの :position 行目の「dept name」',
            'departments.*.employees' => 'departmentsの :position 行目の「employees」',
            'departments.*.employees.*' => 'departmentsの :position 行目の「employees」の各項目',
            'departments.*.employees.*.employeeId' => 'departmentsの :position 行目の「employee id」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromSchemaAndParameterCombination(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[
                Parameter('code', name: 'code', description: 'code value', in: 'path', required: true),
                Schema(type: 'string', maxLength: 19)
            ]
            public string $code;
        };

        $expected = [
            'code' => 'code value',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canOverrideGeneratedAttributes(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'field1',
                description: 'field one',
                type: 'string'
            )]
            public string $field1;

            #[Property(
                property: 'field2',
                description: 'field two',
                type: 'string'
            )]
            public string $field2;

            public function attributes(): array
            {
                return [...$this->generatedAttributes(), 'field1' => 'custom field one'];
            }
        };

        $expected = [
            'field1' => 'custom field one',
            'field2' => 'field two',
        ];

        $actual = $class->attributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromArrayWithSimpleItems(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'tags',
                description: 'tags',
                type: 'array',
                items: new Items(
                    type: 'string'
                )
            )]
            public array $tags;
        };

        $expected = [
            'tags' => 'tags',
            'tags.*' => 'tagsの各項目',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesWithTitleAsLabel(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                title: 'customName',
                description: 'custom name',
                type: 'string'
            )]
            public string $actualPropertyName;
        };

        $expected = [
            'actualPropertyName' => 'custom name',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function reflectedPropertyNameHasPriorityOverAttributePropertyName(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'attributePropertyName',
                description: 'display name',
                type: 'string'
            )]
            public string $reflectedPropertyName;
        };

        $expected = [
            'reflectedPropertyName' => 'display name',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesForMixedObjectAndArrayStructure(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(property: 'parent', description: 'parent object', properties: [
                new Property(
                    property: 'name',
                    description: 'name',
                    type: 'string'
                ),
                new Property(
                    property: 'items',
                    description: 'items',
                    type: 'array',
                    items: new Items(
                        properties: [
                            new Property(
                                property: 'itemId',
                                description: 'item id',
                                type: 'string'
                            ),
                        ]
                    )
                ),
            ], type: 'object')]
            public array $parent;
        };

        $expected = [
            'parent' => 'parent object',
            'parent.name' => 'name',
            'parent.items' => 'items',
            'parent.items.*' => 'itemsの各項目',
            'parent.items.*.itemId' => 'itemsの :position 行目の「item id」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function attributes_methodReturnsGeneratedAttributes(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'name',
                description: 'full name',
                type: 'string'
            )]
            public string $name;

            #[Property(
                property: 'email',
                description: 'email address',
                type: 'string'
            )]
            public string $email;
        };

        $expected = [
            'name' => 'full name',
            'email' => 'email address',
        ];

        $actual = $class->attributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesInJapanese(): void
    {
        app('config')->set('app.locale', 'ja');

        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'items',
                description: 'items',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'code',
                            description: 'code',
                            type: 'string'
                        ),
                    ]
                )
            )]
            public array $items;
        };

        $expected = [
            'items' => 'items',
            'items.*' => 'itemsの各項目',
            'items.*.code' => 'itemsの :position 行目の「code」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesInEnglish(): void
    {
        app('config')->set('app.locale', 'en');

        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'items',
                description: 'items',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'code',
                            description: 'code',
                            type: 'string'
                        ),
                    ]
                )
            )]
            public array $items;
        };

        $expected = [
            'items' => 'items',
            'items.*' => 'Each item of items',
            'items.*.code' => 'Row :position of items: "code"',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateNestedArrayAttributesInJapanese(): void
    {
        app('config')->set('app.locale', 'ja');

        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'departments',
                description: 'departments',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'employees',
                            description: 'employees',
                            type: 'array',
                            items: new Items(
                                properties: [
                                    new Property(
                                        property: 'id',
                                        description: 'employee id',
                                        type: 'string'
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )]
            public array $departments;
        };

        $expected = [
            'departments' => 'departments',
            'departments.*' => 'departmentsの各項目',
            'departments.*.employees' => 'departmentsの :position 行目の「employees」',
            'departments.*.employees.*' => 'departmentsの :position 行目の「employees」の各項目',
            'departments.*.employees.*.id' => 'departmentsの :position 行目の「employee id」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateNestedArrayAttributesInEnglish(): void
    {
        app('config')->set('app.locale', 'en');

        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'departments',
                description: 'departments',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'employees',
                            description: 'employees',
                            type: 'array',
                            items: new Items(
                                properties: [
                                    new Property(
                                        property: 'id',
                                        description: 'employee id',
                                        type: 'string'
                                    ),
                                ]
                            )
                        ),
                    ]
                )
            )]
            public array $departments;
        };

        $expected = [
            'departments' => 'departments',
            'departments.*' => 'Each item of departments',
            'departments.*.employees' => 'Row :position of departments: "employees"',
            'departments.*.employees.*' => 'Each item of row :position of departments: "employees"',
            'departments.*.employees.*.id' => 'Row :position of departments: "employee id"',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromNestedFormRequestClass(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            public NestedSchemaClass $nested;
        };

        $expected = [
            'nested.code' => 'code description',
            'nested.name' => 'name description',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function nestedFormRequestClassSkipsPropertiesWithoutPropertyAttribute(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            public NestedSchemaClassWithMixedProperties $nested;
        };

        $expected = [
            'nested.annotated' => 'annotated field',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function classTypedPropertyWithoutSchemaAttributeFallsThrough(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            public ClassWithoutSchema $noSchema;
        };

        $expected = [];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function publicPropertyWithoutOpenApiAttributesIsSkipped(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            public string $noAttribute;

            #[Property(
                property: 'name',
                description: 'name field',
                type: 'string'
            )]
            public string $name;
        };

        $expected = [
            'name' => 'name field',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function nonPublicPropertiesAreSkipped(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'visible',
                description: 'visible field',
                type: 'string'
            )]
            public string $visible;

            #[Property(
                property: 'hidden',
                description: 'hidden field',
                type: 'string'
            )]
            protected string $hidden;

            #[Property(
                property: 'secret',
                description: 'secret field',
                type: 'string'
            )]
            private string $secret; /** @phpstan-ignore-line */
        };

        $actual = $class->generatedAttributes();

        self::assertArrayHasKey('visible', $actual);
        self::assertArrayNotHasKey('hidden', $actual);
        self::assertArrayNotHasKey('secret', $actual);
    }

    #[Test]
    public function canGenerateAttributesFromSchemaOnlyWithoutParameter(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Schema(title: 'status', description: 'status value', type: 'string')]
            public string $status;
        };

        $expected = [
            'status' => 'status value',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function schemaWithDescriptionKeepsPriorityOverParameterDescription(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[
                Parameter('code', name: 'code', description: 'parameter desc', in: 'path', required: true),
                Schema(description: 'schema desc', type: 'string')
            ]
            public string $code;
        };

        $expected = [
            'code' => 'schema desc',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function schemaArrayTypeWithParameterNameContainingBrackets(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[
                Parameter('ids', name: 'ids[]', description: 'id list', in: 'query'),
                Schema(
                    type: 'array',
                    items: new Items(type: 'integer')
                )
            ]
            public array $ids;
        };

        $expected = [
            'ids' => 'id list',
            'ids.*' => 'id listの各項目',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function schemaWithTitleOnly(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Schema(title: 'myField', type: 'string')]
            public string $myField;
        };

        $expected = [
            'myField' => 'myField',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromObjectInsideArrayWithEmptyRootDescription(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            #[Property(
                property: 'records',
                description: 'records',
                type: 'array',
                items: new Items(
                    properties: [
                        new Property(
                            property: 'detail',
                            description: 'detail object',
                            properties: [
                                new Property(
                                    property: 'value',
                                    description: 'value field',
                                    type: 'string'
                                ),
                            ],
                            type: 'object'
                        ),
                    ]
                )
            )]
            public array $records;
        };

        $expected = [
            'records' => 'records',
            'records.*' => 'recordsの各項目',
            'records.*.detail' => 'detail object',
            'records.*.detail.value' => 'recordsの :position 行目の「value field」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }

    #[Test]
    public function canGenerateAttributesFromNestedFormRequestWithArrayProperty(): void
    {
        $class = new class extends FormRequest
        {
            use RequestAttributesGeneratorTrait;

            public NestedSchemaClassWithArray $nested;
        };

        $expected = [
            'nested.items' => 'item list',
            'nested.items.*' => 'item listの各項目',
            'nested.items.*.id' => 'item listの :position 行目の「item id」',
        ];

        $actual = $class->generatedAttributes();

        self::assertEquals($expected, $actual);
    }
}

#[Schema(schema: 'NestedSchemaClass')]
class NestedSchemaClass
{
    #[Property(property: 'code', description: 'code description', type: 'string')]
    public string $code;

    #[Property(property: 'name', description: 'name description', type: 'string')]
    public string $name;
}

#[Schema(schema: 'NestedSchemaClassWithMixedProperties')]
class NestedSchemaClassWithMixedProperties
{
    #[Property(property: 'annotated', description: 'annotated field', type: 'string')]
    public string $annotated;

    public string $notAnnotated;
}

class ClassWithoutSchema
{
    public string $field;
}

#[Schema(schema: 'NestedSchemaClassWithArray')]
class NestedSchemaClassWithArray
{
    #[Property(
        property: 'items',
        description: 'item list',
        type: 'array',
        items: new Items(
            properties: [
                new Property(
                    property: 'id',
                    description: 'item id',
                    type: 'string'
                ),
            ]
        )
    )]
    public array $items;
}
