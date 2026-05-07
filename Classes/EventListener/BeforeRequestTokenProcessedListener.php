<?php

declare(strict_types=1);

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Mfc\OAuth2\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Security\SigningSecretResolver;

final readonly class BeforeRequestTokenProcessedListener
{
    public function __construct(private Context $context)
    {
    }
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        $request = $event->getRequest();

        if (empty($GLOBALS['TYPO3_REQUEST'])) {
            $GLOBALS['TYPO3_REQUEST'] = $request;
        }

        if (array_key_exists(RequestToken::PARAM_NAME, $request->getQueryParams())) {
            $jwt = $request->getQueryParams()[RequestToken::PARAM_NAME];
            $signingSecretResolver = $this->getSigningSecretResolver();

            $event->setRequestToken(RequestToken::fromHashSignedJwt($jwt, $signingSecretResolver));
        }
    }

    private function getSigningSecretResolver(): SigningSecretResolver
    {
        $context = $this->context;
        $securityAspect = SecurityAspect::provideIn($context);
        return $securityAspect->getSigningSecretResolver();
    }
}
