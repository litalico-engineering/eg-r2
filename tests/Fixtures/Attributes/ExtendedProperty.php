<?php

declare(strict_types=1);

namespace Tests\Fixtures\Attributes;

use Attribute;
use OpenApi\Attributes\Property;
use OpenApi\Generator;

/**
 * A user-defined attribute that extends OpenApi Property.
 *
 * Applications commonly wrap a frequently repeated Property definition in a
 * dedicated attribute class so that the same schema options do not have to be
 * written over and over again.
 *
 * Such a subclass must be resolved exactly like a plain Property attribute.
 */
#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
class ExtendedProperty extends Property
{
    private const PATTERN = '^[0-9]{3,5}$';

    public function __construct(
        string $property,
        string $description = 'extended property',
        bool $nullable = false,
        mixed $default = Generator::UNDEFINED,
    ) {
        parent::__construct(
            property: $property,
            title: $property,
            description: $description,
            type: 'string',
            default: $default,
            pattern: self::PATTERN,
            nullable: $nullable,
            maxLength: 5,
            minLength: 3,
        );
    }
}
