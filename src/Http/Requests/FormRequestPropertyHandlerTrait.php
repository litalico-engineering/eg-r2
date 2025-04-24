<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;

/**
 * If the OpenApi attribute is embedded in the form request class along  with the Property addition,
 * the magic method of the Form Request class will be overridden,
 * but this Trait will reflect the request for its own added Property.
 * @package Litalico\EgR2\Http\Requests
 * Ignore PHPStan error as this is called from production code.
 * @phpstan-ignore-next-line
 */
trait FormRequestPropertyHandlerTrait
{
    /**
     * Handle a passed validation attempt.
     * @see https://readouble.com/laravel/10.x/en/validation.html#preparing-input-for-validation
     * @return void
     * @throws ReflectionException
     */
    protected function passedValidation(): void
    {
        $reflection = new ReflectionClass(self::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Ignore public field of parent class.
            if ($property->getDeclaringClass()->getName() !== self::class) {
                continue;
            }

            $value = request($property->getName());
            $type = $property->getType();
            if ($value === null) {
                $value = $this->initialValue($type);
            } else {
                if (!$type->isBuiltin()) {
                    $value = $this->initializationFormRequest($type->getName(), $value);
                }
            }
            $property->setValue($this, $value);
        }
    }

    /**
     * @param ReflectionType|null $type
     * @return mixed|null
     * @throws ReflectionException
     */
    private function initialValue(?ReflectionType $type)
    {
        if ($type instanceof ReflectionNamedType) {
            if ($type->allowsNull()) {
                return null;
            } else {
                if ($type->isBuiltin()) {
                    return match ($type->getName()) {
                        'array' => [],
                        'int' => 0,
                        default => ''
                    };
                } else {
                    return $this->initializationFormRequest($type->getName());
                }
            }
        }

        return null;
    }

    /**
     * @param class-string<FormRequest> $class
     * @param array $requestValues
     * @return FormRequest
     * @throws ReflectionException
     */
    private function initializationFormRequest(string $class, array $requestValues = []): FormRequest
    {
        $instance = new $class();

        if (!($instance instanceof FormRequest)) {
            throw new InvalidArgumentException("The class must be an instance of FormRequest. {$class} was given");
        }

        $reflection = new ReflectionClass($class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            // Ignore public field of parent class.
            if ($property->getDeclaringClass()->getName() !== $class) {
                continue;
            }

            $type = $property->getType();
            if (isset($requestValues[$property->getName()])) {
                $property->setValue($instance, $requestValues[$property->getName()]);
            } else {
                $property->setValue($instance, $this->initialValue($type));
            }
        }

        return $instance;
    }
}
