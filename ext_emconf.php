<?php

$EM_CONF['oauth2'] = [
    'title' => 'OAuth2 authentication and authorization',
    'description' => 'Generic OAuth 2.0 authentication and authorization for TYPO3 CMS',
    'category' => 'system',
    'state' => 'stable',
    'author' => 'Christian Hellmund, Sebastian Klein, Simon Schmidt, Karoline Steinfatt, Christian Spoo',
    'author_email' => 'typo3@marketing-factory.de',
    'author_company' => 'Marketing Factory Consulting GmbH',
    'version' => '3.3.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99'
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
