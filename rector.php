<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\CodeQuality\Rector\Empty_\SimplifyEmptyCheckOnEmptyArrayRector;
use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodeQuality\Rector\If_\ExplicitBoolCompareRector;
use Rector\CodeQuality\Rector\If_\ShortenElseIfRector;
use Rector\CodingStyle\Rector\FuncCall\ArraySpreadInsteadOfArrayMergeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\DeadCode\Rector\Ternary\TernaryToBooleanOrFalseToBooleanAndRector;
use Rector\Php71\Rector\List_\ListToArrayDestructRector;
use Rector\PHPUnit\PHPUnit100\Rector\Class_\StaticDataProviderClassMethodRector;
use Rector\Strict\Rector\Empty_\DisallowedEmptyRuleFixerRector;

define('LARAVEL_VERSION', '10.0');

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/tests',
    ])
    ->withAutoloadPaths([
        __DIR__ . '/vendor/autoload.php',
    ])
    ->withBootstrapFiles([
    ])
    ->withCache(cacheDirectory: './.rector', cacheClass: FileCacheStorage::class)
    // register single rule
    ->withPhpSets()
    ->withRules([
        StaticDataProviderClassMethodRector::class,
        ExplicitBoolCompareRector::class,
        ArraySpreadInsteadOfArrayMergeRector::class,
    ])
    ->withSkip([
        RemoveUselessParamTagRector::class,
        RemoveUselessReturnTagRector::class,
        SimplifyEmptyCheckOnEmptyArrayRector::class,
        FlipTypeControlToUseExclusiveTypeRector::class,
        DisallowedEmptyRuleFixerRector::class,
        RemoveUselessVarTagRector::class,
        TernaryToBooleanOrFalseToBooleanAndRector::class,
        ShortenElseIfRector::class,
        ListToArrayDestructRector::class,
    ])
    // here we can define, what prepared sets of rules will be applied
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true
    );
