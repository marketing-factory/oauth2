<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function () {

    $ll = 'LLL:EXT:oauth2/Resources/Private/Language/locallang.xlf:';

    $columns = [
        'gitlabGroup' => [
            'exclude' => 1,
            'label' => $ll . 'columnLabel.gitlabGroup',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectCheckBox',
                'items' => [
                    ['Guest', 10],
                    ['Reporter', 20],
                    ['Developer', 30],
                    ['Master', 40],
                    ['Owner', 50],
                ],
            ],
        ],
    ];


    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_groups', $columns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_groups', 'gitlabGroup');

});
