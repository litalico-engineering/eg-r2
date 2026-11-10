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
