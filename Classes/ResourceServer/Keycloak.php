<?php

declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Stevenmaguire\OAuth2\Client\Provider\Keycloak as KeycloakOAuthProvider;
use Stevenmaguire\OAuth2\Client\Provider\KeycloakResourceOwner;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Keycloak extends AbstractResourceServer
{
    private string $providerName;
    private AbstractProvider $oauthProvider;
    private array $oauthProviderConfiguration;

    private int $userOption;
    private bool $userDetailsLoaded = false;
    /**
     * @var int[]
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
                'Resource owner "' . $user->getId() . '" is no suitable Keycloak resource owner'
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
        $userData = $user->toArray();

        if (!is_array($currentRecord)) {
            $currentRecord = [
                'pid' => 0,
                'password' => $saltingInstance->getHashedPassword(md5(uniqid()))
            ];
        }

        /** @var KeycloakResourceOwner $user */
        return array_merge(
            $currentRecord,
            [
                'email' => $user->getEmail(),
                'username' => $this->getUsernameFromUser($user),
                'usergroup' => $this->keycloakDefaultGroups,
                'options' => $this->userOption
            ]
        );
    }

    private function getUserGroupsForUser(
        array $defaultUserGroups,
        int $userLevel = 0,
        string $table = 'be_groups'
    ): string {
        $userGroups = $defaultUserGroups;

        if ($userLevel > 0) {
            $tempGroups = $this->getUserGroupsForAccessLevel($userLevel, $table);
            if (!empty($tempGroups)) {
                $userGroups = $tempGroups;
            }
        }

        return implode(',', $userGroups);
    }

    private function getUserGroupsForAccessLevel(int $level, string $table): array
    {
        // Try to find the user first by its OAuth Identifier
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        $record = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->inSet(
                    'gitlabGroup',
                    $queryBuilder->createNamedParameter($level)
                )
            )
            ->executeQuery()
            ->fetchFirstColumn();

        return empty($record) ? [] : array_values($record);
    }
}
