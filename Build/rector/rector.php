<?php

declare(strict_types=1);

/*
 * This file is part of the package mfd/typo3_fal_checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\Php81\Rector\FuncCall\NullToStrictStringFuncCallArgRector;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\SafeDeclareStrictTypesRector;
use Rector\ValueObject\PhpVersion;
use Ssch\TYPO3Rector\CodeQuality\General\ExtEmConfRector;
use Ssch\TYPO3Rector\Configuration\Typo3Option;
use Ssch\TYPO3Rector\Set\Typo3LevelSetList;
use Ssch\TYPO3Rector\Set\Typo3SetList;

return RectorConfig::configure()
    ->withPaths([
        __DIR__ . '/../../Classes',
        __DIR__ . '/../../Configuration',
        //        __DIR__ . '/../../Tests',
        __DIR__ . '/../../ext_emconf.php',
    ])
    ->withPreparedSets(
        deadCode: true,
        codeQuality: true,
        typeDeclarations: true,
        privatization: true,
        instanceOf: true,
        earlyReturn: true,
    )
    ->withPhpSets(php82: true)
    ->withPhpVersion(PhpVersion::PHP_82)
    ->withSets([
        Typo3SetList::CODE_QUALITY,
        Typo3SetList::GENERAL,
        Typo3LevelSetList::UP_TO_TYPO3_13,
    ])
    ->withPHPStanConfigs([Typo3Option::PHPSTAN_FOR_RECTOR_PATH])
    ->withConfiguredRule(ExtEmConfRector::class, [
        ExtEmConfRector::PHP_VERSION_CONSTRAINT => '8.2.0-8.5.99',
        ExtEmConfRector::TYPO3_VERSION_CONSTRAINT => '13.4.0-14.4.99',
        ExtEmConfRector::ADDITIONAL_VALUES_TO_BE_REMOVED => [],
    ])
    ->withConfiguredRule(EncapsedStringsToSprintfRector::class, [
        'always' => true,
        // force sprintf even for simple cases
    ])
    ->withImportNames(importShortClasses: false, removeUnusedImports: true)
    ->withSkip([
        NullToStrictStringFuncCallArgRector::class,
        '*Build/*',
        'Classes/Controller/Backend/ElementInformationController.php',
        '*Resources/*',
        '*Model/*',
        SafeDeclareStrictTypesRector::class => [
            'ext_emconf.php',
        ],
    ]);
