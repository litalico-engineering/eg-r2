<?php
declare(strict_types=1);

namespace Litalico\EgR2\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionException;

/**
 * Class to search for classes in namespace
 * @package Litalico\EgR2\Services
 */
class NameSpaceFindService
{
    /**
     * @var array<string, list<class-string>> $namespaceMap
     */
    private static array $namespaceMap = [];

    /** @var string */
    public const DEFAULT_NAMESPACE = 'global';

    /**
     * @throws FileNotFoundException|ReflectionException
     */
    public function __construct()
    {
        if (empty(self::$namespaceMap)) {
            $this->traverseClasses();
        }
    }

    /**
     * @param class-string $class
     * @return string
     * @throws ReflectionException
     */
    private function getNameSpaceFromClass(string $class): string
    {
        $reflection = new ReflectionClass($class);

        return $reflection->getNamespaceName() === ''
            ? self::DEFAULT_NAMESPACE
            : $reflection->getNamespaceName();
    }

    /**
     * @return void
     * @throws ReflectionException|FileNotFoundException
     */
    public function traverseClasses(): void
    {
        $composer = require base_path() . '/vendor/autoload.php';
        $jsonContents = file_get_contents('composer.json');
        if ($jsonContents === false) {
            throw new FileNotFoundException("composer.json not found");
        }
        /** @var array{autoload: array{"psr-4": array<string, string>}} $composerJson */
        $composerJson = json_decode($jsonContents);
        $psr4 = $composerJson['autoload']['psr-4'];

        /** @var class-string $class */
        foreach (array_keys($composer->getClassMap()) as $class) {
            foreach ($psr4 as $namespace => $directory) {
                if (str_starts_with($class, $namespace)) {
                    self::$namespaceMap[$this->getNameSpaceFromClass($class)][] = $class;
                }
            }
        }
    }

    /**
     * @return string[]
     */
    public function getNameSpaces(): array
    {
        return array_keys(self::$namespaceMap);
    }

    /**
     * @param string $namespace
     * @return list<class-string>
     */
    public function getClassesOfNameSpace(string $namespace): array
    {
        if (!isset(self::$namespaceMap[$namespace])) {
            throw new InvalidArgumentException("The Namespace $namespace does not exist.");
        }

        return self::$namespaceMap[$namespace];
    }
}
