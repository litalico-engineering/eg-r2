<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Requests;

use Litalico\EgR2\Services\AttributeMessageService;
use OpenApi\Annotations\Schema as AnnotationSchema;
use OpenApi\Attributes\Parameter;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use function in_array;

/**
 * A trait that automatically generates the attributes() method for Laravel FormRequest classes
 * based on OpenAPI Property attributes.
 *
 * This trait provides a generatedAttributes() method that can be called from the attributes() method
 * to get auto-generated attribute labels from OpenAPI definitions.
 *
 * @package Litalico\EgR2\Http\Requests
 * Ignore PHPStan error as this is called from production code.
 * @phpstan-ignore-next-line
 */
trait RequestAttributesGeneratorTrait
{
    /**
     * Generate attributes array from OpenAPI Property attributes.
     * This method can be called from the attributes() method to get auto-generated labels.
     *
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function generatedAttributes(): array
    {
        $attributes = [];
        $refClass = new ReflectionClass($this::class);

        // Create message service once and reuse throughout the process
        $messageService = new AttributeMessageService();

        foreach ($refClass->getProperties() as $phpProperty) {
            if (!$phpProperty->isPublic()) {
                continue;
            }

            $reflectionType = $phpProperty->getType();

            // Handle nested FormRequest classes
            if (
                $reflectionType !== null &&
                !$reflectionType->isBuiltin() &&
                $reflectionType instanceof ReflectionNamedType
            ) {
                $typeName = $reflectionType->getName();
                $nestedClass = new ReflectionClass($typeName);

                $reflectionAttributes = $nestedClass->getAttributes(Schema::class);
                if ($reflectionAttributes !== []) {
                    $parentNames = [$phpProperty->getName()];
                    foreach ($nestedClass->getProperties() as $innerProperty) {
                        $attributeProperties = $innerProperty->getAttributes(Property::class);
                        if ($attributeProperties !== []) {
                            /** @var Property $nestedSchemaProperty */
                            $nestedSchemaProperty = $attributeProperties[0]->newInstance();
                            $attributes += $this->parseSchemaForAttributes($nestedSchemaProperty, $messageService, $parentNames);
                        }
                    }

                    continue;
                }
            }

            /** @var Schema|null $schema */
            $schema = null;
            /** @var Parameter|null $parameter */
            $parameter = null;

            foreach ($phpProperty->getAttributes() as $attribute) {
                $obj = $attribute->newInstance();

                if ($obj instanceof Property) {
                    $attributes += $this->parseSchemaForAttributes($obj, $messageService);
                } elseif ($obj instanceof Schema) {
                    $schema = $obj;
                } elseif ($obj instanceof Parameter) {
                    $parameter = $obj;
                }
            }

            if ($schema !== null) {
                // Combination of Schema and Parameter
                if ($parameter !== null) {
                    if ($parameter->name !== Generator::UNDEFINED) {    /** @phpstan-ignore-line */
                        $schema->title = $schema->type === 'array'
                            ? str_replace('[]', '', $parameter->name)
                            : $parameter->name;
                    }
                    // Copy description from Parameter if Schema doesn't have one
                    if (
                        ($schema->description === Generator::UNDEFINED || $schema->description === null) &&  /** @phpstan-ignore-line */
                        $parameter->description !== Generator::UNDEFINED &&  /** @phpstan-ignore-line */
                        $parameter->description !== null
                    ) {
                        $schema->description = $parameter->description;
                    }
                }
                $attributes += $this->parseSchemaForAttributes($schema, $messageService);
            }
        }

        return $attributes;
    }

    /**
     * Return the auto-generated attributes array.
     *
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function attributes(): array
    {
        return $this->generatedAttributes();
    }

    /**
     * Recursively parse schema to generate attributes array.
     *
     * @param AnnotationSchema $schema
     * @param AttributeMessageService $messageService The attribute message service instance
     * @param array<string> $parentNames
     * @param bool $isArrayItem Whether this is inside an array item (for :position placeholder)
     * @param string $rootArrayDescription Root array description for nested items
     * @return array<string, string>
     */
    private function parseSchemaForAttributes(
        AnnotationSchema $schema,
        AttributeMessageService $messageService,
        array $parentNames = [],
        bool $isArrayItem = false,
        string $rootArrayDescription = ''
    ): array {
        $result = [];

        $propertyName = $this->getPropertyNameForAttributes($schema);
        $description = $this->getDescriptionFromSchema($schema);

        if ($schema->type === 'array') {
            // Add attribute for the array itself
            $currentPath = $parentNames !== [] ? [...$parentNames, $propertyName] : [$propertyName];

            // For nested arrays inside array items, use special format
            if ($isArrayItem && $this->isInsideArrayWildcard($parentNames)) {
                $rootArrayName = $this->getRootArrayDescription($parentNames, $rootArrayDescription);
                $result[implode('.', $currentPath)] = $messageService->formatNestedArrayItemLabel($rootArrayName, $description);
            } else {
                $result[implode('.', $currentPath)] = $description;
            }

            // Add wildcard entry for array items
            $wildcardPath = [...$currentPath, '*'];

            if ($isArrayItem && $this->isInsideArrayWildcard($parentNames)) {
                $rootArrayName = $this->getRootArrayDescription($parentNames, $rootArrayDescription);
                $result[implode('.', $wildcardPath)] = $messageService->formatNestedArrayItemsLabel($rootArrayName, $description);
            } else {
                $result[implode('.', $wildcardPath)] = $messageService->formatArrayItemsLabel($description);
            }

            // Store root array description for nested items
            // For nested arrays, use the immediate parent's description as reference
            $newRootDescription = $isArrayItem && $this->isInsideArrayWildcard($parentNames) ? $rootArrayDescription : $description;

            // Process nested properties in array items
            if ($schema->items->properties !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                foreach ($schema->items->properties as $innerProperty) {
                    $result += $this->parseSchemaForAttributes($innerProperty, $messageService, $wildcardPath, true, $newRootDescription);
                }
            }
        } elseif ($schema->type === 'object') {
            // Add attribute for the object itself
            $currentPath = $parentNames !== [] ? [...$parentNames, $propertyName] : [$propertyName];
            $result[implode('.', $currentPath)] = $description;

            // Process nested properties in object
            if ($schema->properties !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                foreach ($schema->properties as $innerProperty) {
                    // Pass along the root array description even inside objects
                    $result += $this->parseSchemaForAttributes($innerProperty, $messageService, $currentPath, $isArrayItem, $rootArrayDescription !== '' ? $rootArrayDescription : ($isArrayItem ? $description : ''));
                }
            }
        } else {
            // Simple property
            $currentPath = $parentNames !== [] ? [...$parentNames, $propertyName] : [$propertyName];

            // Add :position placeholder for nested array items
            if ($isArrayItem && $this->isInsideArrayWildcard($currentPath)) {
                $rootArrayName = $this->getRootArrayDescription($currentPath, $rootArrayDescription);
                $finalDescription = $messageService->formatNestedArrayItemLabel($rootArrayName, $description);
                $result[implode('.', $currentPath)] = $finalDescription;
            } else {
                $result[implode('.', $currentPath)] = $description;
            }
        }

        return $result;
    }

    /**
     * Get property name from schema.
     *
     * @param AnnotationSchema $schema
     * @return string
     */
    private function getPropertyNameForAttributes(AnnotationSchema $schema): string
    {
        if ($schema instanceof Property) {
            // If title is set, use it as the key name
            if ($schema->title !== Generator::UNDEFINED && $schema->title !== null) {  /** @phpstan-ignore-line */
                return $schema->title;
            }

            return $schema->property;
        }

        return $schema->title;
    }

    /**
     * Get description from schema with fallback priority: description -> title -> property name.
     *
     * @param AnnotationSchema $schema
     * @return string
     */
    private function getDescriptionFromSchema(AnnotationSchema $schema): string
    {
        // Priority 1: description
        if ($schema->description !== Generator::UNDEFINED && $schema->description !== null) {  /** @phpstan-ignore-line */
            return $schema->description;
        }

        // Priority 2: title (but not for Property with title, use property name instead)
        if ($schema instanceof Property) {
            // For Property, if no description, use property name (not title)
            return $schema->property;
        }

        if ($schema->title !== Generator::UNDEFINED && $schema->title !== null) {  /** @phpstan-ignore-line */
            return $schema->title;
        }

        return '';
    }

    /**
     * Check if the path contains array wildcard (*).
     *
     * @param array<string> $path
     * @return bool
     */
    private function isInsideArrayWildcard(array $path): bool
    {
        return in_array('*', $path, true);
    }

    /**
     * Get the root array field description from the path.
     *
     * @param array<string> $path
     * @param string $rootDescription
     * @return string
     */
    private function getRootArrayDescription(array $path, string $rootDescription): string
    {
        if ($rootDescription !== '') {
            return $rootDescription;
        }

        // Find the first non-wildcard part
        $fieldParts = [];
        foreach ($path as $part) {
            if ($part === '*') {
                break;
            }
            $fieldParts[] = $part;
        }

        return implode('.', $fieldParts);
    }
}
