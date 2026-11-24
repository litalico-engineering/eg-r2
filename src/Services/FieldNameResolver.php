<?php

declare(strict_types=1);

namespace Litalico\EgR2\Services;

use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Generator;
use ReflectionAttribute;
use ReflectionProperty;

/**
 * Resolves the request field name of a PHP property.
 */
final class FieldNameResolver
{
    public static function resolve(ReflectionProperty $property): string
    {
        $properties = $property->getAttributes(Property::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($properties !== []) {
            $name = $properties[0]->newInstance()->property;
            if ($name !== Generator::UNDEFINED && $name !== '') { /** @phpstan-ignore-line */
                return $name;
            }
        }

        $parameters = $property->getAttributes(Parameter::class, ReflectionAttribute::IS_INSTANCEOF);
        if ($parameters !== []) {
            $name = $parameters[0]->newInstance()->name;
            if ($name !== Generator::UNDEFINED) { /** @phpstan-ignore-line */
                return str_ends_with($name, '[]') ? substr($name, 0, -2) : $name;
            }
        }

        return $property->getName();
    }
}
