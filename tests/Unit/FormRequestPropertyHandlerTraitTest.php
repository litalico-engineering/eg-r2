<?php
declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use Litalico\EgR2\Http\Requests\FormRequestPropertyHandlerTrait;
use Litalico\EgR2\Rules\Integer;
use Mockery;
use OpenApi\Attributes\Property;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use Tests\FullAccessWrapper;
use Tests\TestCase;

/**
 * @package Tests\Unit
 */
#[CoversTrait(FormRequestPropertyHandlerTrait::class)]
#[CoversClass(InvalidOpenApiDefinitionException::class)]
#[CoversClass(Integer::class)]
class FormRequestPropertyHandlerTraitTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
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
     * @throws ReflectionException
     */
    #[Test]
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
     * @throws ReflectionException
     */
    #[Test]
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
                    ref: '#/components/schemas/NestedObject2',
                ),
            ]
            public NestedObject2 $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 0);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject2::class, $instance->nested);
        self::assertEquals($instance->nested->id, 0);
        self::assertEmpty($instance->nested->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function testNestedObject(): void
    {
        setup:
        $requestMock = Mockery::mock(Request::class);
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
                    ref: '#/components/schemas/NestedObject2',
                ),
            ]
            public NestedObject2 $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals($instance->id, 1);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject2::class, $instance->nested);
        self::assertEquals($instance->nested->id, 2);
        self::assertEquals($instance->nested->name, 'bob');
    }
}

#[\OpenApi\Attributes\Schema(
    schema: 'NestedObject2',
    required: ['id']
)]
class NestedObject2 extends FormRequest
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
