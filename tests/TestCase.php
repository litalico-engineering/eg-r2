<?php
declare(strict_types=1);

namespace Tests;

use Litalico\EgR2\Providers\EgR2ServiceProvider;
use Orchestra\Testbench\Concerns\WithWorkbench;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use WithWorkbench;

    /**
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EgR2ServiceProvider::class,
        ];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        // Set default locale to Japanese for tests
        $app['config']->set('app.locale', 'ja');

        // Set fallback locale to Japanese for tests
        $app['config']->set('app.fallback_locale', 'ja');
    }
}
