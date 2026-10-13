<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassConst\RemoveUnusedPrivateClassConstantRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src', __DIR__ . '/tests'])
    ->withSkip([
        __DIR__ . '/demo',
        // Doctrine calls postGenerateSchema() and passes the replaced listener's constructor args.
        __DIR__ . '/tests/Kernel/NoOpPostGenerateSchemaListener.php',
        RemoveUnusedPrivateClassConstantRector::class => [
            __DIR__ . '/tests/Unit/DependencyInjection/Compiler/AutoTagPermissionCheckersPassTest.php',
        ],
    ])
    ->withPhpSets(php82: true)
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
    );
