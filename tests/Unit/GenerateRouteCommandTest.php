<?php

declare(strict_types=1);

namespace Tests\Unit;

use Litalico\EgR2\Services\NameSpaceFindService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Fixtures\Security\CompositeMultiScopesSecurityController;
use Tests\Fixtures\Security\CompositeSecurityController;
use Tests\Fixtures\Security\MultipleRequirementsSecurityController;
use Tests\Fixtures\Security\ResourceController;
use Tests\Fixtures\Security\UndefinedSchemeSecurityController;
use Tests\TestCase;

class GenerateRouteCommandTest extends TestCase
{
    #[Test]
    public function it_generates_middlewares_from_composite_and_mapping_order_is_preserved(): void
    {
        $routePath = $this->runCommandWithControllers(
            [CompositeSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth&&apiKeyAuth' => [
                        'auth:bearer_and_api',
                        'authorize.scope:{scopes}',
                    ],
                ],
            ]
        );

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::get('/composite','index')->name('index')->middleware(['auth:bearer_and_api','authorize.scope:read']);", $contents);
    }

    #[Test]
    public function it_joins_multiple_scopes_into_single_placeholder_value(): void
    {
        $routePath = $this->runCommandWithControllers(
            [CompositeMultiScopesSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth&&apiKeyAuth' => [
                        'auth:bearer_and_api',
                        'authorize.scope:{scopes}',
                    ],
                ],
            ]
        );

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::get('/composite-multi-scopes','index')->name('index')->middleware(['auth:bearer_and_api','authorize.scope:read,write']);", $contents);
    }

    #[Test]
    public function it_fails_when_multiple_requirements_policy_is_error(): void
    {
        $routePath = $this->prepareRoutePath();
        $this->setupCommandConfig(
            $routePath,
            [MultipleRequirementsSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth' => 'auth:api',
                    'apiKeyAuth' => 'auth.apikey',
                ],
                'multiple_requirements_policy' => 'error',
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->artisan('eg-r2:generate-route');
    }

    #[Test]
    public function it_uses_only_first_requirement_when_multiple_requirements_policy_is_warning_first(): void
    {
        $routePath = $this->runCommandWithControllers(
            [MultipleRequirementsSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth' => 'auth:api',
                    'apiKeyAuth' => 'auth.apikey',
                ],
                'multiple_requirements_policy' => 'warning_first',
            ]
        );

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::get('/multi','multi')->name('multi')->middleware(['auth:api']);", $contents);
        self::assertStringNotContainsString('auth.apikey', $contents);
    }

    #[Test]
    public function it_skips_middleware_generation_when_multiple_requirements_policy_is_warning_skip(): void
    {
        $routePath = $this->runCommandWithControllers(
            [MultipleRequirementsSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth' => 'auth:api',
                    'apiKeyAuth' => 'auth.apikey',
                ],
                'multiple_requirements_policy' => 'warning_skip',
            ]
        );

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::get('/multi','multi')->name('multi');", $contents);
        self::assertStringNotContainsString("Route::get('/multi','multi')->name('multi')->middleware", $contents);
    }

    #[Test]
    public function it_fails_for_undefined_scheme_when_policy_is_error(): void
    {
        $routePath = $this->prepareRoutePath();
        $this->setupCommandConfig(
            $routePath,
            [UndefinedSchemeSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth' => 'auth:api',
                ],
                'undefined_scheme_policy' => 'error',
            ]
        );

        $this->expectException(RuntimeException::class);
        $this->artisan('eg-r2:generate-route');
    }

    #[Test]
    public function error_message_includes_context_for_multiple_requirements(): void
    {
        $routePath = $this->prepareRoutePath();
        $this->setupCommandConfig(
            $routePath,
            [MultipleRequirementsSecurityController::class],
            [
                'multiple_requirements_policy' => 'error',
            ]
        );

        try {
            $this->artisan('eg-r2:generate-route');
            self::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            // Error message should contain path and method name for context
            self::assertStringContainsString('/multi', $e->getMessage());
            self::assertStringContainsString('multi', $e->getMessage());
        }
    }

    #[Test]
    public function default_policy_is_warning_skip_and_generates_routes_without_middleware(): void
    {
        // Test without explicitly setting multiple_requirements_policy
        // It should default to 'warning_skip' instead of 'error'
        $routePath = $this->prepareRoutePath();
        $namespace = 'Tests\\Fixtures\\Security';

        $service = Mockery::mock(NameSpaceFindService::class);
        $service->shouldReceive('getClassesOfNameSpace')
            ->once()
            ->with($namespace)
            ->andReturn([MultipleRequirementsSecurityController::class]);

        $this->app->instance(NameSpaceFindService::class, $service);

        $this->app['config']->set('eg_r2.route_path', $routePath);
        $this->app['config']->set('eg_r2.namespaces', ['api.' => $namespace]);
        // Only set mapping, not explicitly setting multiple_requirements_policy
        $this->app['config']->set('eg_r2.security', ['mapping' => [], 'undefined_scheme_policy' => 'ignore']);

        // Should succeed without throwing error (default warning_skip policy)
        $this->artisan('eg-r2:generate-route')->assertExitCode(0);

        $contents = (string) file_get_contents($routePath);
        // Should generate route without middleware (skipped due to multiple requirements)
        self::assertStringContainsString("Route::get('/multi','multi')->name('multi');", $contents);
        self::assertStringNotContainsString("Route::get('/multi','multi')->name('multi')->middleware", $contents);
    }

    #[Test]
    public function it_generates_unique_route_names_using_operationId(): void
    {
        $routePath = $this->runCommandWithControllers(
            [ResourceController::class],
            []
        );

        $contents = (string) file_get_contents($routePath);

        // Each route should have a unique name derived from operationId
        self::assertStringContainsString("Route::get('/resources/{id}','show')->name('showResource');", $contents);
        self::assertStringContainsString("Route::post('/resources','create')->name('createResource');", $contents);
        self::assertStringContainsString("Route::put('/resources/{id}','update')->name('updateResource');", $contents);
    }

    #[Test]
    public function it_normalizes_route_name_group_with_trailing_dot(): void
    {
        $routePath = $this->prepareRoutePath();
        $namespace = 'Tests\\Fixtures\\Security';

        $service = Mockery::mock(NameSpaceFindService::class);
        $service->shouldReceive('getClassesOfNameSpace')
            ->once()
            ->with($namespace)
            ->andReturn([ResourceController::class]);

        $this->app->instance(NameSpaceFindService::class, $service);

        $this->app['config']->set('eg_r2.route_path', $routePath);
        $this->app['config']->set('eg_r2.namespaces', ['api' => $namespace]);
        $this->app['config']->set('eg_r2.security', ['mapping' => [], 'undefined_scheme_policy' => 'ignore']);

        $this->artisan('eg-r2:generate-route')->assertExitCode(0);

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::as('api.')->group(function ()", $contents);
        self::assertStringNotContainsString("Route::as('api')->group(function ()", $contents);
    }

    /**
     * @param list<class-string> $controllers
     * @param array<string, mixed> $security
     */
    private function runCommandWithControllers(array $controllers, array $security): string
    {
        $routePath = $this->prepareRoutePath();
        $this->setupCommandConfig($routePath, $controllers, $security);

        $this->artisan('eg-r2:generate-route')->assertExitCode(0);

        return $routePath;
    }

    private function prepareRoutePath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'eg-r2-routes-');
        if ($path === false) {
            self::fail('failed to create temp route file path');
        }

        return $path;
    }

    /**
     * @param list<class-string> $controllers
     * @param array<string, mixed> $security
     */
    private function setupCommandConfig(string $routePath, array $controllers, array $security): void
    {
        $namespace = 'Tests\\Fixtures\\Security';

        $service = Mockery::mock(NameSpaceFindService::class);
        $service->shouldReceive('getClassesOfNameSpace')
            ->once()
            ->with($namespace)
            ->andReturn($controllers);

        $this->app->instance(NameSpaceFindService::class, $service);

        $this->app['config']->set('eg_r2.route_path', $routePath);
        $this->app['config']->set('eg_r2.namespaces', ['api.' => $namespace]);
        $this->app['config']->set('eg_r2.security', ['mapping' => [], 'undefined_scheme_policy' => 'ignore', 'multiple_requirements_policy' => 'error', ...$security]);
    }
}
