<?php

declare(strict_types=1);

namespace Tests\Unit;

use Generator;
use Illuminate\Foundation\Application;
use Litalico\EgR2\Console\Commands\GenerateRoute;
use Litalico\EgR2\Providers\GenerateRouteServiceProvider;
use Litalico\EgR2\Services\NameSpaceFindService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(NameSpaceFindService::class)]
class NameSpaceFindServiceTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
    }

    /**
     * @throws
     */
    #[Test]
    public function getNameSpaces(): void
    {
        setup:
        // Change base path
        $path = realpath(__DIR__.'/../../');
        if ($path === false) {
            self::fail('realpath is invalid');
        }
        new Application($path);
        $instance = new NameSpaceFindService();

        when:
        $actual = $instance->getNameSpaces();

        then:
        self::assertContains('Litalico\EgR2\Providers', $actual);
    }

    /**
     * @param string $namespace
     * @param list<class-string> $expected
     */
    #[Test]
    #[DataProvider('namespacePattern')]
    public function getClassesOfNameSpace(string $namespace, array $expected): void
    {
        setup:
        // Change base path
        $path = realpath(__DIR__.'/../../');
        if ($path === false) {
            self::fail('realpath is invalid');
        }
        new Application($path);
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
