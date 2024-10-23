<?php

use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\Php80\Rector\Class_\StringableForToStringRector;
use Rector\Php80\Rector\Catch_\RemoveUnusedVariableInCatchRector;
use Rector\Symfony\Set\SymfonySetList;

return RectorConfig::configure()
    ->withParallel()
    ->withPaths([
        __DIR__ . '/src',
        __DIR__ . '/templates',
        __DIR__ . '/config',
    ])
    ->withPhpVersion(\Rector\ValueObject\PhpVersion::PHP_83)
    ->withPhpSets(php83: true)
    ->withSkip([
        __DIR__ . '/config/bundles.php',
        RemoveUnusedVariableInCatchRector::class,
        StringableForToStringRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
        NullToStrictStringFuncCallArgRector::class,
        ReadOnlyPropertyRector::class,
        Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector::class,
        Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector::class,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        codingStyle: true,
        typeDeclarations: true,
        privatization: true,
        //naming: true,
        instanceOf: true,
        earlyReturn: true,
        strictBooleans: true
    )
    ->withAttributesSets(
        symfony: true,
        doctrine: true,
    )
    ->withSets([
        \Rector\Set\ValueObject\LevelSetList::UP_TO_PHP_83,
        \Rector\Symfony\Set\SymfonySetList::SYMFONY_71,
        \Rector\Symfony\Set\SymfonySetList::SYMFONY_CODE_QUALITY,
        //SymfonySetList::SYMFONY_CONSTRUCTOR_INJECTION,
    ])
    //->withTypeCoverageLevel(1)
    //->withDeadCodeLevel(1)
    ->withImportNames(true, true, false, true)
;
