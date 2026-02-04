<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\SetList;
use Rector\Set\ValueObject\LevelSetList;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessParamTagRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUselessReturnTagRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\TypeDeclaration\Rector\ClassMethod\AddVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        // Skip stubs - they're templates with placeholders
        __DIR__ . '/stubs',
        // Skip vendor
        __DIR__ . '/vendor',
    ])
    ->withPhpSets(php84: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    )
    ->withRules([
        InlineConstructorDefaultToPropertyRector::class,
        AddVoidReturnTypeWhereNoReturnRector::class,
    ])
    ->withSkip([
        // Don't remove "unused" methods that are called dynamically
        RemoveUnusedPrivateMethodRector::class => [
            __DIR__ . '/src/Core/Router.php',
            __DIR__ . '/src/Console/Command.php',
        ],
        // Keep property tags for IDE support in some files
        RemoveUnusedPrivatePropertyRector::class => [
            __DIR__ . '/src/Model/Model.php',
        ],
    ]);
