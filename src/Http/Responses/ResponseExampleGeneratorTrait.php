<?php

declare(strict_types=1);

namespace Litalico\EgR2\Http\Responses;

use BackedEnum;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use OpenApi\Annotations\Schema as AnnotationSchema;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionNamedType;
use UnitEnum;
use function abs;
use function array_fill;
use function array_key_first;
use function ceil;
use function count;
use function filter_var;
use function floor;
use function is_array;
use function is_bool;
use function is_numeric;
use function is_string;
use function max;
use function min;
use function preg_match;
use function preg_match_all;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_repeat;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Generates deterministic response examples from inline OpenAPI schemas.
 *
 * @package Litalico\EgR2\Http\Responses
 * @phpstan-ignore-next-line
 */
trait ResponseExampleGeneratorTrait
{
    /**
     * @return array<string, mixed>
     */
    public function generatedExample(): array
    {
        $reflection = new ReflectionClass($this);
        $classAttributes = $reflection->getAttributes(Schema::class);

        if ($classAttributes !== []) {
            /** @var Schema $classSchema */
            $classSchema = $classAttributes[0]->newInstance();
            if ($this->isDefined($classSchema->example)) {
                if (!is_array($classSchema->example)) {
                    $this->invalid('$', 'A class-level response example must be an array.');
                }

                return $classSchema->example;
            }

            if ($this->isDefined($classSchema->properties)) {
                return $this->generateProperties($classSchema->properties, '$');
            }
        }

        $example = [];
        foreach ($reflection->getProperties() as $phpProperty) {
            if (!$phpProperty->isPublic()) {
                continue;
            }

            $attributes = $phpProperty->getAttributes(Property::class);
            if ($attributes === []) {
                continue;
            }

            /** @var Property $property */
            $property = $attributes[0]->newInstance();
            $name = $this->isDefined($property->property) && $property->property !== ''
                ? $property->property
                : $phpProperty->getName();
            $reflectionType = $phpProperty->getType();
            $fallbackType = $reflectionType instanceof ReflectionNamedType && $reflectionType->isBuiltin()
                ? $reflectionType->getName()
                : null;
            $example[$name] = $this->generateSchemaValue($property, '$.' . $name, $fallbackType);
        }

        return $example;
    }

    /**
     * @param array<AnnotationSchema> $properties
     * @return array<string, mixed>
     */
    private function generateProperties(array $properties, string $path): array
    {
        $example = [];
        foreach ($properties as $property) {
            if (!$property instanceof AnnotationSchema) {
                continue;
            }

            if (!$property instanceof Property || !$this->isDefined($property->property) || $property->property === '') {
                $this->invalid($path, 'Inline response properties must define a property name.');
            }

            $name = $property->property;
            $example[$name] = $this->generateSchemaValue($property, $path . '.' . $name);
        }

        return $example;
    }

    private function generateSchemaValue(AnnotationSchema $schema, string $path, ?string $fallbackType = null): mixed
    {
        if ($this->isDefined($schema->example)) {
            return $schema->example;
        }

        if ($this->isDefined($schema->enum)) {
            $enum = $this->firstEnumValue($schema->enum, $path);
            if ($enum['found']) {
                return $enum['value'];
            }
        }

        if ($this->isDefined($schema->default)) {
            return $schema->default;
        }

        if ($this->isDefined($schema->ref)) {
            $this->invalid($path, 'Schema references require an OpenAPI analysis context and cannot be resolved by this trait.');
        }

        foreach (['allOf', 'anyOf', 'oneOf'] as $composition) {
            if ($this->isDefined($schema->{$composition})) {
                $this->invalid($path, sprintf('Schema composition "%s" is not supported by this trait.', $composition));
            }
        }

        $type = $this->resolveType($schema, $fallbackType);

        return match ($type) {
            'object' => $this->isDefined($schema->properties)
                ? $this->generateProperties($schema->properties, $path)
                : [],
            'array' => $this->generateArray($schema, $path),
            'string' => $this->generateString($schema, $path),
            'integer' => $this->generateNumber($schema, $path, true),
            'number' => $this->generateNumber($schema, $path, false),
            'boolean', 'bool' => true,
            'null' => null,
            default => $this->invalid($path, sprintf('Cannot generate an example for OpenAPI type "%s".', $type)),
        };
    }

    private function resolveType(AnnotationSchema $schema, ?string $fallbackType): string
    {
        if ($this->isDefined($schema->type)) {
            if (is_array($schema->type)) {
                foreach ($schema->type as $type) {
                    if ($type !== 'null') {
                        return $type;
                    }
                }

                return 'null';
            }

            return $schema->type;
        }

        if ($this->isDefined($schema->properties)) {
            return 'object';
        }

        if ($this->isDefined($schema->items)) {
            return 'array';
        }

        return match ($fallbackType) {
            'int' => 'integer',
            'float' => 'number',
            'bool' => 'boolean',
            default => $fallbackType ?? '',
        };
    }

    /**
     * @return array<int, mixed>
     */
    private function generateArray(AnnotationSchema $schema, string $path): array
    {
        $minimum = $this->isDefined($schema->minItems) ? max(0, (int) $schema->minItems) : 1;
        $maximum = $this->isDefined($schema->maxItems) ? max(0, (int) $schema->maxItems) : null;
        if ($maximum !== null && $minimum > $maximum) {
            $this->invalid($path, 'Array minItems is greater than maxItems.');
        }

        $count = $maximum === null ? $minimum : min($minimum, $maximum);

        if ($count === 0) {
            return [];
        }

        if (!$this->isDefined($schema->items) || !$schema->items instanceof AnnotationSchema) {
            $this->invalid($path, 'Array schemas must define items.');
        }

        return array_fill(0, $count, $this->generateSchemaValue($schema->items, $path . '[]'));
    }

    private function generateNumber(AnnotationSchema $schema, string $path, bool $integer): int|float
    {
        $minimum = $this->isDefined($schema->minimum) ? (float) $schema->minimum : null;
        $maximum = $this->isDefined($schema->maximum) ? (float) $schema->maximum : null;

        if ($this->isDefined($schema->exclusiveMinimum)) {
            if (is_bool($schema->exclusiveMinimum)) {
                if ($schema->exclusiveMinimum && $minimum === null) {
                    $this->invalid($path, 'exclusiveMinimum requires minimum when expressed as a boolean.');
                }
                if ($schema->exclusiveMinimum) {
                    $minimum = $this->nextNumber($minimum, $integer);
                }
            } else {
                $exclusiveMinimum = $this->nextNumber((float) $schema->exclusiveMinimum, $integer);
                $minimum = $minimum === null ? $exclusiveMinimum : max($minimum, $exclusiveMinimum);
            }
        }
        if ($this->isDefined($schema->exclusiveMaximum)) {
            if (is_bool($schema->exclusiveMaximum)) {
                if ($schema->exclusiveMaximum && $maximum === null) {
                    $this->invalid($path, 'exclusiveMaximum requires maximum when expressed as a boolean.');
                }
                if ($schema->exclusiveMaximum) {
                    $maximum = $this->previousNumber($maximum, $integer);
                }
            } else {
                $exclusiveMaximum = $this->previousNumber((float) $schema->exclusiveMaximum, $integer);
                $maximum = $maximum === null ? $exclusiveMaximum : min($maximum, $exclusiveMaximum);
            }
        }

        $value = $minimum ?? (($maximum !== null && $maximum < 0) ? $maximum : 0.0);
        if ($maximum !== null && $value > $maximum) {
            $this->invalid($path, 'Numeric minimum is greater than maximum.');
        }

        if ($integer) {
            $value = ceil($value);
            if ($maximum !== null && $value > $maximum) {
                $this->invalid($path, 'Numeric bounds contain no integer value.');
            }

            return (int) $value;
        }

        return $value;
    }

    private function nextNumber(float $number, bool $integer): float
    {
        return $integer
            ? floor($number) + 1
            : $number + max(1, abs($number)) * PHP_FLOAT_EPSILON;
    }

    private function previousNumber(float $number, bool $integer): float
    {
        return $integer
            ? ceil($number) - 1
            : $number - max(1, abs($number)) * PHP_FLOAT_EPSILON;
    }

    private function generateString(AnnotationSchema $schema, string $path): string
    {
        $minimum = $this->isDefined($schema->minLength) ? max(0, (int) $schema->minLength) : 0;
        $maximum = $this->isDefined($schema->maxLength) ? max(0, (int) $schema->maxLength) : null;
        if ($maximum !== null && $minimum > $maximum) {
            $this->invalid($path, 'String minLength is greater than maxLength.');
        }

        if ($this->isDefined($schema->pattern)) {
            return $this->generatePatternString((string) $schema->pattern, $minimum, $maximum, $path);
        }

        $format = $this->isDefined($schema->format) ? $schema->format : null;
        $value = match ($format) {
            'date' => '2000-01-01',
            'date-time' => '2000-01-01T00:00:00Z',
            'email' => 'user@example.com',
            'uuid' => '00000000-0000-4000-8000-000000000000',
            default => 'example',
        };

        if (strlen($value) < $minimum) {
            $value .= str_repeat('x', $minimum - strlen($value));
        }
        if ($maximum !== null && strlen($value) > $maximum) {
            $value = substr($value, 0, $maximum);
        }

        $validFormat = match ($format) {
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1,
            'date-time' => preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $value) === 1,
            'email' => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'uuid' => preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1,
            default => true,
        };
        if (!$validFormat) {
            $this->invalid($path, sprintf('String constraints cannot be satisfied for format "%s".', $format));
        }

        return $value;
    }

    private function generatePatternString(string $pattern, int $minimum, ?int $maximum, string $path): string
    {
        $body = str_starts_with($pattern, '^') ? substr($pattern, 1) : $pattern;
        $body = str_ends_with($body, '$') ? substr($body, 0, -1) : $body;
        $tokenPattern = '/(\[[^\]]+\]|\\\\[dws]|\\\\.|[^\\\\\[\]{}+*?()|])(\{(\d+)(?:,(\d*))?\}|[+*?])?/u';
        $matched = preg_match_all($tokenPattern, $body, $tokens, PREG_SET_ORDER);
        if ($matched === false || $matched === 0) {
            $this->invalid($path, sprintf('Pattern "%s" cannot be converted to an example.', $pattern));
        }

        $consumed = '';
        $length = 0;
        /** @var list<array{character: string, count: int, maximum: int|null}> $segments */
        $segments = [];
        foreach ($tokens as $token) {
            $consumed .= $token[0];
            $quantifier = $token[2] ?? '';
            $count = match ($quantifier) {
                '+', '' => 1,
                '*', '?' => 0,
                default => (int) $token[3],
            };
            $segmentMaximum = match (true) {
                $quantifier === '+', $quantifier === '*' => null,
                $quantifier === '?' => 1,
                str_contains($quantifier, ',') => ($token[4] ?? '') === '' ? null : (int) $token[4],
                default => $count,
            };
            $segments[] = [
                'character' => $this->patternCharacter($token[1], $path),
                'count' => $count,
                'maximum' => $segmentMaximum,
            ];
            $length += $count;
        }

        if ($consumed !== $body) {
            $this->invalid($path, sprintf('Pattern "%s" uses unsupported syntax.', $pattern));
        }

        $padding = max(0, $minimum - $length);
        for ($index = count($segments) - 1; $index >= 0 && $padding > 0; --$index) {
            $available = $segments[$index]['maximum'] === null
                ? $padding
                : $segments[$index]['maximum'] - $segments[$index]['count'];
            $growth = min($padding, $available);
            $segments[$index]['count'] += $growth;
            $padding -= $growth;
        }
        if ($padding > 0) {
            $this->invalid($path, sprintf('Pattern "%s" cannot satisfy minLength.', $pattern));
        }

        $value = '';
        foreach ($segments as $segment) {
            $value .= str_repeat($segment['character'], $segment['count']);
        }
        if ($maximum !== null && strlen($value) > $maximum) {
            $this->invalid($path, sprintf('Pattern "%s" cannot satisfy maxLength.', $pattern));
        }

        $delimiterSafePattern = str_replace('~', '\\~', $pattern);
        if (preg_match('~' . $delimiterSafePattern . '~u', $value) !== 1) {
            $this->invalid($path, sprintf('Pattern "%s" cannot be converted to a matching example.', $pattern));
        }

        return $value;
    }

    private function patternCharacter(string $token, string $path): string
    {
        if (str_starts_with($token, '[')) {
            if (str_starts_with($token, '[^')) {
                $this->invalid($path, 'Negated character classes are not supported for example generation.');
            }
            if (str_contains($token, 'a-z')) {
                return 'a';
            }
            if (str_contains($token, 'A-Z')) {
                return 'A';
            }
            if (str_contains($token, '0-9')) {
                return '0';
            }

            return substr($token, 1, 1);
        }

        return match ($token) {
            '\\d' => '0',
            '\\w' => 'a',
            '\\s' => ' ',
            default => str_starts_with($token, '\\') ? substr($token, 1) : $token,
        };
    }

    /**
     * @return array{found: bool, value: mixed}
     */
    private function firstEnumValue(mixed $enum, string $path): array
    {
        if (is_string($enum) && is_a($enum, UnitEnum::class, true)) {
            $cases = $enum::cases();
            if ($cases === []) {
                return ['found' => false, 'value' => null];
            }

            return ['found' => true, 'value' => $this->normalizeEnumValue($cases[0])];
        }

        if (is_array($enum)) {
            $key = array_key_first($enum);
            if ($key === null) {
                return ['found' => false, 'value' => null];
            }

            return ['found' => true, 'value' => $this->normalizeEnumValue($enum[$key])];
        }

        if (is_string($enum) || is_numeric($enum) || is_bool($enum)) {
            return ['found' => true, 'value' => $enum];
        }

        $this->invalid($path, 'Enum does not contain a supported example value.');
    }

    private function normalizeEnumValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }

    private function isDefined(mixed $value): bool
    {
        return $value !== Generator::UNDEFINED;
    }

    private function invalid(string $path, string $message): never
    {
        throw new InvalidOpenApiDefinitionException([sprintf('%s: %s', $path, $message)]);
    }
}
