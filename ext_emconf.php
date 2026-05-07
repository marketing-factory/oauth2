<?php

declare(strict_types=1);

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

$EM_CONF['oauth2'] = [
    'title' => 'OAuth2 authentication and authorization',
    'description' => 'Generic OAuth 2.0 authentication and authorization for TYPO3 CMS',
    'category' => 'system',
    'state' => 'stable',
    'author' => 'Christian Hellmund, Sebastian Klein, Simon Schmidt, Karoline Steinfatt, Christian Spoo',
    'author_email' => 'typo3@marketing-factory.de',
    'author_company' => 'Marketing Factory Digital GmbH',
    'version' => '3.3.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
