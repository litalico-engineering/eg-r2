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

/**
 * @package Litalico\EgR2\Console\Commands
 */
class GenerateRoute extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'eg-r2:generate-route';

    /**
     * The console command description.
     *
     * @var null|string
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
     * Execute the console command.
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
            $message = sprintf("Invalid configuration namespace. namespace: %s", var_export($namespaces, true));
            $this->error($message);
            throw new RuntimeException($message);
        }

        foreach ($namespaces as $group => $namespaceName) {
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

        // Copy to root file
        File::copy(stream_get_meta_data($this->fp)['uri'], $this->getRoutePath());

        // Delete temporary files
        fclose($this->fp);

        return self::SUCCESS;
    }

    /**
     * @param class-string $controller
     * @return Literal|null
     * @throws ReflectionException
     */
    private function generateRoute(string $controller): ?Literal
    {
        $reflection = new ReflectionClass($controller);
        $methods = $reflection->getMethods();

        $closure = new Closure();
        $bodies = '';
        foreach ($methods as $method) {
            $attributes = $method->getAttributes();

            foreach ($attributes as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof Operation) {
                    $bodies .= $this->convertOperation($instance, $method->getName());
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
     * @return Literal
     */
    private function convertOperation(Operation $operation, string $action): Literal
    {
        $path = $operation->path;
        // Specify Laravel own url description format
        if ($operation->x !== Generator::UNDEFINED) {
            foreach ($operation->x as $key => $value) {
                if ($key === 'OptionalPathParameter' && $value === true) {
                    // Change path parameters arbitrarily
                    $path = str_replace('}', '\?}', $path);
                }
            }
        }

        return new Literal("Route::{$operation->method}(?,?);\n", [$path, $action]);
    }

    /**
     * @return string
     */
    private function getRoutePath(): string
    {
        /** @var string $path */
        $path = config('eg_r2.route_path', base_path("routes/eg_r2.php"));

        return $path;
    }
}
