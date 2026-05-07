<?php

declare(strict_types=1);

/*
 * This file is part of the package mfd/typo3-fal-checker.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Mfc\OAuth2\ResourceServer;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak as KeycloakOAuthProvider;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Keycloak extends AbstractResourceServer
{
    private readonly string $providerName;

    private AbstractProvider $oauthProvider;

    private readonly array $oauthProviderConfiguration;

    private readonly int $userOption;

    private bool $userDetailsLoaded = false;

    /**
     * @var list<string>
     */
    private array $keycloakDefaultGroups = [];

    public function __construct(array $arguments)
    {
        $this->providerName = $arguments['providerName'];
        $this->keycloakDefaultGroups = GeneralUtility::trimExplode(',', $arguments['keycloakDefaultGroups'], true);
        $this->userOption = (int)$arguments['keycloakUserOption'];

        [$redirectUri] = $this->getRedirectUri($this->providerName);
        $this->oauthProviderConfiguration = [
            'authServerUrl' => $arguments['authServerUrl'],
            'realm' => $arguments['realm'],
            'clientId' => $arguments['clientId'],
            'clientSecret' => $arguments['clientSecret'],
            'redirectUri' => $redirectUri,
        ];

        $this->oauthProvider = new KeycloakOAuthProvider($this->oauthProviderConfiguration);
    }

    public function getOAuthProvider(string $requestToken = ''): AbstractProvider
    {
        if ($requestToken !== '') {
            [$redirectUri] = $this->getRedirectUri($this->providerName, false, $requestToken);

            $oauthProviderConfiguration = array_merge(
                $this->oauthProviderConfiguration,
                ['redirectUri' => $redirectUri]
            );
            $this->oauthProvider = new KeycloakOAuthProvider($oauthProviderConfiguration);
        }
        return $this->oauthProvider;
    }

    public function getAuthorizationUrl(): array
    {
        [$redirectUri, $nonceCookie] = $this->getRedirectUri($this->providerName, true);

        $oauthProviderConfiguration = array_merge(
            $this->oauthProviderConfiguration,
            ['redirectUri' => $redirectUri]
        );
        $this->oauthProvider = new KeycloakOAuthProvider($oauthProviderConfiguration);

        return [
            $this->oauthProvider->getAuthorizationUrl([ 'scope' => ['profile', 'email', 'openid'] ]),
            $this->oauthProvider->getState(),
            $nonceCookie,
        ];
    }

    public function userShouldBeAdmin(ResourceOwnerInterface $user): bool
    {
        return true;
    }

    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime
    {
        return null;
    }

    public function userIsActive(ResourceOwnerInterface $user): bool
    {
        return true;
    }

    public function getOAuthIdentifier(ResourceOwnerInterface $user): string
    {
        return $this->providerName . '|' . $user->getId();
    }

    public function loadUserDetails(ResourceOwnerInterface $user): void
    {
        if ($this->userDetailsLoaded) {
            return;
        }

        if (!$user instanceof KeycloakResourceOwner) {
            throw new \InvalidArgumentException(
                'Resource owner "' . $user->getId() . '" is no suitable Keycloak resource owner',
                2571410682
            );
        }

        $this->userDetailsLoaded = true;
    }

    public function getUsernameFromUser(ResourceOwnerInterface $user): string
    {
        /** @var KeycloakResourceOwner $user */
        return $user->getUsername();
    }

    public function getEmailFromUser(ResourceOwnerInterface $user): string
    {
        /** @var KeycloakResourceOwner $user */
        return $user->getEmail();
    }

    public function updateUserRecord(
        ResourceOwnerInterface $user,
        ?array $currentRecord = null,
        array $authenticationInformation = [],
        ?PasswordHashInterface $saltingInstance = null
    ): array {
        $user->toArray();

        if (!is_array($currentRecord)) {
            $currentRecord = [
                'pid' => 0,
                'password' => $saltingInstance->getHashedPassword(md5(uniqid())),
            ];
        }

        /** @var KeycloakResourceOwner $user */
        return array_merge(
            $currentRecord,
            [
                'email' => $user->getEmail(),
                'username' => $this->getUsernameFromUser($user),
                'usergroup' => $this->keycloakDefaultGroups,
                'options' => $this->userOption,
            ]
        );
    }
}
