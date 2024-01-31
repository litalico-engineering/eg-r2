<?php
declare(strict_types=1);

namespace Litalico\EgR2\Providers;

use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;
use Litalico\EgR2\Console\Commands\GenerateRoute;

/**
 * This provider will publish the necessary files in the specified directories.
 * @package Litalico\EgR2\Providers
 */
class GenerateRouteServiceProvider extends ServiceProvider
{
    public const STUB_DIR = __DIR__.'/../../stubs';

    /**
     * @inheritDoc
     */
    public function boot(Router $router): void
    {
        // Publishing is only necessary when using the CLI
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * @return void
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            self::STUB_DIR.'/config/eg_r2.php' => config_path('eg_r2.php'),
        ], 'eg-r2-config');
        // Setup command
        $this->commands([
            GenerateRoute::class,
        ]);
    }

    /**
     * @inheritDoc
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            self::STUB_DIR.'/config/eg_r2.php',
            'eg-r2-config'
        );
    }

    /**
     * @return list<class-string>
     */
    public function provides(): array
    {
        return [GenerateRoute::class];
    }
}
