<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use Litalico\EgR2\Rules\Integer;
use OpenApi\Annotations\Schema as AnnotationSchema;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionProperty;
use function count;
use function in_array;
use function is_array;
use function is_string;

/**
 * A trait used to generate Laravel validation rules from OpenApiAttributes.
 * @package Litalico\EgR2\Http\Requests
 */
trait RequestRuleGeneratorTrait
{
    /**
     * Returns Laravel validation rules converted from OpenApiAttributes.
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Enum|\Illuminate\Validation\Rules\In|int|string>>
     * @throws ReflectionException
     */
    public function rules(): array
    {
        return $this->convertRules();
    }

    /**
     * Converts OpenApiAttributes to Laravel Validation Rules and returns them as an array.
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Enum|\Illuminate\Validation\Rules\In|int|string>>
     * @throws ReflectionException
     */
    private function convertRules(): array
    {
        $rules = [];
        $refClass = new ReflectionClass($this::class);
        $requires = $this->parseSchemaRequired($refClass);

        $errorMessages = [];

        // property attribute
        foreach ($refClass->getProperties() as $phpProperty) {
            if (!$phpProperty->isPublic()) {
                // Exclude inherited FormRequest-related Properties。
                continue;
            }

            /** var Schema $schema */
            $schema = null;
            /** var Parameter $parameter */
            $parameter = null;
            foreach ($phpProperty->getAttributes() as $attribute) {
                $obj = $attribute->newInstance();

                if (is_a($obj, Property::class)) {
                    $rules += $this->parseSchema($obj, requires: $requires);
                    $errorMessages = array_merge(
                        $errorMessages,
                        $this->checkDiffInPropertyAndSchema(
                            $phpProperty,
                            $obj->nullable === true,
                            $obj->type
                        )
                    );
                } elseif (is_a($obj, Schema::class)) {
                    $schema = $obj;
                } elseif (is_a($obj, Parameter::class)) {
                    $parameter = $obj;
                }
            }
            if ($schema !== null) {
                // Combination of Schema and Parameter is processed after merging.
                if ($parameter !== null) {
                    if ($parameter->name !== Generator::UNDEFINED) {    /** @phpstan-ignore-line */
                        // If the Schema is an array, assign the parameter names without [].
                        // (Because a [] at the end will cause a validation error)
                        $schema->title = $schema->type === 'array'
                            ? str_replace('[]', '', $parameter->name)
                            : $parameter->name;
                    }
                    if ($parameter->required === true) {
                        $requires[] = $schema->title;
                    }
                    // Manual copy because subsequent merge process does not include x
                    if ($parameter->x !== Generator::UNDEFINED) {   /** @phpstan-ignore-line */
                        $schema->x = $parameter->x;
                    }
                    $schema->merge([$parameter]);
                }
                $rules += $this->parseSchema($schema, requires: $requires);
                $errorMessages = array_merge(
                    $errorMessages,
                    $this->checkDiffInPropertyAndSchema(
                        $phpProperty,
                        $schema->nullable === true,
                        $schema->type
                    )
                );
            }
        }

        // Error if there is a conflict between the property and OpenAPI definition
        if (count($errorMessages) >= 1) {
            throw new InvalidOpenApiDefinitionException($errorMessages);
        }

        return $rules;
    }

    /**
     * Parses the required fields from the given reflection class's Schema.
     * This method recursively checks the traits of the given class and its own attributes.
     * It returns an array of required fields.
     * @template T of ReflectionClass
     * @param ReflectionClass $refClass The reflection class to parse the required fields from.
     * @return array<string> The required fields parsed from the Schema of the given reflection class.
     */
    private function parseSchemaRequired(ReflectionClass $refClass): array
    {
        $requires = [];

        // Obtain the trait attributes. This is necessary because class attributes cannot refer to inherited class attributes.
        $traits = $refClass->getTraits();
        foreach ($traits as $trait) {
            $requires = [
                ...$requires,
                ...$this->parseSchemaRequired($trait),
            ];
        }

        // Process the attributes of the reflection class itself.
        foreach ($refClass->getAttributes() as $attribute) {
            $obj = $attribute->newInstance();
            if (is_a($obj, Schema::class)) {
                if ($obj->required !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                    $requires = [
                        ...$requires,
                        ...$obj->required,
                    ];
                }
            }
        }

        return $requires;
    }

    /**
     * Compares the property and schema definitions and returns an array of error messages if there are discrepancies.
     * This method checks if the nullable definition and the type definition of the property and the schema match.
     * If they don't match, it adds an error message to the array for each discrepancy.
     *
     * @param ReflectionProperty $property The property to compare with the schema.
     * @param bool $schemaNullable Indicates whether the schema definition is nullable or not.
     * @param string|non-empty-array<string> $schemaTypeName The type definition of the schema.
     * @return list<string> An array of error messages indicating the discrepancies between the property and the schema.
     */
    private function checkDiffInPropertyAndSchema(
        ReflectionProperty $property,
        bool $schemaNullable,
        string|array $schemaTypeName,
    ): array {
        /** @var ReflectionNamedType $propertyType */
        $propertyType = $property->getType();
        $propertyTypeName = $propertyType->getName();
        $isPropertyNullable = $propertyType->allowsNull();

        $errorMessages = [];

        if ($isPropertyNullable !== $schemaNullable) {
            $errorMessages[] = "{$property->getName()}: Nullable definitions are different in property and schema. ";
        }

        if (
            $propertyTypeName === 'array' && !in_array($schemaTypeName, ['array', 'object'], true)
            || (is_string($schemaTypeName) && $propertyTypeName !== 'array' && !str_starts_with($schemaTypeName, $propertyTypeName))
        ) {
            $errorMessages[] = "{$property->getName()}: Type definitions are different in property and schema. ";
        }

        return $errorMessages;
    }

    /**
     * Recursively analyzes the given schema and generates Laravel validation rules.
     * This method handles different types of schemas (array, object, others) and generates appropriate validation rules.
     * It also takes into account the required fields in the schema.
     *
     * @param AnnotationSchema $schema The schema to analyze.
     * @param array<string> $parentNames An array of parent names used for nested schemas.
     * @param array<string> $requires An array of required fields in the schema.
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Enum|\Illuminate\Validation\Rules\In|int|string>> The generated Laravel validation rules.
     */
    private function parseSchema(AnnotationSchema $schema, array $parentNames = [], array $requires = []): array
    {
        $result = [];

        if ($schema->type === 'array') {
            // arraySet your own rules
            $required = in_array($this->getPropertyName($schema), $requires, true);
            $result += $this->convertRule($schema, $parentNames, $required);
            // Set rules for child elements contained in array
            $parentNames[] = $this->getPropertyName($schema) . '.*';
            if ($schema->items->properties !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                foreach ($schema->items->properties as $innerProperty) {
                    /** @phpstan-ignore-next-line To determine the default value Generator::UNDEFINED */
                    $requires = is_array($schema->items->required) ? $schema->items->required : [];
                    $result += $this->parseSchema($innerProperty, $parentNames, $requires);
                }
                /** @phpstan-ignore-next-line To determine the default value Generator::UNDEFINED */
            } else {
                $ruleName = implode('.', $parentNames + [$this->getPropertyName($schema)]);
                $result[$ruleName] = array_values($this->convertRule($schema->items))[0];
            }
        } elseif ($schema->type === 'object') {
            $propertyName = $this->getPropertyName($schema);
            $result += $this->convertRule($schema, $parentNames, in_array($propertyName, $requires, true));
            $parentNames[] = $propertyName;
            foreach ($schema->properties as $innerProperty) {
                /** @phpstan-ignore-next-line To determine the default value Generator::UNDEFINED */
                $requires = is_array($schema->required) ? $schema->required : [];
                $result += $this->parseSchema($innerProperty, $parentNames, $requires);
            }
        } else {
            $required = in_array($this->getPropertyName($schema), $requires, true);
            $result += $this->convertRule($schema, $parentNames, $required);
        }

        return $result;
    }

    /**
     * Retrieves the property name from the given Schema.
     * If the Schema is of type Property and has a defined title, the title is returned.
     * If the title is not defined, the property of the Schema is returned.
     * If the Schema is not of type Property, the title of the Schema is returned.
     *
     * @param AnnotationSchema $schema The schema to retrieve the property name from.
     * @return string The property name retrieved from the Schema.
     */
    private function getPropertyName(AnnotationSchema $schema): string
    {
        if (is_a($schema, Property::class)) {
            if ($schema->title !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                return $schema->title;
            }

            return $schema->property;
        }

        return $schema->title;
    }

    /**
     * Converts the given OpenApiSchema into Laravel validation rules.
     * This method analyzes the schema and generates appropriate validation rules based on its properties.
     * It takes into account whether the schema is nullable and whether it is required.
     *
     * @param AnnotationSchema $schema The OpenApiSchema to convert into Laravel validation rules.
     * @param array<string> $names An array of parent names used for nested schemas.
     * @param bool $required Indicates whether the schema is required or not.
     * @return array<string, list<\Illuminate\Contracts\Validation\ValidationRule|\Illuminate\Validation\Rules\Enum|\Illuminate\Validation\Rules\In|string>> The generated Laravel validation rules.
     */
    protected function convertRule(AnnotationSchema $schema, array $names = [], bool $required = false): array
    {
        $rules = [];

        // Check if the schema value is nullable or not
        if ($schema->nullable === true) {
            $rules[] = 'nullable';
        } elseif ($schema->nullable === false) {
            // nop
        }

        // Check if the field is required. If it is, ensure the key is present.
        if ($required) {
            if ($schema->type === 'object') {
                $rules[] = 'present';
            } else {
                // The path parameter etc. is treated as true for required, but the value may be null in some cases.
                if ($schema->nullable !== true) {
                    // If type is array, set to `present`.
                    $requireRule = $schema->type === 'array' ? 'present' : 'required';
                    // In the case of a nested structure, it is required only when the parent element exists.
                    $rules[] = $names !== [] ? 'required_with:' . implode('.', $names) : $requireRule;
                }
            }
        }

        // Specify the type of the schema
        if (in_array($schema->type, ['string', 'number', 'integer', 'boolean', 'array', 'object'], true)) {
            if ($schema->type === 'number') {
                $rules[] = 'numeric';
            } elseif ($schema->type === 'object') {
                $rules[] = 'array';
            } elseif ($schema->type === 'integer') {

                $rules[] = new Integer();
                $rules[] = 'integer';
            } else {
                $rules[] = $schema->type;
            }
        }

        // Apply specific Laravel validation rules based on the schema properties
        $ignorePattern = false;
        if ($schema->x !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
            foreach ($schema->x as $ruleName => $rule) {
                if ($ruleName === 'date_format') {
                    $rules[] = "$ruleName:$rule";
                    // The following specifications are included in the validation rules and therefore reset
                    $schema->maximum = Generator::UNDEFINED; /** @phpstan-ignore-line */
                    $schema->maxLength = Generator::UNDEFINED; /** @phpstan-ignore-line */
                    $schema->minimum = Generator::UNDEFINED; /** @phpstan-ignore-line */
                    $schema->minLength = Generator::UNDEFINED; /** @phpstan-ignore-line */
                    $schema->pattern = Generator::UNDEFINED; /** @phpstan-ignore-line */
                }
                if ($ruleName === 'mimes') {
                    // Overwrite type specification to file
                    array_pop($rules);
                    $rules[] = 'file';
                    $rules[] = "$ruleName:$rule";
                }
                if ($ruleName === 'pattern') {
                    // Prefer regular expressions for Laravel
                    $ignorePattern = true;
                    $rules[] = sprintf('regex:%s', $rule);
                }
                if ($ruleName === 'validation' && $rule !== '') {
                    $validationRules = match (true) {
                        is_array($rule) => [...$rule],
                        default => [$rule],
                    };
                    foreach ($validationRules as $validationRule) {
                        //　For ValidationRule class, instantiate and use it
                        if (class_exists($validationRule)) {
                            $validationRuleClass = new $validationRule();
                            if ($validationRuleClass instanceof ValidationRule) {
                                $rules[] = $validationRuleClass;
                            }
                        } else {
                            $rules[] = $validationRule;
                        }
                    }
                }
            }
        }

        // Apply enum
        if ($schema->enum !== Generator::UNDEFINED) {   /** @phpstan-ignore-line */
            if (is_string($schema->enum)) {
                $rules[] = new Enum($schema->enum);
            } else {
                $rules[] = Rule::in($schema->enum);
            }
        }

        // Maximum number
        if ($schema->maximum !== Generator::UNDEFINED) {    /** @phpstan-ignore-line */
            $rules[] = 'max:' . $schema->maximum;
        }

        // Maximum number of characters
        if ($schema->maxLength !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
            $rules[] = 'max:' . $schema->maxLength;
        }

        // Minimum number
        if ($schema->minimum !== Generator::UNDEFINED) {    /** @phpstan-ignore-line */
            $rules[] = 'min:' . $schema->minimum;
        }

        // Minimum number of characters
        if ($schema->minLength !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
            $rules[] = 'min:' . $schema->minLength;
        }

        // Maximum number of elements
        if ($schema->maxItems !== Generator::UNDEFINED) {   /** @phpstan-ignore-line */
            $rules[] = 'max:' . $schema->maxItems;
        }

        // Minimum number of elements
        if ($schema->minItems !== Generator::UNDEFINED) {   /** @phpstan-ignore-line */
            $rules[] = 'min:' . $schema->minItems;
        }

        // Regular expression
        if (!$ignorePattern &&
            $schema->pattern !== Generator::UNDEFINED   /** @phpstan-ignore-line */
        ) {
            $rules[] = sprintf('regex:/%s/', $schema->pattern);
        }

        $names[] = $this->getPropertyName($schema);

        $ruleName = implode('.', $names);

        return [$ruleName => $rules];
    }
}
