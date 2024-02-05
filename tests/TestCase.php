<?php
declare(strict_types=1);

namespace Tests;

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
        return [];
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     * @return void
     */
    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('eg_r2', [
            'namespaces' => [],
            'route_path' => base_path('routes/eg_r2.php'),
        ]);
    }
}
