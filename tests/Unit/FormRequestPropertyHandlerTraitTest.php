<?php
declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Litalico\EgR2\Http\Requests\FormRequestPropertyHandlerTrait;
use Mockery;
use OpenApi\Attributes\Property;
use Tests\FullAccessWrapper;
use Tests\TestCase;

/**
 * @package Tests\Unit
 * @coversDefaultClass \Litalico\EgR2\Http\Requests\FormRequestPropertyHandlerTrait
 * @covers \Litalico\EgR2\Http\Requests\FormRequestPropertyHandlerTrait
 * @covers \Litalico\EgR2\Rules\Integer
 * @covers \Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException
 */
class FormRequestPropertyHandlerTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @test
     * @covers ::passedValidation
     */
    public function testPropertyOfFormRequestCanBeInitializedEvenIfValueIsNull(): void
    {
        setup:
        $class = new class extends FormRequest {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: "id",
                title: "id",
                description: "id",
                type: "integer",
                format: "int",
                minimum: 1
            )]
            public int $id;

            #[Property(
                property: "name",
                title: "name",
                description: "name",
                type: "string"
            )]
            public string $name;

            #[Property(
                property: "categories",
                title: "categories",
                description: "categories",
                type: "array",
                format: "string"
            )]
            public array $categories;

            #[Property(
                property: "nullable",
                title: "nullable",
                description: "nullable",
                type: "int",
                nullable: true
            )]
            public ?int $nullable;

            #[Property(
                property: "unknownType",
                title: "unknownType",
                description: "unknownType"
            )]
            public $unknownType;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 0);
        self::assertEmpty($instance->name);
        self::assertEquals($instance->categories, []);
        self::assertNull($instance->nullable);
        self::assertEmpty($instance->unknownType);
    }


    /**
     * @test
     * @covers ::passedValidation
     */
    public function testGetPropertiesOfNestedObject(): void
    {
        setup:
        $requestMock = Mockery::mock('Illuminate\Http\Request');
        $requestMock->shouldReceive('setUserResolver')->andReturn("dummy");
        $requestMock->shouldReceive('all')->andReturn(['id' => 1, 'name' => 'bob']);
        $this->app->instance('request', $requestMock);

        $class = new class extends FormRequest {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: "id",
                title: "id",
                description: "id",
                type: "integer",
                format: "int",
                minimum: 1
            )]
            public int $id;

            #[Property(
                property: "name",
                title: "name",
                description: "name",
                type: "string"
            )]
            public string $name;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 1);
        self::assertEquals($instance->name, 'bob');
    }

    /**
     * @test
     * @covers ::passedValidation
     */
    public function testGetNestedObjectPropertiesEvenIfValueIsNull(): void
    {
        setup:
        $class = new class extends FormRequest {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: "id",
                title: "id",
                description: "id",
                type: "integer",
                format: "int",
                minimum: 1
            )]
            public int $id;

            #[
                Property(
                    property: 'nested',
                    ref: '#/components/schemas/NestedObject',
                ),
            ]
            public NestedObject $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 0);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject::class, $instance->nested);
        self::assertEquals($instance->nested->id, 0);
        self::assertEmpty($instance->nested->name);
    }

    /**
     * @test
     * @covers ::passedValidation
     * @throws \ReflectionException
     */
    public function testNestedObject(): void
    {
        setup:
        $requestMock = Mockery::mock('Illuminate\Http\Request');
        $requestMock->shouldReceive('setUserResolver')->andReturn("dummy");
        $requestMock->shouldReceive('all')->andReturn(['id' => 1, 'nested' => ['id' => 2, 'name' => 'bob']]);
        $this->app->instance('request', $requestMock);

        $class = new class extends FormRequest {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: "id",
                title: "id",
                description: "id",
                type: "integer",
                format: "int",
                minimum: 1
            )]
            public int $id;

            #[
                Property(
                    property: 'nested',
                    ref: '#/components/schemas/NestedObject',
                ),
            ]
            public NestedObject $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 1);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject::class, $instance->nested);
        self::assertEquals($instance->nested->id, 2);
        self::assertEquals($instance->nested->name, 'bob');
    }
}

#[\OpenApi\Attributes\Schema(
    schema: 'NestedObject',
    required: ['id']
)]
class NestedObject extends FormRequest
{
    #[Property(
        property: "id",
        title: "id",
        description: "id",
        type: "integer",
        format: "int",
        minimum: 1
    )]
    public int $id;

    #[Property(
        property: "name",
        title: "name",
        description: "name",
        type: "string",
        format: "string"
    )]
    public string $name;
}
