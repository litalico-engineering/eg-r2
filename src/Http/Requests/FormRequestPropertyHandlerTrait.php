<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use InvalidArgumentException;
use OpenApi\Attributes\Property;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use stdClass;
use function is_array;

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
        foreach ($this->getOwnPublicProperties() as $property) {
            $value = $this->getDefaultValueFromProperty($property);
            $property->setValue($this, $value);
        }
    }

    /**
     * Get public properties declared in the current class (excluding parent class properties).
     *
     * @return list<ReflectionProperty>
     */
    private function getOwnPublicProperties(): array
    {
        $properties = (new ReflectionClass(self::class))->getProperties(ReflectionProperty::IS_PUBLIC);

        $filtered = array_filter(
            $properties,
            static fn (ReflectionProperty $property) => $property->getDeclaringClass()->getName() === self::class
        );

        return array_values($filtered);
    }

    /**
     * Resolve the value for a property based on request data, default values, and type information.
     * Values are normalized to match the declared property type.
     *
     * @param ReflectionProperty $property
     * @return mixed
     * @throws ReflectionException
     */
    private function getDefaultValueFromProperty(ReflectionProperty $property): mixed
    {
        $requestValue = request($property->getName());
        $propertyType = $property->getType();

        if (
            $requestValue !== null &&
            $propertyType !== null &&
            !$propertyType->isBuiltin()
        ) {
            return $this->initializationFormRequest($propertyType->getName(), $requestValue);
        }

        if ($requestValue !== null) {
            return $this->normalizeValueToType($requestValue, $propertyType);
        }

        $defaultValue = $this->getPropertyDefaultValue($property);
        if ($defaultValue !== Generator::UNDEFINED && $defaultValue !== null) {
            return $this->normalizeValueToType($defaultValue, $propertyType);
        }

        if ($property->isInitialized($this)) {
            return $property->getValue($this);
        }

        return $this->initialValue($propertyType);
    }

    /**
     * Get the default value from the Property attribute if it exists.
     *
     * @param ReflectionProperty $property
     * @return mixed
     */
    private function getPropertyDefaultValue(ReflectionProperty $property): mixed
    {
        $attributes = $property->getAttributes(Property::class);

        if (!isset($attributes[0])) {
            return null;
        }

        $instance = $attributes[0]->newInstance();
        if ($instance->nullable === true) {
            return $instance->default;
        }

        return null;
    }

    /**
     * Normalize a value to match the declared property type.
     *
     * @param mixed $value
     * @param ReflectionType|null $type
     * @return mixed
     */
    private function normalizeValueToType(mixed $value, ReflectionType|null $type): mixed
    {
        if ($type === null) {
            return $value;
        }

        if (!($type instanceof ReflectionNamedType) || !$type->isBuiltin()) {
            return $value;
        }

        return match ($type->getName()) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value,
        };
    }

    /**
     * @param ReflectionType|null $type
     * @return mixed
     * @throws ReflectionException
     */
    private function initialValue(?ReflectionType $type): mixed
    {
        if ($type === null) {
            return null;
        }

        if ($type->allowsNull()) {
            return null;
        }

        if (!($type instanceof ReflectionNamedType)) {
            throw new InvalidArgumentException('The type must be an instance of ReflectionNamedType.');
        }

        if (!$type->isBuiltin()) {
            return $this->initializationFormRequest($type->getName());
        }

        return match ($type->getName()) {
            'array' => [],
            'int' => 0,
            'float' => 0.0,
            'object' => new stdClass(),
            'bool' => false,
            default => ''
        };
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

        $properties = (new ReflectionClass($class))
            ->getProperties(ReflectionProperty::IS_PUBLIC);

        // Ignore public field of parent class.
        $filteredProperties = array_filter(
            $properties,
            static fn (ReflectionProperty $property) => $property->getDeclaringClass()->getName() === $class
        );

        foreach ($filteredProperties as $property) {
            $type = $property->getType();

            $propertyName = $property->getName();
            if (!isset($requestValues[$propertyName])) {
                $value = $this->initialValue($type);
            } else {
                $rawValue = $requestValues[$propertyName];
                $value = $this->normalizeValueToType($rawValue, $type);
            }

            $property->setValue($instance, $value);
        }

        return $instance;
    }
}
