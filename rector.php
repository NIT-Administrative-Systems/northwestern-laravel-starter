<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\EarlyReturn\Rector\Return_\ReturnBinaryOrToEarlyReturnRector;
use Rector\Php74\Rector\Closure\ClosureToArrowFunctionRector;
use Rector\Php81\Rector\MethodCall\SpatieEnumMethodCallToEnumConstRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/public',
        __DIR__ . '/resources',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withSkip([
        AddOverrideAttributeToOverriddenMethodsRector::class,
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        ReturnBinaryOrToEarlyReturnRector::class,
        ClosureToArrowFunctionRector::class,
        SpatieEnumMethodCallToEnumConstRector::class,
    ])
    ->withPhpSets()
    ->withRules([
        RectorLaravel\Rector\If_\AbortIfRector::class,
        RectorLaravel\Rector\If_\ReportIfRector::class,
        RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector::class,
        RectorLaravel\Rector\Class_\AddExtendsAnnotationToModelFactoriesRector::class,
        RectorLaravel\Rector\Class_\AnonymousMigrationsRector::class,
        RectorLaravel\Rector\Expr\AppEnvironmentComparisonToParameterRector::class,
        RectorLaravel\Rector\Empty_\EmptyToBlankAndFilledFuncRector::class,
        RectorLaravel\Rector\MethodCall\AvoidNegatedCollectionFilterOrRejectRector::class,
        RectorLaravel\Rector\Cast\DatabaseExpressionCastsToMethodCallRector::class,
        RectorLaravel\Rector\PropertyFetch\OptionalToNullsafeOperatorRector::class,
        RectorLaravel\Rector\PropertyFetch\ReplaceFakerInstanceWithHelperRector::class,
        RectorLaravel\Rector\FuncCall\NotFilledBlankFuncCallToBlankFilledFuncCallRector::class,
        RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector::class,
        RectorLaravel\Rector\StaticCall\RouteActionCallableRector::class,
        RectorLaravel\Rector\Expr\SubStrToStartsWithOrEndsWithStaticMethodCallRector\SubStrToStartsWithOrEndsWithStaticMethodCallRector::class,

        PHPUnit\AnnotationsToAttributes\Rector\Class_\CoversAnnotationWithValueToAttributeRector::class,
        PHPUnit\AnnotationsToAttributes\Rector\ClassMethod\DataProviderAnnotationToAttributeRector::class,
        PHPUnit\PHPUnit110\Rector\Class_\NamedArgumentForDataProviderRector::class,
        PHPUnit\PHPUnit90\Rector\MethodCall\ReplaceAtMethodWithDesiredMatcherRector::class,
        PHPUnit\PHPUnit70\Rector\Class_\RemoveDataProviderTestPrefixRector::class,
        PHPUnit\PHPUnit80\Rector\MethodCall\SpecificAssertContainsRector::class,
        PHPUnit\PHPUnit100\Rector\MethodCall\PropertyExistsWithoutAssertRector::class,
        PHPUnit\PHPUnit100\Rector\Class_\PublicDataProviderClassMethodRector::class,
        PHPUnit\PHPUnit100\Rector\Class_\StaticDataProviderClassMethodRector::class,
        PHPUnit\CodeQuality\Rector\MethodCall\AssertNotOperatorRector::class,
        PHPUnit\CodeQuality\Rector\MethodCall\AssertCompareOnCountableWithMethodToAssertCountRector::class,
        PHPUnit\CodeQuality\Rector\MethodCall\FlipAssertRector::class,
        PHPUnit\CodeQuality\Rector\FuncCall\AssertFuncCallToPHPUnitAssertRector::class,
        PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector::class,
    ])
    ->withCache(
        cacheDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withParallel();
