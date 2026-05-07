<?php

declare(strict_types=1);

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

defined('TYPO3') || die();

call_user_func(function (): void {

    $ll = 'LLL:EXT:oauth2/Resources/Private/Language/locallang.xlf:';

    $columns = [
        'oauth_identifier' => [
            'exclude' => 1,
            'label' => $ll . 'columnLabel.oauth_identifier',
            'config' => [
                'type' => 'input',
                'readOnly' => 1,
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('be_users', $columns);
    ExtensionManagementUtility::addToAllTCAtypes('be_users', 'oauth_identifier');

});
