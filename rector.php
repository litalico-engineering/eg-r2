<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Attribute\SortAttributeNamedArgsRector;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\FuncCall\SortCallLikeNamedArgsRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\DeadCode\Rector\Ternary\TernaryToBooleanOrFalseToBooleanAndRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\PHPUnit\PHPUnit100\Rector\Class_\StaticDataProviderClassMethodRector;

define('LARAVEL_VERSION', '10.0');

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/tests',
        __DIR__ . '/src',
    ])
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ])
    ->withBootstrapFiles([
    ])
    ->withCache(cacheDirectory: './.rector', cacheClass: FileCacheStorage::class)
    // register single rule
    ->withPhpSets(php84: true)
    ->withRules([
        StaticDataProviderClassMethodRector::class,
    ])
    ->withSkip([
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        RemoveUselessVarTagRector::class,
        TernaryToBooleanOrFalseToBooleanAndRector::class,
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
        ListToArrayDestructRector::class,
        SortAttributeNamedArgsRector::class,
        SortCallLikeNamedArgsRector::class,
    ])
    // here we can define, what prepared sets of rules will be applied
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true
    );
