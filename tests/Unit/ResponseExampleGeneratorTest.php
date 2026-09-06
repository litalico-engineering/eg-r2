<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use Litalico\EgR2\Services\ResponseExampleGenerator;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Schema;
use OpenApi\Attributes\AdditionalProperties;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema as SchemaAttribute;
use OpenApi\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tests\Fixtures\Responses\AdminResponse;
use Tests\Fixtures\Responses\FacilityResponse;
use Tests\Fixtures\Responses\OwnerResponse;
use Tests\TestCase;
use function array_keys;
use function array_unique;
use function count;
use function filter_var;
use function json_encode;
use function preg_match;
use function strlen;

#[CoversClass(ResponseExampleGenerator::class)]
class ResponseExampleGeneratorTest extends TestCase
{
    private static OpenApi $openApi;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $openApi = (new Generator(new NullLogger()))->generate([__DIR__ . '/../Fixtures/Responses'], validate: false);
        self::assertInstanceOf(OpenApi::class, $openApi);
        self::$openApi = $openApi;
    }

    #[Test]
    public function refsCompositionAndCyclesResolveAgainstTheScannedDocument(): void
    {
        $example = (new ResponseExampleGenerator(self::$openApi, []))->generateForClass(FacilityResponse::class);

        self::assertSame(['id', 'status', 'name', 'owner', 'contacts', 'kind', 'parent', 'children'], array_keys($example));
        self::assertGreaterThanOrEqual(1, $example['id']);
        self::assertLessThanOrEqual(10, $example['id']);
        self::assertContains($example['status'], ['open', 'closed']);
        self::assertSame('Central', $example['name']);
        self::assertSame(['name', 'email', 'uuid', 'birthday', 'createdAt', 'code', 'score', 'active', 'plan'], array_keys($example['owner']));
        self::assertGreaterThanOrEqual(1, count($example['contacts']));
        self::assertLessThanOrEqual(3, count($example['contacts']));
        self::assertSame(array_keys($example['owner']), array_keys($example['contacts'][0]));
        self::assertContains($example['kind'], [7, 'branch']);
        // A recursive nullable $ref becomes null once the cycle repeats; a recursive array becomes empty.
        self::assertNull($example['parent']['parent']);
        self::assertSame([], $example['parent']['children']);
        foreach ($example['children'] as $child) {
            self::assertSame([], $child['children']);
        }
    }

    #[Test]
    public function scalarFormatsAndConstraintsProduceValidValues(): void
    {
        $example = (new ResponseExampleGenerator(self::$openApi, []))->generateForClass(OwnerResponse::class);

        self::assertGreaterThanOrEqual(3, strlen($example['name']));
        self::assertLessThanOrEqual(5, strlen($example['name']));
        self::assertNotFalse(filter_var($example['email'], FILTER_VALIDATE_EMAIL));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $example['uuid']);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $example['birthday']);
        self::assertNotFalse($date);
        self::assertSame($example['birthday'], $date->format('Y-m-d'));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $example['createdAt']);
        self::assertSame(1, preg_match('/^[a-c]{2}_\d+$/', $example['code']));
        self::assertGreaterThanOrEqual(6, strlen($example['code']));
        self::assertLessThanOrEqual(8, strlen($example['code']));
        self::assertGreaterThan(1.5, $example['score']);
        self::assertLessThan(3.5, $example['score']);
        self::assertIsBool($example['active']);
        self::assertSame('free', $example['plan']);
    }

    #[Test]
    public function emailExamplesFitLengthBoundsThatAllowAValidAddress(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);

        foreach ([[13, 13], [15, 15], [30, 30], [1, 19], [25, null]] as list($minLength, $maxLength)) {
            $email = $generator->generate(new SchemaAttribute(type: 'string', format: 'email', minLength: $minLength, maxLength: $maxLength));
            self::assertIsString($email);
            self::assertNotFalse(filter_var($email, FILTER_VALIDATE_EMAIL), $email);
            self::assertStringEndsWith('@example.com', $email);
            self::assertGreaterThanOrEqual($minLength, strlen($email));
            self::assertLessThanOrEqual($maxLength ?? PHP_INT_MAX, strlen($email));
        }

        try {
            $generator->generate(new SchemaAttribute(type: 'string', format: 'email', maxLength: 3));
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$: String constraints cannot be satisfied for format "email".'], $exception->getMessages());
        }
    }

    #[Test]
    public function allOfMergesBranchesAndAnyOfPicksOneBranch(): void
    {
        $example = (new ResponseExampleGenerator(self::$openApi, []))->generateForClass(AdminResponse::class);

        self::assertSame(['name', 'email', 'uuid', 'birthday', 'createdAt', 'code', 'score', 'active', 'plan', 'role', 'permissions'], array_keys($example));
        self::assertSame('admin', $example['role']);
        self::assertCount(2, $example['permissions']);
        foreach ($example['permissions'] as $permission) {
            self::assertContains($permission, ['read', 'write']);
        }
    }

    #[Test]
    public function valuesAreRandomPerCallAndReproducibleWithASeededRandomizer(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);
        $uuids = [];
        for ($index = 0; $index < 20; ++$index) {
            $uuids[] = $generator->generateForClass(OwnerResponse::class)['uuid'];
        }
        self::assertGreaterThan(1, count(array_unique($uuids)));

        $first = (new ResponseExampleGenerator(self::$openApi, [], new Randomizer(new Mt19937(39))))->generateForClass(FacilityResponse::class);
        $second = (new ResponseExampleGenerator(self::$openApi, [], new Randomizer(new Mt19937(39))))->generateForClass(FacilityResponse::class);
        self::assertSame($first, $second);
    }

    #[Test]
    public function customRulesMatchPropertyThenFormatThenTypeAndFallBackToConfig(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, [
            'property:uuid' => 'fixed-uuid',
            'format:email' => static fn (Schema $schema, string $path): string => $path . '@rule.test',
            'type:boolean' => ResponseExampleFalseRule::class,
            'type:string' => 'type rule must lose to the more specific keys',
        ]);
        $example = $generator->generateForClass(OwnerResponse::class);

        self::assertSame('fixed-uuid', $example['uuid']);
        self::assertSame('$.email@rule.test', $example['email']);
        self::assertFalse($example['active']);
        self::assertSame('type rule must lose to the more specific keys', $example['name']);
        // An explicit example still wins over a rule; enum and default lose to it.
        self::assertSame('type rule must lose to the more specific keys', $example['plan']);
        self::assertSame('Central', $generator->generateForClass(FacilityResponse::class)['name']);

        config()->set('eg_r2.response_example.rules', ['property:id' => 99]);
        self::assertSame(99, (new ResponseExampleGenerator(self::$openApi))->generateForClass(FacilityResponse::class)['id']);
    }

    #[Test]
    public function invalidDefinitionsAreReportedWithTheirPath(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);

        $schema = new SchemaAttribute(properties: [
            new Property(property: 'items', type: 'array', minItems: 3, maxItems: 1, items: new Items(type: 'string')),
        ]);

        try {
            $generator->generate($schema);
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$.items: Array minItems is greater than maxItems.'], $exception->getMessages());
        }

        try {
            $generator->generate(new SchemaAttribute(ref: '#/components/schemas/Missing'));
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertStringStartsWith('$: ', $exception->getMessages()[0]);
        }

        try {
            $generator->generateForClass(self::class);
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$: No component schema is declared on class "Tests\Unit\ResponseExampleGeneratorTest".'], $exception->getMessages());
        }

        try {
            (new ResponseExampleGenerator(self::$openApi, ['type:string' => ResponseExampleNotInvokable::class]))->generate(new SchemaAttribute(type: 'string'));
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$: Rule class "Tests\Unit\ResponseExampleNotInvokable" is not invokable.'], $exception->getMessages());
        }
    }

    #[Test]
    public function objectsWithoutPropertiesEncodeAsJsonObjects(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);

        self::assertSame('{}', json_encode($generator->generate(new SchemaAttribute(type: 'object'))));
        self::assertSame('{}', json_encode($generator->generate(new SchemaAttribute(type: 'object', additionalProperties: new AdditionalProperties(type: 'string')))));
        self::assertSame('{}', json_encode($generator->generate(new SchemaAttribute(properties: [new Property(property: 'secret', type: 'string', writeOnly: true)]))));
        // An empty branch merges nothing; an array branch is not an object.
        self::assertSame('{"a":1}', json_encode($generator->generate(new SchemaAttribute(allOf: [
            new SchemaAttribute(type: 'object'),
            new SchemaAttribute(properties: [new Property(property: 'a', type: 'integer', example: 1)]),
        ]))));

        try {
            $generator->generate(new SchemaAttribute(allOf: [new SchemaAttribute(type: 'array', items: new Items(type: 'string'))]));
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$: allOf branches must generate objects.'], $exception->getMessages());
        }
    }

    #[Test]
    public function arrayCountsHonorMaxItemsAndUniqueItems(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);

        self::assertSame([], $generator->generate(new SchemaAttribute(type: 'array', maxItems: 0, items: new Items(type: 'string'))));

        $unique = new SchemaAttribute(type: 'array', minItems: 3, maxItems: 3, uniqueItems: true, items: new Items(type: 'string', enum: ['a', 'b', 'c']));
        for ($index = 0; $index < 20; ++$index) {
            $values = $generator->generate($unique);
            self::assertCount(3, $values);
            self::assertSame($values, array_unique($values));
        }

        try {
            $generator->generate(new SchemaAttribute(type: 'array', minItems: 2, uniqueItems: true, items: new Items(type: 'string', enum: ['only'])));
            self::fail('Expected an exception.');
        } catch (InvalidOpenApiDefinitionException $exception) {
            self::assertSame(['$: uniqueItems cannot be satisfied with the item schema.'], $exception->getMessages());
        }
    }

    #[Test]
    public function integerBoundsKeepInt64PrecisionAndHonorMultipleOf(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);

        self::assertSame(PHP_INT_MAX, $generator->generate(new SchemaAttribute(type: 'integer', minimum: PHP_INT_MAX, maximum: PHP_INT_MAX)));
        self::assertSame(PHP_INT_MIN, $generator->generate(new SchemaAttribute(type: 'integer', minimum: PHP_INT_MIN, maximum: PHP_INT_MIN)));
        $value = $generator->generate(new SchemaAttribute(type: 'integer', minimum: 0, maximum: PHP_INT_MAX));
        self::assertIsInt($value);
        self::assertGreaterThanOrEqual(0, $value);
        // OAS 3.1 numeric exclusive bounds on integers round inward.
        self::assertSame(8, $generator->generate(new SchemaAttribute(type: 'integer', exclusiveMinimum: 7.5, exclusiveMaximum: 9)));

        // The attribute constructor has no multipleOf argument; the annotation form accepts every property.
        for ($index = 0; $index < 20; ++$index) {
            $multiple = $generator->generate(new Schema(['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'multipleOf' => 10]));
            self::assertIsInt($multiple);
            self::assertSame(0, $multiple % 10);
            self::assertGreaterThanOrEqual(10, $multiple);
            self::assertLessThanOrEqual(100, $multiple);
            $decimal = $generator->generate(new Schema(['type' => 'number', 'minimum' => 0.5, 'maximum' => 0.75, 'multipleOf' => 0.25]));
            self::assertContains($decimal, [0.5, 0.75]);
        }

        foreach ([
            [new SchemaAttribute(type: 'integer', minimum: 9.3e18), '$: Integer bound 9.3E+18 exceeds the supported range.'],
            [new SchemaAttribute(type: 'integer', minimum: PHP_INT_MAX, exclusiveMinimum: true), '$: Numeric bounds contain no integer value.'],
            [new Schema(['type' => 'integer', 'minimum' => 11, 'maximum' => 19, 'multipleOf' => 10]), '$: Numeric bounds contain no multiple of 10.'],
        ] as list($schema, $message)) {
            try {
                $generator->generate($schema);
                self::fail('Expected an exception.');
            } catch (InvalidOpenApiDefinitionException $exception) {
                self::assertSame([$message], $exception->getMessages());
            }
        }
    }

    /**
     * @return iterable<string, array{string, int|null, int|null}>
     */
    public static function supportedPatterns(): iterable
    {
        yield 'unbounded quantifier fills an exact length' => ['^[a-z]+$', 20, 20];
        yield 'open range' => ['^\d{3,}$', 10, null];
        yield 'optional and star' => ['^a?b*c$', null, null];
        yield 'escaped literal and word class' => ['^\w{2}\.[A-F0-9-]{1,4}$', null, 6];
        yield 'unanchored' => ['x[0-9]', null, null];
    }

    #[Test]
    #[DataProvider('supportedPatterns')]
    public function patternsGenerateMatchingStringsWithinLengthBounds(string $pattern, ?int $minLength, ?int $maxLength): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);
        for ($index = 0; $index < 20; ++$index) {
            $value = $generator->generate(new SchemaAttribute(type: 'string', pattern: $pattern, minLength: $minLength, maxLength: $maxLength));
            self::assertMatchesRegularExpression('~' . $pattern . '~', $value);
            self::assertGreaterThanOrEqual($minLength ?? 0, strlen($value));
            self::assertLessThanOrEqual($maxLength ?? PHP_INT_MAX, strlen($value));
        }
    }

    #[Test]
    public function unsupportedPatternsAreReported(): void
    {
        $generator = new ResponseExampleGenerator(self::$openApi, []);
        foreach ([
            ['^(ab)+$', null, null, 'Pattern "^(ab)+$" uses unsupported syntax.'],
            ['^[^a]$', null, null, 'Negated character classes are not supported for example generation.'],
            ['^[ぁ-ん]+$', null, null, 'Pattern "^[ぁ-ん]+$" uses unsupported syntax: only ASCII patterns are supported.'],
            ['^[a-z]{2}$', 3, null, 'Pattern "^[a-z]{2}$" cannot satisfy minLength.'],
            ['^[a-z]{3}$', null, 2, 'Pattern "^[a-z]{3}$" cannot satisfy maxLength.'],
        ] as list($pattern, $minLength, $maxLength, $message)) {
            try {
                $generator->generate(new SchemaAttribute(type: 'string', pattern: $pattern, minLength: $minLength, maxLength: $maxLength));
                self::fail('Expected an exception.');
            } catch (InvalidOpenApiDefinitionException $exception) {
                self::assertSame(['$: ' . $message], $exception->getMessages());
            }
        }
    }
}

final class ResponseExampleFalseRule
{
    public function __invoke(): bool
    {
        return false;
    }
}

final class ResponseExampleNotInvokable
{
}
