<?php

declare(strict_types=1);

namespace Litalico\EgR2\Services;

use BackedEnum;
use DateTimeImmutable;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Property;
use OpenApi\Annotations\Schema;
use OpenApi\Context;
use OpenApi\Generator;
use OpenApi\OpenApiException;
use Random\Randomizer;
use UnitEnum;
use function abs;
use function array_pop;
use function array_values;
use function bin2hex;
use function ceil;
use function chr;
use function class_exists;
use function config;
use function count;
use function filter_var;
use function floor;
use function in_array;
use function is_a;
use function is_array;
use function is_bool;
use function is_callable;
use function is_string;
use function ltrim;
use function max;
use function min;
use function ord;
use function preg_match;
use function preg_match_all;
use function range;
use function sprintf;
use function str_contains;
use function str_ends_with;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Generates random response examples from OpenAPI schemas in a scanned document.
 *
 * @package Litalico\EgR2\Services
 */
final class ResponseExampleGenerator
{
    private const DEFAULT_ITEMS_SPAN = 2;
    private const DEFAULT_LENGTH_SPAN = 8;
    private const DEFAULT_NUMBER_SPAN = 1000;
    private const ALPHANUMERIC = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    /** @var array<string, mixed> */
    private readonly array $rules;

    /** @var list<string> refs on the current generation path, for cycle detection */
    private array $refStack = [];

    /**
     * @param OpenApi $openApi Scanned document used to resolve `$ref` values.
     * @param array<string, mixed>|null $rules Custom generation rules; defaults to `eg_r2.response_example.rules`.
     *                                         Keys: `property:<name>`, `format:<format>`, `type:<type>`.
     *                                         Values: a fixed value, a callable `fn(Schema $schema, string $path): mixed`, or the class name of an invokable.
     */
    public function __construct(
        private readonly OpenApi $openApi,
        ?array $rules = null,
        private readonly Randomizer $randomizer = new Randomizer(),
    ) {
        /** @var array<string, mixed> $configured */
        $configured = $rules ?? config('eg_r2.response_example.rules', []);
        $this->rules = $configured;
    }

    /**
     * Generates an example for the component schema declared on the given class.
     *
     * @param class-string $class
     */
    public function generateForClass(string $class): mixed
    {
        $class = ltrim($class, '\\');
        $schemas = $this->isDefined($this->openApi->components) && $this->isDefined($this->openApi->components->schemas)
            ? $this->openApi->components->schemas
            : [];
        foreach ($schemas as $schema) {
            /** @var Context|null $context `_context` is nullable on swagger-php 4/5 and non-nullable on 6. */
            $context = $schema->_context;
            $name = $context?->fullyQualifiedName($context->class);
            if (ltrim((string) $name, '\\') === $class) {
                return $this->generate($schema);
            }
        }

        throw new InvalidOpenApiDefinitionException([sprintf('$: No component schema is declared on class "%s".', $class)]);
    }

    public function generate(Schema $schema): mixed
    {
        $this->refStack = [];

        return $this->value($schema, '$');
    }

    private function value(Schema $schema, string $path, bool $nullable = false): mixed
    {
        if ($this->isDefined($schema->example)) {
            return $schema->example;
        }

        $rule = $this->rule($schema);
        if ($rule !== null) {
            return $this->applyRule($this->rules[$rule], $schema, $path);
        }

        if ($this->isDefined($schema->enum)) {
            return $this->enumValue($schema->enum, $path);
        }

        if ($this->isDefined($schema->default)) {
            return $schema->default;
        }

        $nullable = $nullable || $this->isNullable($schema);
        if ($this->isDefined($schema->ref)) {
            return $this->reference($schema, $path, $nullable);
        }

        if ($this->isDefined($schema->allOf)) {
            return $this->allOf($schema, $path);
        }

        foreach (['oneOf', 'anyOf'] as $composition) {
            if ($this->isDefined($schema->{$composition})) {
                /** @var list<Schema> $branches */
                $branches = array_values($schema->{$composition});
                if ($branches === []) {
                    $this->invalid($path, sprintf('%s must contain at least one schema.', $composition));
                }
                $index = $this->randomizer->getInt(0, count($branches) - 1);

                return $this->value($branches[$index], sprintf('%s.%s[%d]', $path, $composition, $index), $nullable);
            }
        }

        $type = $this->resolveType($schema);

        return match ($type) {
            'object' => $this->properties($schema, $path),
            'array' => $this->arrayValue($schema, $path),
            'string' => $this->stringValue($schema, $path),
            'integer' => $this->number($schema, $path, true),
            'number' => $this->number($schema, $path, false),
            'boolean' => $this->randomizer->getInt(0, 1) === 1,
            'null' => null,
            default => $this->invalid($path, sprintf('Cannot generate an example for OpenAPI type "%s".', $type)),
        };
    }

    private function rule(Schema $schema): ?string
    {
        if ($this->rules === []) {
            return null;
        }

        $candidates = [];
        if ($schema instanceof Property && $this->isDefined($schema->property)) {
            $candidates[] = 'property:' . $schema->property;
        }
        if ($this->isDefined($schema->format)) {
            $candidates[] = 'format:' . $schema->format;
        }
        $type = $this->resolveType($schema);
        if ($type !== '') {
            $candidates[] = 'type:' . $type;
        }

        foreach ($candidates as $candidate) {
            if (isset($this->rules[$candidate])) {
                return $candidate;
            }
        }

        return null;
    }

    private function applyRule(mixed $rule, Schema $schema, string $path): mixed
    {
        if (is_string($rule) && class_exists($rule)) {
            $rule = new $rule();
        }

        return !is_string($rule) && is_callable($rule) ? $rule($schema, $path) : $rule;
    }

    private function reference(Schema $schema, string $path, bool $nullable): mixed
    {
        $ref = $schema->ref;
        if (!is_string($ref)) {
            $this->invalid($path, '$ref must be a string.');
        }
        if (in_array($ref, $this->refStack, true)) {
            if ($nullable) {
                return null;
            }
            $this->invalid($path, sprintf('Recursive $ref "%s" cannot be resolved without a nullable or empty-array escape.', $ref));
        }

        try {
            $target = $this->openApi->ref($ref);
        } catch (OpenApiException $exception) {
            $this->invalid($path, $exception->getMessage());
        }
        if (!$target instanceof Schema) {
            $this->invalid($path, sprintf('$ref "%s" does not resolve to a schema.', $ref));
        }

        $this->refStack[] = $ref;
        $value = $this->value($target, $path);
        array_pop($this->refStack);

        return $value;
    }

    /**
     * @return array<mixed>
     */
    private function allOf(Schema $schema, string $path): array
    {
        $merged = [];
        foreach (array_values($schema->allOf) as $index => $branch) {
            $value = $this->value($branch, sprintf('%s.allOf[%d]', $path, $index));
            if (!is_array($value)) {
                $this->invalid($path, 'allOf branches must generate objects.');
            }
            $merged = [...$merged, ...$value];
        }

        return [...$merged, ...$this->properties($schema, $path)];
    }

    /**
     * @return array<string, mixed>
     */
    private function properties(Schema $schema, string $path): array
    {
        if (!$this->isDefined($schema->properties)) {
            return [];
        }

        $example = [];
        foreach ($schema->properties as $property) {
            if (!$this->isDefined($property->property) || $property->property === '') {
                $this->invalid($path, 'Object properties must define a property name.');
            }
            if ($this->isDefined($property->writeOnly) && $property->writeOnly === true) {
                continue;
            }
            $example[$property->property] = $this->value($property, $path . '.' . $property->property);
        }

        return $example;
    }

    private function resolveType(Schema $schema): string
    {
        if ($this->isDefined($schema->type)) {
            if (is_array($schema->type)) {
                foreach ($schema->type as $type) {
                    if ($type !== 'null') {
                        return (string) $type;
                    }
                }

                return 'null';
            }

            return (string) $schema->type;
        }
        if ($this->isDefined($schema->properties)) {
            return 'object';
        }
        if ($this->isDefined($schema->items)) {
            return 'array';
        }

        return '';
    }

    private function isNullable(Schema $schema): bool
    {
        if ($this->isDefined($schema->nullable) && $schema->nullable === true) {
            return true;
        }

        return $this->isDefined($schema->type) && is_array($schema->type) && in_array('null', $schema->type, true);
    }

    /**
     * @return list<mixed>
     */
    private function arrayValue(Schema $schema, string $path): array
    {
        $minimum = $this->isDefined($schema->minItems) ? max(0, (int) $schema->minItems) : null;
        $maximum = $this->isDefined($schema->maxItems) ? max(0, (int) $schema->maxItems) : null;
        if ($minimum !== null && $maximum !== null && $minimum > $maximum) {
            $this->invalid($path, 'Array minItems is greater than maxItems.');
        }

        $items = $this->isDefined($schema->items) ? $schema->items : null;
        $cyclic = $items !== null && is_string($items->ref) && in_array($items->ref, $this->refStack, true);

        $minimum ??= $cyclic ? 0 : 1;
        $maximum = min($maximum ?? $minimum + self::DEFAULT_ITEMS_SPAN, $cyclic ? $minimum : PHP_INT_MAX);
        $count = $this->randomizer->getInt($minimum, max($minimum, $maximum));
        if ($count === 0) {
            return [];
        }
        if ($items === null) {
            $this->invalid($path, 'Array schemas must define items.');
        }

        $values = [];
        for ($index = 0; $index < $count; ++$index) {
            $values[] = $this->value($items, sprintf('%s[%d]', $path, $index));
        }

        return $values;
    }

    private function number(Schema $schema, string $path, bool $integer): int|float
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

        $minimum ??= $maximum === null ? 0.0 : $maximum - self::DEFAULT_NUMBER_SPAN;
        $maximum ??= $minimum + self::DEFAULT_NUMBER_SPAN;
        if ($minimum > $maximum) {
            $this->invalid($path, 'Numeric minimum is greater than maximum.');
        }

        if ($integer) {
            $low = (int) ceil($minimum);
            $high = (int) floor($maximum);
            if ($low > $high) {
                $this->invalid($path, 'Numeric bounds contain no integer value.');
            }

            return $this->randomizer->getInt($low, $high);
        }

        $value = $minimum + ($maximum - $minimum) * ($this->randomizer->getInt(0, PHP_INT_MAX) / PHP_INT_MAX);

        return min(max($value, $minimum), $maximum);
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

    private function stringValue(Schema $schema, string $path): string
    {
        $minimum = $this->isDefined($schema->minLength) ? max(0, (int) $schema->minLength) : 0;
        $maximum = $this->isDefined($schema->maxLength) ? max(0, (int) $schema->maxLength) : null;
        if ($maximum !== null && $minimum > $maximum) {
            $this->invalid($path, 'String minLength is greater than maxLength.');
        }

        if ($this->isDefined($schema->pattern)) {
            return $this->patternString((string) $schema->pattern, $minimum, $maximum, $path);
        }

        $format = $this->isDefined($schema->format) ? $schema->format : null;
        $value = match ($format) {
            'date' => $this->randomDate()->format('Y-m-d'),
            'date-time' => $this->randomDate()->format('Y-m-d\TH:i:s\Z'),
            'email' => $this->randomString(8, 8) . '@example.com',
            'uuid' => $this->randomUuid(),
            default => $this->randomString($minimum, $maximum ?? $minimum + self::DEFAULT_LENGTH_SPAN),
        };

        if (strlen($value) < $minimum) {
            $value .= $this->randomString($minimum - strlen($value), $minimum - strlen($value));
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

    private function randomString(int $minimum, int $maximum): string
    {
        return $this->bytesFromString(self::ALPHANUMERIC, $this->randomizer->getInt($minimum, max($minimum, $maximum)));
    }

    /** Randomizer::getBytesFromString() requires PHP 8.3; composer allows 8.2. */
    private function bytesFromString(string $characters, int $length): string
    {
        $last = strlen($characters) - 1;
        $value = '';
        for ($index = 0; $index < $length; ++$index) {
            $value .= $characters[$this->randomizer->getInt(0, $last)];
        }

        return $value;
    }

    private function randomDate(): DateTimeImmutable
    {
        // 2000-01-01T00:00:00Z .. 2037-12-31T23:59:59Z
        $timestamp = $this->randomizer->getInt(946684800, 2145916799);

        return new DateTimeImmutable('@' . $timestamp);
    }

    private function randomUuid(): string
    {
        $bytes = $this->randomizer->getBytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);

        return sprintf('%s-%s-%s-%s-%s', substr($hex, 0, 8), substr($hex, 8, 4), substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20, 12));
    }

    private function patternString(string $pattern, int $minimum, ?int $maximum, string $path): string
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
        /** @var list<array{characters: string, count: int, maximum: int|null}> $segments */
        $segments = [];
        foreach ($tokens as $token) {
            $consumed .= $token[0];
            $quantifier = $token[2] ?? '';
            $count = match ($quantifier) {
                '+', '' => 1,
                '*', '?' => 0,
                default => (int) ($token[3] ?? 0),
            };
            $segmentMaximum = match (true) {
                $quantifier === '+', $quantifier === '*' => null,
                $quantifier === '?' => 1,
                str_contains($quantifier, ',') => ($token[4] ?? '') === '' ? null : (int) $token[4],
                default => $count,
            };
            $segments[] = [
                'characters' => $this->patternCharacters($token[1], $path),
                'count' => $count,
                'maximum' => $segmentMaximum,
            ];
            $length += $count;
        }

        if ($consumed !== $body) {
            $this->invalid($path, sprintf('Pattern "%s" uses unsupported syntax.', $pattern));
        }
        if ($maximum !== null && $length > $maximum) {
            $this->invalid($path, sprintf('Pattern "%s" cannot satisfy maxLength.', $pattern));
        }

        // Grow quantified segments to a random total length within the string bounds.
        $capacity = 0;
        foreach ($segments as $segment) {
            $capacity += $segment['maximum'] === null ? self::DEFAULT_LENGTH_SPAN : $segment['maximum'] - $segment['count'];
        }
        $upper = min($length + $capacity, $maximum ?? $length + $capacity);
        if ($upper < $minimum) {
            $this->invalid($path, sprintf('Pattern "%s" cannot satisfy minLength.', $pattern));
        }
        $padding = $this->randomizer->getInt(max($minimum, $length), $upper) - $length;
        for ($index = count($segments) - 1; $index >= 0 && $padding > 0; --$index) {
            $available = $segments[$index]['maximum'] === null
                ? $padding
                : $segments[$index]['maximum'] - $segments[$index]['count'];
            $growth = min($padding, $available);
            $segments[$index]['count'] += $growth;
            $padding -= $growth;
        }

        $value = '';
        foreach ($segments as $segment) {
            $value .= $this->bytesFromString($segment['characters'], $segment['count']);
        }

        $delimiterSafePattern = str_replace('~', '\\~', $pattern);
        if (preg_match('~' . $delimiterSafePattern . '~u', $value) !== 1) {
            $this->invalid($path, sprintf('Pattern "%s" cannot be converted to a matching example.', $pattern));
        }

        return $value;
    }

    /**
     * Expands a pattern token into the set of characters it may produce.
     */
    private function patternCharacters(string $token, string $path): string
    {
        if (str_starts_with($token, '[')) {
            if (str_starts_with($token, '[^')) {
                $this->invalid($path, 'Negated character classes are not supported for example generation.');
            }
            $class = substr($token, 1, -1);
            $characters = '';
            for ($index = 0, $length = strlen($class); $index < $length; ++$index) {
                $character = $class[$index];
                if ($character === '\\' && $index + 1 < $length) {
                    $characters .= $this->patternCharacters('\\' . $class[++$index], $path);
                } elseif ($index + 2 < $length && $class[$index + 1] === '-') {
                    $characters .= $this->range($character, $class[$index + 2]);
                    $index += 2;
                } else {
                    $characters .= $character;
                }
            }

            return $characters;
        }

        return match ($token) {
            '\\d' => '0123456789',
            '\\w' => self::ALPHANUMERIC . '_',
            '\\s' => ' ',
            '.' => self::ALPHANUMERIC,
            default => str_starts_with($token, '\\') ? substr($token, 1) : $token,
        };
    }

    private function range(string $from, string $to): string
    {
        $characters = '';
        foreach (range(ord($from), ord($to)) as $code) {
            $characters .= chr($code);
        }

        return $characters;
    }

    private function enumValue(mixed $enum, string $path): mixed
    {
        if (is_string($enum) && is_a($enum, UnitEnum::class, true)) {
            $enum = $enum::cases();
        }
        if (is_array($enum)) {
            $values = array_values($enum);
            if ($values === []) {
                $this->invalid($path, 'Enum does not contain a supported example value.');
            }
            $value = $values[$this->randomizer->getInt(0, count($values) - 1)];

            return $value instanceof BackedEnum ? $value->value : ($value instanceof UnitEnum ? $value->name : $value);
        }

        return $enum;
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
