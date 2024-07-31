<?php
declare(strict_types=1);

namespace Tests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Litalico\EgR2\Http\Requests\FormRequestPropertyHandlerTrait;
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
}