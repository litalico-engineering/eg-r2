<?php

declare(strict_types=1);

namespace Tests\Unit;

use Litalico\EgR2\Services\NameSpaceFindService;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\Fixtures\Security\ClassSecurityController;
use Tests\Fixtures\Security\CompositeMultiScopesSecurityController;
use Tests\Fixtures\Security\CompositeSecurityController;
use Tests\Fixtures\Security\MultipleRequirementsSecurityController;
use Tests\Fixtures\Security\UndefinedSchemeSecurityController;
use Tests\TestCase;

class GenerateRouteCommandTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        require_once __DIR__ . '/../Fixtures/SecurityControllers.php';
    }

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

        self::assertStringContainsString("Route::get('/composite','index')->middleware(['auth:bearer_and_api','authorize.scope:read']);", $contents);
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

        self::assertStringContainsString("Route::get('/composite-multi-scopes','index')->middleware(['auth:bearer_and_api','authorize.scope:read,write']);", $contents);
    }

    #[Test]
    public function it_inherits_class_level_security_and_allows_operation_empty_override(): void
    {
        $routePath = $this->runCommandWithControllers(
            [ClassSecurityController::class],
            [
                'mapping' => [
                    'bearerAuth' => ['auth:api', 'scope:{scopes}'],
                ],
            ]
        );

        $contents = (string) file_get_contents($routePath);

        self::assertStringContainsString("Route::get('/class-inherited','inherited')->middleware(['auth:api','scope:write']);", $contents);
        self::assertStringContainsString("Route::get('/class-override-empty','overrideEmpty');", $contents);
        self::assertStringNotContainsString("Route::get('/class-override-empty','overrideEmpty')->middleware", $contents);
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

        self::assertStringContainsString("Route::get('/multi','multi')->middleware(['auth:api']);", $contents);
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

        self::assertStringContainsString("Route::get('/multi','multi');", $contents);
        self::assertStringNotContainsString("Route::get('/multi','multi')->middleware", $contents);
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
