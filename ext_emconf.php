<?php

declare(strict_types=1);

/*
 * This file is part of the package mfc/oauth2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF[$_EXTKEY] = [
    'title' => 'OAuth2 authentication and authorization',
    'description' => 'Generic OAuth 2.0 authentication and authorization for TYPO3 CMS',
    'category' => 'system',
    'state' => 'stable',
    'author' => 'Christian Hellmund, Sebastian Klein, Simon Schmidt, Karoline Steinfatt, Christian Spoo',
    'author_email' => 'info@marketing-factory.de',
    'author_company' => 'Marketing Factory Digital GmbH',
    'version' => '4.1.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
