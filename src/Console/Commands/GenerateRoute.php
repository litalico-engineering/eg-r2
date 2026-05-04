<?php
declare(strict_types=1);

namespace Litalico\EgR2\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Litalico\EgR2\Services\NameSpaceFindService;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use OpenApi\Annotations\Operation;
use OpenApi\Generator;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use function array_fill;
use function array_filter;
use function array_key_exists;
use function array_map;
use function count;
use function explode;
use function implode;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;
use function trim;

/**
 * @package Litalico\EgR2\Console\Commands
 */
class GenerateRoute extends Command
{
    private const AND_DELIMITER = '&&';

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eg-r2:generate-route';

    /**
     * @inheritDoc
     */
    protected $description = 'Generate routing file from OpenAPI specifications.';

    /**
     * @var resource Temporary file pointer
     */
    private $fp;

    /**
     * Create a new command instance.
     *
     * @param NameSpaceFindService $nameSpaceFindService
     */
    public function __construct(private readonly NameSpaceFindService $nameSpaceFindService)
    {
        parent::__construct();
        $fp = tmpfile();
        if ($fp === false) {
            throw new RuntimeException('Creation of temporary file failed.');
        }
        $this->fp = $fp;
    }

    /**
     * Executes the console command. This method generates a routing file from OpenAPI specifications.
     *
     * @return int
     * @throws ReflectionException
     */
    public function handle(): int
    {
        $file = new PhpFile();
        $file->addComment('This file is auto-generated.');
        $file->setStrictTypes();
        $file->addUse(Route::class);

        fwrite($this->fp, (string)$file);

        $namespaces = config('eg_r2.namespaces', []);
        if (is_array($namespaces) === false) {
            $message = sprintf('Invalid configuration namespace. namespace: %s', var_export($namespaces, true));
            $this->error($message);

            throw new RuntimeException($message);
        }

        foreach ($namespaces as $group => $namespaceName) {
            if (!is_string($namespaceName)) {
                $message = sprintf('Invalid configuration namespace. namespace: %s', var_export($namespaces, true));
                $this->error($message);

                throw new RuntimeException($message);
            }
            $controllers = $this->nameSpaceFindService->getClassesOfNameSpace($namespaceName);

            $closure = new Closure();
            $bodies = '';
            foreach ($controllers as $controller) {
                $bodies .= $this->generateRoute($controller);
            }
            if (empty($bodies)) {
                continue;
            }
            $closure->setBody($bodies);
            $printClosure = (new PsrPrinter)->printClosure($closure);
            $literal = new Literal("Route::as(?)->group({$printClosure});\n", [$group]);

            fwrite($this->fp, (string) $literal);
        }

        $metaData = stream_get_meta_data($this->fp);
        if (isset($metaData['uri']) === false) {
            throw new RuntimeException('Failed to get uri.');
        }
        // Copy to root file
        File::copy($metaData['uri'], $this->getRoutePath());

        // Delete temporary files
        fclose($this->fp);

        return self::SUCCESS;
    }

    /**
     * @param class-string $controller The fully qualified class name of the controller.
     * @return Literal|null Returns a Literal object representing the route group for the controller, or null if no routes were generated.
     * @throws ReflectionException
     */
    private function generateRoute(string $controller): ?Literal
    {
        $reflection = new ReflectionClass($controller);
        $methods = $reflection->getMethods();
        $controllerSecurity = $this->extractClassSecurity($reflection);

        $closure = new Closure();
        $bodies = '';
        foreach ($methods as $method) {
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Operation) {
                    $middlewares = $this->resolveOperationMiddlewares($instance, $controllerSecurity);
                    $bodies .= $this->convertOperation($instance, $method->getName(), $middlewares);
                }
            }
        }
        if (empty($bodies)) {
            return null;
        }

        $closure->setBody($bodies);
        $printClosure = (new PsrPrinter)->printClosure($closure);
        // ? is the target of replacement, so escape
        $printClosure = str_replace('?', '\?', $printClosure);

        return new Literal("Route::controller(?)->group({$printClosure});\n", [$controller]);
    }

    /**
     * @param Operation $operation
     * @param string $action
     * @param list<string> $middlewares
     * @return Literal
     */
    private function convertOperation(Operation $operation, string $action, array $middlewares = []): Literal
    {
        $path = $operation->path;
        // Converts to Laravel path parameter format if `OptionalPathParameter` is specified in the 'x' attribute

        $x = $operation->x === Generator::UNDEFINED ? [] : $operation->x; // @phpstan-ignore-line
        foreach ($x as $key => $value) {
            if ($key === 'OptionalPathParameter' && $value === true) {
                // Change path parameters arbitrarily
                $path = str_replace('}', '\?}', $path);
            }
        }

        $literal = "Route::{$operation->method}(?,?)";
        $arguments = [$path, $action];

        if ($middlewares !== []) {
            $placeholders = implode(',', array_fill(0, count($middlewares), '?'));
            $literal .= "->middleware([{$placeholders}])";
            $arguments = [...$arguments, ...$middlewares];
        }

        $literal .= ";\n";

        return new Literal($literal, $arguments);
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @return list<array<string, array<int, string>>>
     */
    private function extractClassSecurity(ReflectionClass $reflection): array
    {
        foreach ($reflection->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            if (!property_exists($instance, 'security')) {
                continue;
            }

            $security = $instance->security;
            if (Generator::isDefault($security) || !is_array($security)) {
                return [];
            }

            return $this->normalizeSecurityRequirements($security);
        }

        return [];
    }

    /**
     * @param Operation $operation
     * @param list<array<string, array<int, string>>> $controllerSecurity
     * @return list<string>
     */
    private function resolveOperationMiddlewares(Operation $operation, array $controllerSecurity): array
    {
        $operationSecurity = $operation->security;
        if (Generator::isDefault($operationSecurity)) {
            $requirements = $controllerSecurity;
        } else {
            $requirements = $this->normalizeSecurityRequirements($operationSecurity);
        }

        if ($requirements === []) {
            return [];
        }

        return $this->convertSecurityRequirementsToMiddlewares($requirements);
    }

    /**
     * @param array<mixed, mixed> $requirements
     * @return list<array<string, array<int, string>>>
     */
    private function normalizeSecurityRequirements(array $requirements): array
    {
        $normalized = [];
        foreach ($requirements as $requirement) {
            if (!is_array($requirement)) {
                continue;
            }

            $normalizedRequirement = [];
            foreach ($requirement as $scheme => $scopes) {
                if (!is_string($scheme)) {
                    continue;
                }
                if (str_contains($scheme, self::AND_DELIMITER)) {
                    throw new RuntimeException(sprintf('Security scheme name must not include "%s": %s', self::AND_DELIMITER, $scheme));
                }

                $normalizedRequirement[$scheme] = $this->normalizeScopes($scopes);
            }

            if ($normalizedRequirement !== []) {
                $normalized[] = $normalizedRequirement;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $scopes
     * @return list<string>
     */
    private function normalizeScopes(mixed $scopes): array
    {
        if (!is_array($scopes)) {
            return [];
        }

        /** @var list<string> $normalized */
        $normalized = array_values(array_filter(array_map(static fn (mixed $scope): ?string => is_string($scope) ? $scope : null, $scopes)));

        return $normalized;
    }

    /**
     * @param list<array<string, array<int, string>>> $requirements
     * @return list<string>
     */
    private function convertSecurityRequirementsToMiddlewares(array $requirements): array
    {
        if (count($requirements) > 1) {
            $policy = config('eg_r2.security.multiple_requirements_policy', 'error');
            if ($policy === 'warning_skip') {
                $this->warn('OpenAPI security has multiple requirement objects. Skipping middleware generation.');

                return [];
            }
            if ($policy === 'warning_first') {
                $this->warn('OpenAPI security has multiple requirement objects. Using only the first requirement object.');
                $requirements = [$requirements[0]];
            } elseif ($policy === 'error') {
                throw new RuntimeException('OpenAPI security with multiple requirement objects is not supported by current middleware mapping policy.');
            } else {
                throw new RuntimeException(sprintf('Invalid multiple_requirements_policy: %s', var_export($policy, true)));
            }
        }

        return $this->convertSingleRequirementToMiddlewares($requirements[0]);
    }

    /**
     * @param array<string, array<int, string>> $requirement
     * @return list<string>
     */
    private function convertSingleRequirementToMiddlewares(array $requirement): array
    {
        $rawMapping = config('eg_r2.security.mapping', []);
        if (!is_array($rawMapping)) {
            throw new RuntimeException('Invalid configuration. eg_r2.security.mapping must be an array.');
        }

        /** @var array<string, string|array<mixed, mixed>> $mapping */
        $mapping = $rawMapping;

        $middlewares = [];

        if (count($requirement) > 1) {
            $compositeValue = $this->findCompositeMapping($requirement, $mapping);
            if ($compositeValue !== null) {
                $scopes = [];
                foreach ($requirement as $schemeScopes) {
                    foreach ($schemeScopes as $scope) {
                        if (!in_array($scope, $scopes, true)) {
                            $scopes[] = $scope;
                        }
                    }
                }

                return $this->convertMappingToMiddlewares($compositeValue, $scopes);
            }
        }

        foreach ($requirement as $scheme => $scopes) {
            if (!array_key_exists($scheme, $mapping)) {
                $this->handleUndefinedScheme($scheme);

                continue;
            }

            $middlewares = [...$middlewares, ...$this->convertMappingToMiddlewares($mapping[$scheme], array_values($scopes))];
        }

        return $middlewares;
    }

    /**
     * @param array<string, array<int, string>> $requirement
     * @param array<string, string|array<mixed, mixed>> $mapping
     * @return string|array<mixed, mixed>|null
     */
    private function findCompositeMapping(array $requirement, array $mapping): string|array|null
    {
        $requirementKey = $this->normalizeCompositeKey(array_keys($requirement));

        foreach ($mapping as $key => $value) {
            if (!str_contains($key, self::AND_DELIMITER)) {
                continue;
            }

            $mappingKey = $this->normalizeCompositeKey(explode(self::AND_DELIMITER, $key));
            if ($mappingKey === $requirementKey) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $schemes
     * @return string
     */
    private function normalizeCompositeKey(array $schemes): string
    {
        $normalizedSchemes = [];
        foreach ($schemes as $scheme) {
            $trimmed = trim($scheme);
            if ($trimmed !== '') {
                $normalizedSchemes[] = $trimmed;
            }
        }
        $uniqueSchemes = array_values(array_unique($normalizedSchemes));
        sort($uniqueSchemes);

        return implode(self::AND_DELIMITER, $uniqueSchemes);
    }

    /**
     * @param string|array<mixed, mixed> $mapping
     * @param list<string> $scopes
     * @return list<string>
     */
    private function convertMappingToMiddlewares(string|array $mapping, array $scopes): array
    {
        $mappings = is_string($mapping) ? [$mapping] : $mapping;

        $scopeLiteral = implode(',', $scopes);
        $middlewares = [];

        foreach ($mappings as $middleware) {
            if (!is_string($middleware)) {
                throw new RuntimeException('Invalid middleware mapping. Middleware must be string or list of strings.');
            }

            $middlewares[] = str_replace('{scopes}', $scopeLiteral, $middleware);
        }

        return $middlewares;
    }

    private function handleUndefinedScheme(string $scheme): void
    {
        $policy = config('eg_r2.security.undefined_scheme_policy', 'ignore');

        if ($policy === 'ignore') {
            return;
        }
        if ($policy === 'warning') {
            $this->warn(sprintf('OpenAPI security scheme "%s" has no middleware mapping. Skipped.', $scheme));

            return;
        }
        if ($policy === 'error') {
            throw new RuntimeException(sprintf('OpenAPI security scheme "%s" has no middleware mapping.', $scheme));
        }

        throw new RuntimeException(sprintf('Invalid undefined_scheme_policy: %s', var_export($policy, true)));
    }

    /**
     * Returns the path to the route file.
     *
     * @return string
     */
    private function getRoutePath(): string
    {
        /** @var string $path */
        $path = config('eg_r2.route_path', base_path('routes/eg_r2.php'));

        return $path;
    }
}
