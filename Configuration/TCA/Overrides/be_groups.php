<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3') || die();

call_user_func(function (): void {
    $columns = [
        'gitlabGroup' => [
            'exclude' => 1,
            'label' => 'LLL:EXT:oauth2/Resources/Private/Language/locallang.xlf:columnLabel.gitlabGroup',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => [
                    ['label' => 'Guest', 'value' => 10],
                    ['label' => 'Reporter', 'value' => 20],
                    ['label' => 'Developer', 'value' => 30],
                    ['label' => 'Master', 'value' => 40],
                    ['label' => 'Owner', 'value' => 50],
                ],
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('be_groups', $columns);
    ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'gitlabGroup');
});
