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
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Test;
use ReflectionException;
use stdClass;
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
    #[Override]
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function propertyOfFormRequestCanBeInitializedEvenIfValueIsNull(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use FormRequestPropertyHandlerTrait;

            public int $intWithoutInitialValue;
            public string $stringWithoutInitialValue;
            public array $arrayWithoutInitialValue;
            public bool $boolWithoutInitialValue;
            public float $floatWithoutInitialValue;
            public object $objectWithoutInitialValue;

            public int $intWithInitialValue = 101;
            public string $stringWithInitialValue = 'string';
            public array $arrayWithInitialValue = ['a', 'b', 'c'];
            public bool $boolWithInitialValue = true;
            public float $floatWithInitialValue = 0.999;

            public ?int $nullableIntWithInitialValue = 101;
            public ?string $nullableStringWithInitialValue = 'string';
            public ?array $nullableArrayWithInitialValue = ['a', 'b', 'c'];
            public ?bool $nullableBoolWithInitialValue = true;
            public ?float $nullableFloatWithInitialValue = 0.999;

            public ?int $nullableIntWithInitialValueNull = null;
            public ?string $nullableStringWithInitialValueNull = null;
            public ?array $nullableArrayWithInitialValueNull = null;
            public ?bool $nullableBoolWithInitialValueNull = null;
            public ?float $nullableFloatWithInitialValueNull = null;

            #[Property(
                type: 'integer',
            )]
            public int $intWithoutInitialValueWithoutDefault;

            #[Property(
                type: 'string',
            )]
            public string $stringWithoutInitialValueWithoutDefault;

            #[Property(
                type: 'array',
            )]
            public array $arrayWithoutInitialValueWithoutDefault;

            #[Property(
                type: 'bool'
            )]
            public bool $boolWithoutInitialValueWithoutDefault;

            #[Property(
                type: 'float'
            )]
            public float $floatWithoutInitialValueWithoutDefault;

            #[Property(
                type: 'integer',
            )]
            public int $intWithInitialValueWithoutDefault = 3;

            #[Property(
                type: 'string',
            )]
            public string $stringWithInitialValueWithoutDefault = 'string';

            #[Property(
                type: 'array',
            )]
            public array $arrayWithInitialValueWithoutDefault = ['c', 'd', 'e'];

            #[Property(
                type: 'bool'
            )]
            public bool $boolWithInitialValueWithoutDefault = true;

            #[Property(
                type: 'float'
            )]
            public float $floatWithInitialValueWithoutDefault = 0.8888;

            #[Property(type: 'integer', nullable: true, default: 8989)]
            public int $intWithInitialValueAndDefault = 3;

            #[Property(type: 'integer', nullable: true, default: '888')]
            public int $intWithInitialValueAndDefaultAsString = 3;

            #[Property(type: 'string', nullable: true, default: '33333')]
            public string $stringWithInitialValueAndDefault = 'string';

            #[Property(type: 'array', nullable: true, default: ['bbc', 'abc', 'cnn'])]
            public array $arrayWithInitialValueAndDefault = ['litalico', 'eg-r2'];

            #[Property(type: 'bool', nullable: true, default: 'true')]
            public bool $boolWithInitialValueAndDefaultAsStringTrue = false;

            #[Property(type: 'bool', nullable: true, default: true)]
            public bool $boolWithInitialValueAndDefaultAsTrue = false;

            #[Property(type: 'bool', nullable: true, default: '1')]
            public bool $boolWithInitialValueAndDefaultAsString1 = false;

            #[Property(type: 'float', nullable: true, default: 3.14)]
            public float $floatWithInitialValueAndDefault = 0.5;

            #[Property(type: 'float', nullable: true, default: '2.718')]
            public float $floatWithInitialValueAndDefaultAsString = 0.5;

            #[Property(type: 'integer', nullable: true, default: 999)]
            public ?int $nullableIntWithoutInitialValueAndDefault = null;

            #[Property(type: 'string', nullable: true, default: 'default_string')]
            public ?string $nullableStringWithoutInitialValueAndDefault = null;

            #[Property(type: 'array', nullable: true, default: ['x', 'y', 'z'])]
            public ?array $nullableArrayWithoutInitialValueAndDefault = null;

            #[Property(type: 'bool', nullable: true, default: true)]
            public ?bool $nullableBoolWithoutInitialValueAndDefault = null;

            #[Property(type: 'float', nullable: true, default: 1.23)]
            public ?float $nullableFloatWithoutInitialValueAndDefault = null;

            #[Property(type: 'float', nullable: true, default: 'aa')]
            public $unknownProperty;
        };

        /** @var FullAccessWrapper&FormRequest $instance */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertSame(0, $instance->intWithoutInitialValue);
        self::assertSame('', $instance->stringWithoutInitialValue);
        self::assertSame([], $instance->arrayWithoutInitialValue);
        self::assertFalse($instance->boolWithoutInitialValue);
        self::assertSame(0.0, $instance->floatWithoutInitialValue);
        self::assertInstanceOf(stdClass::class, $instance->objectWithoutInitialValue);

        self::assertSame(101, $instance->intWithInitialValue);
        self::assertSame('string', $instance->stringWithInitialValue);
        self::assertSame(['a', 'b', 'c'], $instance->arrayWithInitialValue);
        self::assertTrue($instance->boolWithInitialValue);
        self::assertSame(0.999, $instance->floatWithInitialValue);

        self::assertSame(101, $instance->nullableIntWithInitialValue);
        self::assertSame('string', $instance->nullableStringWithInitialValue);
        self::assertSame(['a', 'b', 'c'], $instance->nullableArrayWithInitialValue);
        self::assertTrue($instance->nullableBoolWithInitialValue);
        self::assertSame(0.999, $instance->nullableFloatWithInitialValue);

        self::assertNull($instance->nullableIntWithInitialValueNull);
        self::assertNull($instance->nullableStringWithInitialValueNull);
        self::assertNull($instance->nullableArrayWithInitialValueNull);
        self::assertNull($instance->nullableBoolWithInitialValueNull);
        self::assertNull($instance->nullableFloatWithInitialValueNull);

        self::assertSame(0, $instance->intWithoutInitialValueWithoutDefault);
        self::assertSame('', $instance->stringWithoutInitialValueWithoutDefault);
        self::assertSame([], $instance->arrayWithoutInitialValueWithoutDefault);
        self::assertFalse($instance->boolWithoutInitialValueWithoutDefault);
        self::assertSame(0.0, $instance->floatWithoutInitialValueWithoutDefault);

        self::assertSame(3, $instance->intWithInitialValueWithoutDefault);
        self::assertSame('string', $instance->stringWithInitialValueWithoutDefault);
        self::assertSame(['c', 'd', 'e'], $instance->arrayWithInitialValueWithoutDefault);
        self::assertTrue($instance->boolWithInitialValueWithoutDefault);
        self::assertSame(0.8888, $instance->floatWithInitialValueWithoutDefault);

        self::assertSame(8989, $instance->intWithInitialValueAndDefault);
        self::assertSame(888, $instance->intWithInitialValueAndDefaultAsString);
        self::assertSame('33333', $instance->stringWithInitialValueAndDefault);
        self::assertSame(['bbc', 'abc', 'cnn'], $instance->arrayWithInitialValueAndDefault);
        self::assertTrue($instance->boolWithInitialValueAndDefaultAsStringTrue);
        self::assertTrue($instance->boolWithInitialValueAndDefaultAsTrue);
        self::assertTrue($instance->boolWithInitialValueAndDefaultAsString1);

        self::assertSame(3.14, $instance->floatWithInitialValueAndDefault);
        self::assertSame(2.718, $instance->floatWithInitialValueAndDefaultAsString);

        self::assertSame(999, $instance->nullableIntWithoutInitialValueAndDefault);
        self::assertSame('default_string', $instance->nullableStringWithoutInitialValueAndDefault);
        self::assertSame(['x', 'y', 'z'], $instance->nullableArrayWithoutInitialValueAndDefault);
        self::assertTrue($instance->nullableBoolWithoutInitialValueAndDefault);
        self::assertSame(1.23, $instance->nullableFloatWithoutInitialValueAndDefault);

        self::assertSame('aa', $instance->unknownProperty);
    }

    /**
     * @throws
     */
    #[Test]
    public function getPropertiesOfNestedObject(): void
    {
        setup:
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('setUserResolver')->andReturn('dummy');
        $requestMock->shouldReceive('all')->andReturn(['id' => 1, 'name' => 'bob']);
        $this->app->instance('request', $requestMock);

        $class = new class extends FormRequest
        {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: 'id',
                title: 'id',
                description: 'id',
                type: 'integer',
                format: 'int',
                minimum: 1
            )]
            public int $id;

            #[Property(
                property: 'name',
                title: 'name',
                description: 'name',
                type: 'string'
            )]
            public string $name;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals(1, $instance->id);
        self::assertEquals('bob', $instance->name);
    }

    /**
     * @throws ReflectionException
     *
     */
    #[Test]
    public function getNestedObjectPropertiesEvenIfValueIsNull(): void
    {
        setup:
        $class = new class extends FormRequest
        {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: 'id',
                title: 'id',
                description: 'id',
                type: 'integer',
                format: 'int',
                minimum: 1
            )]
            public int $id;

            #[Property(
                property: 'nested',
                ref: '#/components/schemas/NestedObject2',
            )]
            public NestedObject2 $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals(0, $instance->id);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject2::class, $instance->nested);
        self::assertEquals(0, $instance->nested->id);
        self::assertEmpty($instance->nested->name);
    }

    /**
     * @throws ReflectionException
     */
    #[Test]
    public function nestedObject(): void
    {
        setup:
        $requestMock = Mockery::mock(Request::class);
        $requestMock->shouldReceive('setUserResolver')->andReturn('dummy');
        $requestMock->shouldReceive('all')->andReturn(['id' => 1, 'nested' => ['id' => 2, 'name' => 'bob']]);
        $this->app->instance('request', $requestMock);

        $class = new class extends FormRequest
        {
            use FormRequestPropertyHandlerTrait;

            #[Property(
                property: 'id',
                title: 'id',
                description: 'id',
                type: 'integer',
                format: 'int',
                minimum: 1
            )]
            public int $id;

            #[Property(
                property: 'nested',
                ref: '#/components/schemas/NestedObject2',
            )]
            public NestedObject2 $nested;
        };
        /** @var $instance FormRequestPropertyHandlerTrait */
        $instance = new FullAccessWrapper($class);

        when:
        $instance->passedValidation();

        then:
        self::assertNotNull($instance);
        self::assertEquals(1, $instance->id);
        self::assertNotNull($instance->nested);
        self::assertInstanceOf(NestedObject2::class, $instance->nested);
        self::assertEquals(2, $instance->nested->id);
        self::assertEquals('bob', $instance->nested->name);
    }
}

#[\OpenApi\Attributes\Schema(
    schema: 'NestedObject2',
    required: ['id']
)]
class NestedObject2 extends FormRequest
{
    #[Property(
        property: 'id',
        title: 'id',
        description: 'id',
        type: 'integer',
        format: 'int',
        minimum: 1
    )]
    public int $id;

    #[Property(
        property: 'name',
        title: 'name',
        description: 'name',
        type: 'string',
        format: 'string'
    )]
    public string $name;
}
