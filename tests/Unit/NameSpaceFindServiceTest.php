<?php

declare(strict_types=1);

namespace Tests\Unit;

use Generator;
use Litalico\EgR2\Console\Commands\GenerateRoute;
use Litalico\EgR2\Providers\GenerateRouteServiceProvider;
use Tests\TestCase;
use Litalico\EgR2\Services\NameSpaceFindService;
use Illuminate\Foundation\Application;

/**
 * @package Tests\Unit
 * @coversDefaultClass \Litalico\EgR2\Services\NameSpaceFindService
 * @covers \Litalico\EgR2\Services\NameSpaceFindService
 */
class NameSpaceFindServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @test
     * @covers ::getNameSpaces
     * @throws
     */
    public function testGetNameSpaces(): void
    {
        setup:
        // Change base path
        new Application(realpath(__DIR__.'/../../'));
        $instance = new NameSpaceFindService();

        when:
        $actual = $instance->getNameSpaces();

        then:
        self::assertContains('Litalico\EgR2\Providers', $actual);
    }

    /**
     * @test
     * @covers ::getClassesOfNameSpace
     * @dataProvider namespacePattern
     * @param string $namespace
     * @param array $expected
     */
    public function testGetClassesOfNameSpace(string $namespace, array $expected): void
    {
        setup:
        // Change base path
        new Application(realpath(__DIR__.'/../../'));
        $instance = new NameSpaceFindService();

        when:
        $actual = $instance->getClassesOfNameSpace($namespace);

        then:
        self::assertEquals($expected, $actual);
    }

    /**
     * @return Generator
     */
    public static function namespacePattern(): iterable
    {
        yield 'Litalico\EgR2\Providers' => ['Litalico\EgR2\Providers', [GenerateRouteServiceProvider::class]];
        yield 'Litalico\EgR2\Console\Commands' => ['Litalico\EgR2\Console\Commands', [GenerateRoute::class]];
    }
}
