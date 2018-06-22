<?php

/**
 * Extension Manager/Repository config file for ext "site_verkehrskadetten_aachen".
 */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Generic OAuth2 authentication and authorization for TYPO3 CMS',
    'description' => '',
    'category' => 'system',
    'constraints' => [
        'depends' => [
            'typo3' => '8.7.0-8.7.99'
        ],
        'conflicts' => [
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'Mfc\\OAuth2\\' => 'Classes'
        ],
    ],
    'state' => 'alpha',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 1,
    'author' => 'Christian Spoo',
    'author_email' => 'cs@marketing-factory.de',
    'author_company' => 'Marketing-Factory Consulting GmbH',
    'version' => '0.0.1-alpha',
];
