<?php

$EM_CONF['oauth2'] = [
    'title' => 'OAuth2 authentication and authorization',
    'description' => 'Generic OAuth 2.0 authentication and authorization for TYPO3 CMS',
    'category' => 'system',
    'state' => 'beta',
    'author' => 'Christian Hellmund, Simon Schmidt, Christian Spoo',
    'author_email' => 'typo3@marketing-factory.de',
    'author_company' => 'Marketing Factory Consulting GmbH',
    'version' => '3.0.5',
    'constraints' => [
        'depends' => [
            'typo3' => '12.2.0-12.4.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
