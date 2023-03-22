<?php

defined('TYPO3') || die();

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addService(
    'oauth2',
    'auth',
    \Mfc\OAuth2\Services\OAuth2LoginService::class,
    [
        'title' => 'OAuth Authentication',
        'description' => 'OAuth authentication service for backend users',
        'subtype' => 'getUserBE,authUserBE',
        'available' => true,
        'priority' => 75,
        'quality' => 50,
        'os' => '',
        'exec' => '',
        'className' => \Mfc\OAuth2\Services\OAuth2LoginService::class
    ]
);

$enableBackendLogin = (bool)\TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(
    \TYPO3\CMS\Core\Configuration\ExtensionConfiguration::class
)->get('oauth2', 'enableBackendLogin');

if ($enableBackendLogin) {
    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][1529672977] = [
        'provider' => \Mfc\OAuth2\LoginProvider\OAuth2LoginProvider::class,
        'sorting' => 25,
        'iconIdentifier' => 'mfc-oauth2-login',
        'label' => 'LLL:EXT:oauth2/Resources/Private/Language/locallang.xlf:oauth2.login.link'
    ];
}
