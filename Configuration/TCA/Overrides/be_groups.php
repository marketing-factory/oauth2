<?php

defined('TYPO3') || die();

call_user_func(function () {
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

    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_groups', $columns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'gitlabGroup');
});
