<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Requests;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionProperty;

/**
 * If the OpenApi attribute is embedded in the form request class along  with the Property addition,
 * the magic method of the Form Request class will be overridden,
 * but this Trait will reflect the request for its own added Property.
 * @package Litalico\EgR2\Http\Requests
 */
trait FormRequestPropertyHandlerTrait
{
    /**
     * Handle a passed validation attempt.
     * @see https://readouble.com/laravel/10.x/en/validation.html#preparing-input-for-validation
     * @return void
     */
    protected function passedValidation(): void
    {
        $reflection = new ReflectionClass(self::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Only primitive types take over parameters
            if (!$property->hasType()) {
                continue;
            }

            $value = request($property->getName());
            if ($value === null) {
                $type = $property->getType();
                if ($type instanceof ReflectionNamedType) {
                    if ($type->allowsNull()) {
                        // TODO: If an item specified as nullable or default in the OpenAPI specification is not entered, set the default value to Property.
                        $value = null;
                    } else {
                        $value = match ($type->getName()) {
                            'array' => [],
                            'int' => 0,
                            default => ''
                        };
                    }
                } else {
                    $value = '';
                }
            }
            $property->setValue($this, $value);
        }
    }
}
