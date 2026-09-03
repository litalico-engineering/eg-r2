<?php

declare(strict_types=1);

namespace Tests\Unit;

use DateTimeImmutable;
use Litalico\EgR2\Exceptions\InvalidOpenApiDefinitionException;
use Litalico\EgR2\Services\ResponseExampleGenerator;
use OpenApi\Annotations\OpenApi;
use OpenApi\Annotations\Schema;
use OpenApi\Attributes\Items;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema as SchemaAttribute;
use OpenApi\Generator;
use PHPUnit\Framework\Attributes\CoversClass;
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
    }
}

final class ResponseExampleFalseRule
{
    public function __invoke(): bool
    {
        return false;
    }
}
