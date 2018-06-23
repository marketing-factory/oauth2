<?php
defined('TYPO3_MODE') || die('Access denied.');

call_user_func(function () {

    $ll = 'LLL:EXT:oauth2/Resources/Private/Language/locallang.xlf:';

    $columns = array(
        'oauth_identifier' => array(
            'exclude' => 1,
            'label' => $ll . 'columnLabel.oauth_identifier',
            'config' => array(
                'type' => 'input',
                'readOnly' => 1
            ),
        ),
    );


    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('be_users', $columns);
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('be_users', 'oauth_identifier');

});
