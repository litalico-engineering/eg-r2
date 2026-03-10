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
use ReflectionProperty;
use function in_array;
use function iterator_to_array;

/**
 * A trait that automatically generates the attributes() method for Laravel FormRequest classes
 * based on OpenAPI Property/Schema/Parameter attributes.
 *
 * Processing flow:
 *  1. generateAttributeEntries() — iterates public properties of the FormRequest class
 *  2. flattenProperty()          — dispatches to one of 3 pattern methods:
 *       - flattenNestedFormRequest()  — nested class with #[Schema]
 *       - flattenPropertyAttribute()  — #[Property]
 *       - flattenSchemaAttribute()    — #[Schema] + optional #[Parameter]
 *  3. flattenSchema()            — recursively walks the schema tree and yields dot-notation entries
 *  4. resolveLabel()             — determines the localized display label based on nesting context
 *
 * @package Litalico\EgR2\Http\Requests
 * @phpstan-ignore-next-line
 */
trait RequestAttributesGeneratorTrait
{
    /**
     * Generate attributes array from OpenAPI Property/Schema/Parameter attributes.
     * This method can be called from the attributes() method to get auto-generated labels.
     *
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function generatedAttributes(): array
    {
        return iterator_to_array($this->generateAttributeEntries());
    }

    /**
     * Default implementation of Laravel's FormRequest::attributes().
     *
     * Returns auto-generated attribute labels. Override this method in the
     * FormRequest class to merge or replace specific entries:
     *
     *   public function attributes(): array
     *   {
     *       return [...$this->generatedAttributes(), 'field' => 'custom label'];
     *   }
     *
     * @return array<string, string>
     * @throws ReflectionException
     */
    public function attributes(): array
    {
        return $this->generatedAttributes();
    }

    /**
     * Iterate public properties of the FormRequest class and yield attribute entries.
     *
     * @return \Generator<string, string>
     * @throws ReflectionException
     */
    private function generateAttributeEntries(): \Generator
    {
        $refClass = new ReflectionClass($this::class);
        $messageService = new AttributeMessageService();

        foreach ($refClass->getProperties() as $phpProperty) {
            if ($phpProperty->isPublic()) {
                yield from $this->flattenProperty($phpProperty, $messageService);
            }
        }
    }

    /**
     * Resolve OpenAPI attributes on a single PHP property and return attribute entries.
     *
     * Three mutually exclusive patterns (checked in priority order, first match wins):
     *  Pattern 1: Property type is a class with #[Schema] → nested FormRequest
     *  Pattern 2: Property has #[Property] → self-contained field definition
     *  Pattern 3: Property has #[Schema] + optional #[Parameter] → combined definition
     *
     * @param ReflectionProperty $phpProperty
     * @param AttributeMessageService $messageService
     * @return array<string, string>
     * @throws ReflectionException
     */
    private function flattenProperty(ReflectionProperty $phpProperty, AttributeMessageService $messageService): array
    {
        return $this->flattenNestedFormRequest($phpProperty, $messageService)
            ?? $this->flattenPropertyAttribute($phpProperty, $messageService)
            ?? $this->flattenSchemaAttribute($phpProperty, $messageService)
            ?? [];
    }

    /**
     * Pattern 1: Property type is a class with #[Schema] on the class.
     *
     * Reads inner properties of the nested class and flattens them
     * under the parent property name as a prefix.
     *
     * @param ReflectionProperty $phpProperty
     * @param AttributeMessageService $messageService
     * @return array<string, string>|null Null if this pattern does not apply
     * @throws ReflectionException
     */
    private function flattenNestedFormRequest(ReflectionProperty $phpProperty, AttributeMessageService $messageService): ?array
    {
        $reflectionType = $phpProperty->getType();
        if (
            $reflectionType === null ||
            $reflectionType->isBuiltin() ||
            !$reflectionType instanceof ReflectionNamedType
        ) {
            return null;
        }

        $nestedClass = new ReflectionClass($reflectionType->getName());
        if ($nestedClass->getAttributes(Schema::class) === []) {
            return null;
        }

        $result = [];
        $parentNames = [$phpProperty->getName()];
        foreach ($nestedClass->getProperties() as $innerProperty) {
            $attributeProperties = $innerProperty->getAttributes(Property::class);
            if ($attributeProperties !== []) {
                /** @var Property $nestedSchemaProperty */
                $nestedSchemaProperty = $attributeProperties[0]->newInstance();
                $result += iterator_to_array($this->flattenSchema(
                    $nestedSchemaProperty,
                    $messageService,
                    $innerProperty->getName(),
                    $this->resolveDescription($nestedSchemaProperty),
                    $parentNames,
                    false,
                    ''
                ));
            }
        }

        return $result;
    }

    /**
     * Pattern 2: Property has #[Property] attribute.
     *
     * The Property attribute is self-contained with field name, description, and type.
     * The PHP property name is used as the field name.
     *
     * @param ReflectionProperty $phpProperty
     * @param AttributeMessageService $messageService
     * @return array<string, string>|null Null if this pattern does not apply
     */
    private function flattenPropertyAttribute(ReflectionProperty $phpProperty, AttributeMessageService $messageService): ?array
    {
        $attributes = $phpProperty->getAttributes(Property::class);
        if ($attributes === []) {
            return null;
        }

        /** @var Property $property */
        $property = $attributes[0]->newInstance();

        return iterator_to_array($this->flattenSchema(
            $property,
            $messageService,
            $phpProperty->getName(),
            $this->resolveDescription($property),
            [],
            false,
            ''
        ));
    }

    /**
     * Pattern 3: Property has #[Schema] with an optional #[Parameter].
     *
     * Schema provides structural info (type, maxLength, etc.).
     * Parameter provides the display name and description.
     * When both are present, Parameter values take priority for name/description.
     *
     * @param ReflectionProperty $phpProperty
     * @param AttributeMessageService $messageService
     * @return array<string, string>|null Null if this pattern does not apply
     */
    private function flattenSchemaAttribute(ReflectionProperty $phpProperty, AttributeMessageService $messageService): ?array
    {
        $schemaAttributes = $phpProperty->getAttributes(Schema::class);
        if ($schemaAttributes === []) {
            return null;
        }

        /** @var Schema $schema */
        $schema = $schemaAttributes[0]->newInstance();

        $propertyName = $this->resolvePropertyName($schema);
        $description = $this->resolveDescription($schema);

        $parameterAttributes = $phpProperty->getAttributes(Parameter::class);
        if ($parameterAttributes !== []) {
            /** @var Parameter $parameter */
            $parameter = $parameterAttributes[0]->newInstance();

            if ($this->isDefined($parameter->name)) {    /** @phpstan-ignore-line */
                $propertyName = $schema->type === 'array'
                    ? str_replace('[]', '', $parameter->name)
                    : $parameter->name;
            }
            if (!$this->isDefined($schema->description) && $this->isDefined($parameter->description)) {  /** @phpstan-ignore-line */
                $description = $parameter->description;
            }
        }

        return iterator_to_array($this->flattenSchema(
            $schema,
            $messageService,
            $propertyName,
            $description,
            [],
            false,
            ''
        ));
    }

    /**
     * Recursively walk the schema tree and yield dot-notation attribute entries.
     *
     * - array type:  yields the field itself, a wildcard (.*) entry, then recurses into items
     * - object type: yields the field itself, then recurses into properties
     * - leaf type:   yields the field immediately
     *
     * @param AnnotationSchema $schema Schema node (used only for structural traversal: type, items, properties)
     * @param AttributeMessageService $messageService
     * @param string $propertyName Resolved field name for dot-notation path
     * @param string $description Resolved display label
     * @param array<string> $parentNames Accumulated parent path segments
     * @param bool $isArrayItem Whether this node is inside an array item
     * @param string $rootArrayDescription Root array's description, carried through for nested label formatting
     * @return \Generator<string, string>
     */
    private function flattenSchema(
        AnnotationSchema $schema,
        AttributeMessageService $messageService,
        string $propertyName,
        string $description,
        array $parentNames,
        bool $isArrayItem,
        string $rootArrayDescription
    ): \Generator {
        $currentPath = $parentNames !== [] ? [...$parentNames, $propertyName] : [$propertyName];
        $pathKey = implode('.', $currentPath);

        if ($schema->type === 'array') {
            yield $pathKey => $this->resolveLabel($messageService, $isArrayItem, $parentNames, $rootArrayDescription, $description, false);

            $wildcardPath = [...$currentPath, '*'];
            $wildcardKey = implode('.', $wildcardPath);
            yield $wildcardKey => $this->resolveLabel($messageService, $isArrayItem, $parentNames, $rootArrayDescription, $description, true);

            // Recurse into nested properties in array items
            $newRootDescription = $isArrayItem && $this->isInsideArrayWildcard($parentNames) ? $rootArrayDescription : $description;

            if ($schema->items->properties !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                foreach ($schema->items->properties as $innerProperty) {
                    yield from $this->flattenSchema(
                        $innerProperty,
                        $messageService,
                        $this->resolvePropertyName($innerProperty),
                        $this->resolveDescription($innerProperty),
                        $wildcardPath,
                        true,
                        $newRootDescription
                    );
                }
            }
        } elseif ($schema->type === 'object') {
            yield $pathKey => $description;

            // Recurse into nested properties in object
            if ($schema->properties !== Generator::UNDEFINED) {  /** @phpstan-ignore-line */
                foreach ($schema->properties as $innerProperty) {
                    yield from $this->flattenSchema(
                        $innerProperty,
                        $messageService,
                        $this->resolvePropertyName($innerProperty),
                        $this->resolveDescription($innerProperty),
                        $currentPath,
                        $isArrayItem,
                        $rootArrayDescription !== '' ? $rootArrayDescription : ($isArrayItem ? $description : '')
                    );
                }
            }
        } else {
            // Leaf property — yield immediately
            yield $pathKey => $this->resolveLabel($messageService, $isArrayItem, $currentPath, $rootArrayDescription, $description, false);
        }
    }

    /**
     * Resolve the field name from a schema node.
     *
     * For Property: uses the property name.
     * For Schema: uses the title.
     *
     * @param AnnotationSchema $schema
     * @return string
     */
    private function resolvePropertyName(AnnotationSchema $schema): string
    {
        if ($schema instanceof Property) {
            if (
                $this->isDefined($schema->property) &&  /** @phpstan-ignore-line */
                $schema->property !== ''
            ) {
                return $schema->property;
            }

            return '';
        }

        if ($this->isDefined($schema->title)) {  /** @phpstan-ignore-line */
            return $schema->title;
        }

        return '';
    }

    /**
     * Resolve the display description from a schema node.
     *
     * Fallback priority: description → title → property name (Property only).
     *
     * @param AnnotationSchema $schema
     * @return string
     */
    private function resolveDescription(AnnotationSchema $schema): string
    {
        if ($this->isDefined($schema->description)) {  /** @phpstan-ignore-line */
            return $schema->description;
        }

        if ($this->isDefined($schema->title)) {  /** @phpstan-ignore-line */
            return $schema->title;
        }

        if ($schema instanceof Property) {
            return $schema->property;
        }

        return '';
    }

    /**
     * Determine the localized display label based on array nesting context.
     *
     * When inside a nested array (path contains *), generates positional labels like
     * "itemsの :position 行目の「code」". Otherwise returns the description as-is
     * or wraps it in an array-items format like "itemsの各項目".
     *
     * @param AttributeMessageService $messageService
     * @param bool $isArrayItem Whether currently inside an array item
     * @param array<string> $pathForCheck Path to check for wildcard (*) presence
     * @param string $rootArrayDescription Root array's description for nested label formatting
     * @param string $description The field's own description
     * @param bool $isWildcard Whether this label is for a wildcard (.*) entry
     * @return string
     */
    private function resolveLabel(
        AttributeMessageService $messageService,
        bool $isArrayItem,
        array $pathForCheck,
        string $rootArrayDescription,
        string $description,
        bool $isWildcard
    ): string {
        if ($isArrayItem && $this->isInsideArrayWildcard($pathForCheck)) {
            $rootArrayName = $this->getRootArrayDescription($pathForCheck, $rootArrayDescription);

            return $isWildcard
                ? $messageService->formatNestedArrayItemsLabel($rootArrayName, $description)
                : $messageService->formatNestedArrayItemLabel($rootArrayName, $description);
        }

        return $isWildcard
            ? $messageService->formatArrayItemsLabel($description)
            : $description;
    }

    /**
     * Check whether a path contains an array wildcard (*) segment.
     *
     * @param array<string> $path
     * @return bool
     */
    private function isInsideArrayWildcard(array $path): bool
    {
        return in_array('*', $path, true);
    }

    /**
     * Check whether an OpenAPI attribute value is defined (not UNDEFINED and not null).
     *
     * OpenAPI library uses Generator::UNDEFINED as a sentinel for unset values.
     *
     * @param mixed $value
     * @return bool
     */
    private function isDefined(mixed $value): bool
    {
        return $value !== Generator::UNDEFINED && $value !== null;  /** @phpstan-ignore-line */
    }

    /**
     * Get the root array's description for nested label formatting.
     *
     * If an explicit rootDescription is available, returns it.
     * Otherwise extracts the path segments before the first wildcard (*)
     * and joins them with dots as a fallback.
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

        // Collect path segments before the first wildcard
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
