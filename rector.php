<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Class_\CompleteDynamicPropertiesRector;
use Rector\CodeQuality\Rector\Ternary\ArrayKeyExistsTernaryThenValueToCoalescingRector;
use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictNativeCallRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnTypeFromStrictScalarReturnExprRector;

return static function (RectorConfig $rectorConfig): void {

    $rectorConfig->paths([
        __DIR__ . '/'

    ]);

    $rectorConfig->skip([
        __DIR__ . '/src/App/Migrations',
        __DIR__ . '/vendor/*',
        __DIR__ . '/src/proxy/*'
    ]);

    $rectorConfig->rules([
        ReturnTypeFromStrictNativeCallRector::class,
        ReturnTypeFromStrictScalarReturnExprRector::class,
        ArrayKeyExistsTernaryThenValueToCoalescingRector::class,


    ]);

    $rectorConfig->skip([
            ClassPropertyAssignToConstructorPromotionRector::class
    ]);

    $rectorConfig->sets([
        PHPUnitSetList::PHPUNIT_100,
        SetList::PHP_83,
        SetList::CODE_QUALITY,
        //SetList::DEAD_CODE,
        //SetList::NAMING,
        SetList::TYPE_DECLARATION,
    ]);
};
