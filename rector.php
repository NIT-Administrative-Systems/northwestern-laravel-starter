<?php

declare(strict_types=1);

use Rector\Caching\ValueObject\Storage\FileCacheStorage;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\StaticCall\RemoveParentCallWithoutParentRector;
use Rector\Php81\Rector\MethodCall\SpatieEnumMethodCallToEnumConstRector;
use Rector\Php85\Rector\Property\AddOverrideAttributeToOverriddenPropertiesRector;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ArrowFunction\AddArrowFunctionReturnTypeRector;
use Rector\TypeDeclaration\Rector\Closure\AddClosureVoidReturnTypeWhereNoReturnRector;
use Rector\TypeDeclaration\Rector\Closure\ClosureReturnTypeRector;

return RectorConfig::configure()
    ->withPHPStanConfigs([
        __DIR__ . '/phpstan-rector.neon',
    ])
    ->withPaths([
        __DIR__ . '/app',
        __DIR__ . '/config',
        __DIR__ . '/database',
        __DIR__ . '/routes',
        __DIR__ . '/tests',
    ])
    ->withPhpSets()
    ->withSets([
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        phpunitCodeQuality: true,
    )
    ->withComposerBased(
        phpunit: true,
    )
    ->withSkip([
        __DIR__ . '/vendor',
        __DIR__ . '/storage',
        __DIR__ . '/bootstrap/cache',
        AddClosureVoidReturnTypeWhereNoReturnRector::class,
        ClosureReturnTypeRector::class,
        AddArrowFunctionReturnTypeRector::class,
        RemoveParentCallWithoutParentRector::class,
        AddOverrideAttributeToOverriddenPropertiesRector::class,
        // Rewrites spatie/enum calls such as Spatie Health's Status::ok() to Status::OK, a constant
        // that class doesn't have: it is a spatie/enum class, not a native enum.
        SpatieEnumMethodCallToEnumConstRector::class,
        // toArray() -> all() change breaks PHPStan type checking for Filament's HtmlString options
        RectorLaravel\Rector\MethodCall\ConvertEnumerableToArrayToAllRector::class => [
            __DIR__ . '/app/Filament/*/*',
        ],
    ])
    ->withRules([
        // Laravel: classes / migrations / model structure
        RectorLaravel\Rector\Class_\AnonymousMigrationsRector::class,
        RectorLaravel\Rector\Class_\AddExtendsAnnotationToModelFactoriesRector::class,
        RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector::class,
        RectorLaravel\Rector\ClassMethod\MakeModelAttributesAndScopesProtectedRector::class,

        // Laravel: query builder / Eloquent typing and relation helpers
        RectorLaravel\Rector\MethodCall\EloquentWhereRelationTypeHintingParameterRector::class,
        RectorLaravel\Rector\MethodCall\EloquentWhereTypeHintClosureParameterRector::class,
        RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector::class,

        // Laravel: collections / fluent chain cleanup
        RectorLaravel\Rector\BooleanNot\AvoidNegatedCollectionContainsOrDoesntContainRector::class,
        RectorLaravel\Rector\MethodCall\AvoidNegatedCollectionFilterOrRejectRector::class,
        RectorLaravel\Rector\MethodCall\ConvertEnumerableToArrayToAllRector::class,
        RectorLaravel\Rector\MethodCall\UnaliasCollectionMethodsRector::class,
        RectorLaravel\Rector\MethodCall\ReverseConditionableMethodCallRector::class,

        // Laravel: control flow / defaults / app helpers
        RectorLaravel\Rector\If_\AbortIfRector::class,
        RectorLaravel\Rector\If_\ReportIfRector::class,
        RectorLaravel\Rector\Coalesce\ApplyDefaultInsteadOfNullCoalesceRector::class,
        RectorLaravel\Rector\Expr\AppEnvironmentComparisonToParameterRector::class,
        RectorLaravel\Rector\FuncCall\AppToResolveRector::class,

        // Laravel: framework helper normalizations + misc refactors
        RectorLaravel\Rector\FuncCall\NotFilledBlankFuncCallToBlankFilledFuncCallRector::class,
        RectorLaravel\Rector\FuncCall\RemoveDumpDataDeadCodeRector::class,
        RectorLaravel\Rector\FuncCall\SleepFuncToSleepStaticCallRector::class,
        RectorLaravel\Rector\FuncCall\ThrowIfAndThrowUnlessExceptionsToUseClassStringRector::class,
        RectorLaravel\Rector\Expr\SubStrToStartsWithOrEndsWithStaticMethodCallRector\SubStrToStartsWithOrEndsWithStaticMethodCallRector::class,
        RectorLaravel\Rector\Cast\DatabaseExpressionCastsToMethodCallRector::class,
        RectorLaravel\Rector\PropertyFetch\OptionalToNullsafeOperatorRector::class,
        RectorLaravel\Rector\PropertyFetch\ReplaceFakerInstanceWithHelperRector::class,
        RectorLaravel\Rector\StaticCall\RouteActionCallableRector::class,

        // Laravel :testing helpers
        RectorLaravel\Rector\MethodCall\AssertStatusToAssertMethodRector::class,
        RectorLaravel\Rector\MethodCall\JsonCallToExplicitJsonCallRector::class,
        RectorLaravel\Rector\StaticCall\AssertWithClassStringToTypeHintedClosureRector::class,
        RectorLaravel\Rector\StaticCall\CarbonSetTestNowToTravelToRector::class,
    ])
    ->withCache(
        cacheDirectory: sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rector',
        cacheClass: FileCacheStorage::class,
    )
    ->withParallel();
