<?php

declare(strict_types=1);

namespace Litalico\EgR2\Services;

use OpenApi\Annotations\Schema;
use OpenApi\Generator;
use function array_filter;
use function array_values;
use function count;
use function in_array;
use function is_array;

/**
 * Resolves nullable and type definitions of a Schema in both OpenAPI 3.0 and 3.1 styles.
 *
 * - 3.0: `type: 'string', nullable: true`
 * - 3.1: `type: ['string', 'null']`
 */
final class SchemaTypeResolver
{
    /**
     * Returns true if the schema accepts null (`nullable: true` or `'null'` in the type array).
     */
    public static function isNullable(Schema $schema): bool
    {
        return $schema->nullable === true || self::hasNullType($schema);
    }

    /**
     * Returns true if the schema actually accepts null, taking `enum` into account.
     *
     * - 3.0: `nullable: true` always accepts null.
     * - 3.1: `type` and `enum` apply independently, so with an `enum` null must also be listed in the enum values.
     */
    public static function acceptsNull(Schema $schema): bool
    {
        if ($schema->nullable === true) {
            return true;
        }
        if (!self::hasNullType($schema)) {
            return false;
        }
        if ($schema->enum === Generator::UNDEFINED) {   /** @phpstan-ignore-line */
            return true;
        }

        return is_array($schema->enum) && self::containsNull($schema->enum);
    }

    /**
     * swagger-php's PHPDoc for `enum` omits null, but OpenAPI 3.1 enums can list null.
     *
     * @param array<mixed> $values
     */
    private static function containsNull(array $values): bool
    {
        return in_array(null, $values, true);
    }

    /**
     * Returns the type without `'null'`.
     * A type array with a single non-null type is returned as that string.
     *
     * @return string|non-empty-array<string>|null null when the type is undefined
     */
    public static function baseType(Schema $schema): string|array|null
    {
        $type = $schema->type;
        if ($type === Generator::UNDEFINED) {   /** @phpstan-ignore-line */
            return null;
        }
        if (!is_array($type)) {
            return $type;
        }

        $types = array_values(array_filter($type, static fn (string $name): bool => $name !== 'null'));

        return match (count($types)) {
            0 => null,
            1 => $types[0],
            default => $types,
        };
    }

    /**
     * Returns true if the base type equals the given type.
     */
    public static function is(Schema $schema, string $type): bool
    {
        return self::baseType($schema) === $type;
    }

    private static function hasNullType(Schema $schema): bool
    {
        return is_array($schema->type) && in_array('null', $schema->type, true);
    }
}
