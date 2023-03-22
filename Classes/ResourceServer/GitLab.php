<?php

declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use Gitlab\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class GitLab extends AbstractResourceServer
{
    public const USER_LEVEL_GUEST = 10;
    public const USER_LEVEL_REPORTER = 20;
    public const USER_LEVEL_DEVELOPER = 30;
    public const USER_LEVEL_MAINTAINER = 40;

    private int $adminUserLevel;

    private array $gitlabDefaultGroups;

    private int $userOption;

    private string $providerName;

    private string $projectName;

    private bool $blockExternalUser;

    private ?array $gitlabProjectPermissions;

    private array $oauthProviderConfiguration;

    private AbstractProvider $oauthProvider;

    private bool $userDetailsLoaded = false;

    public function __construct(array $arguments)
    {
        $this->providerName = $arguments['providerName'];
        $this->projectName = (string)$arguments['projectName'] ?? '';
        $this->adminUserLevel = (int)$arguments['gitlabAdminUserLevel'];
        $this->gitlabDefaultGroups = GeneralUtility::trimExplode(',', $arguments['gitlabDefaultGroups'], true);
        $this->userOption = (int)$arguments['gitlabUserOption'];
        $this->blockExternalUser = (bool)$arguments['blockExternalUser'];

        [$redirectUri] = $this->getRedirectUri($this->providerName);
        $this->oauthProviderConfiguration = [
            'clientId' => $arguments['appId'],
            'clientSecret' => $arguments['appSecret'],
            'redirectUri' => $redirectUri,
            'domain' => $arguments['gitlabServer'],
        ];
        $this->oauthProvider = new GitLabOAuthProvider($this->oauthProviderConfiguration);
    }

    public function getOAuthProvider(string $requestToken = ''): AbstractProvider
    {
        if ($requestToken !== '') {
            [$redirectUri] = $this->getRedirectUri($this->providerName, false, $requestToken);

            $oauthProviderConfiguration = array_merge(
                $this->oauthProviderConfiguration,
                ['redirectUri' => $redirectUri]
            );
            $this->oauthProvider = new GitLabOAuthProvider($oauthProviderConfiguration);
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
        $this->oauthProvider = new GitLabOAuthProvider($oauthProviderConfiguration);

        return [
            $this->oauthProvider->getAuthorizationUrl([ 'scope' => ['api', 'read_user', 'openid'] ]),
            $this->oauthProvider->getState(),
            $nonceCookie,
        ];
    }

    public function userShouldBeAdmin(ResourceOwnerInterface $user): bool
    {
        $this->loadUserDetails($user);
        if (!is_array($this->gitlabProjectPermissions)) {
            return false;
        }

        $accessLevel = $this->gitlabProjectPermissions['access_level'];

        // Grant admin access from Developer level onwards
        return $accessLevel >= $this->adminUserLevel;
    }

    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime
    {
        $this->loadUserDetails($user);
        if (!is_array($this->gitlabProjectPermissions)) {
            return null;
        }

        if (empty($this->gitlabProjectPermissions['expires_at'])) {
            return null;
        }

        /*$expirationDate = new \DateTime($this->gitlabProjectPermissions['expires_at']);
        return $expirationDate;*/

        return null;
    }

    public function userIsActive(ResourceOwnerInterface $user): bool
    {
        $this->loadUserDetails($user);

        return is_array($this->gitlabProjectPermissions) && ($this->gitlabProjectPermissions['access_level'] > 0);
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

        if (empty($this->projectName)) {
            throw new \InvalidArgumentException(
                'A "projectName" must be set in order for the GitLab Provider to function',
                1558972080
            );
        }

        if (!$user instanceof GitlabResourceOwner) {
            throw new \InvalidArgumentException(
                'Resource owner "' . $user->getId() . '" is no suitable GitLab resource owner'
            );
        }

        if ($this->blockExternalUser && $user->isExternal()) {
            $this->gitlabProjectPermissions = [
                'access_level' => 0
            ];
            $this->userDetailsLoaded = true;
            return;
        }

        $gitlabClient = $user->getApiClient();

        try {
            $project = $gitlabClient->projects()->show($this->projectName);

            $accessLevel = 0;
            $accessLevel = max($accessLevel, $project['permissions']['project_access']['access_level'] ?? 0);
            $accessLevel = max($accessLevel, $project['permissions']['group_access']['access_level'] ?? 0);

            $sharedGroups = $this->sharedGroupsForProject($gitlabClient, $project);
            foreach ($sharedGroups as $sharedGroupId) {
                try {
                    $member = $gitlabClient->groups()->member($sharedGroupId, $user->getId());
                    if ($member) {
                        $accessLevel = max($accessLevel, $member['access_level'] ?? 0);
                    }
                } catch (\Exception $exception) {
                    // user has no access to see details
                }
            }

            $this->gitlabProjectPermissions = [
                'access_level' => $accessLevel
            ];
            $this->userDetailsLoaded = true;
        } catch (\Exception $exception) {
            // User not authorized to access this project
        }
    }

    public function getUsernameFromUser(ResourceOwnerInterface $user): string
    {
        return substr($user->toArray()['username'] ?? '', 0, 50);
    }

    public function getEmailFromUser(ResourceOwnerInterface $user): string
    {
        return $user->toArray()['email'] ?? '';
    }

    private function sharedGroupsForProject(Client $gitlabClient, array $project): array
    {
        $sharedGroups = [];

        // 1. Directly associated groups
        if (isset($project['shared_with_groups']) && is_array($project['shared_with_groups'])) {
            $sharedGroups += array_map(
                static function (array $groupData): int {
                    return (int)$groupData['group_id'];
                },
                $project['shared_with_groups']
            );
        }

        // 2. Workaround for a limitation of GitLab's API endpoint for retrieving inherited group memberships
        // @see https://gitlab.com/gitlab-org/gitlab/-/issues/369592
        if ($project['namespace']['kind'] === 'group') {
            $inheritedGroups = [];
            $currentGroupId = $project['namespace']['id'];

            // Determine all parent groups
            while (!is_null($currentGroupId)) {
                $inheritedGroups[] = (int)$currentGroupId;

                $group = $gitlabClient->groups()->show($currentGroupId);

                if (isset($group['shared_with_groups'])) {
                    $inheritedGroups += array_map(
                        static function (array $groupData): int {
                            return (int)$groupData['group_id'];
                        },
                        $group['shared_with_groups']
                    );
                }

                $currentGroupId = $group['parent_id'];
            }

            $sharedGroups += $inheritedGroups;
        }
        $sharedGroups = array_unique($sharedGroups);

        // 3. Determine child groups
        $subgroups = array_map(
            function (int $sharedGroup) use ($gitlabClient): array {
                return $this->subgroupsForGroup($sharedGroup, $gitlabClient);
            },
            $sharedGroups
        );

        return array_unique(array_merge($sharedGroups, ...$subgroups));
    }

    private function subgroupsForGroup(int $groupId, Client $gitlabClient): array
    {
        $group = $gitlabClient->groups()->show($groupId);

        if (!isset($group['shared_with_groups'])) {
            return [];
        }

        $subgroups = [];
        foreach ($group['shared_with_groups'] as $subgroup) {
            $subgroups[] = (int)$subgroup['group_id'];
        }

        $result = $subgroups;
        foreach ($subgroups as $subgroup) {
            $result += self::subgroupsForGroup($subgroup, $gitlabClient);
        }

        return array_unique($result);
    }

    public function updateUserRecord(
        ResourceOwnerInterface $user,
        ?array $currentRecord = null,
        array $authenticationInformation = [],
        PasswordHashInterface $saltingInstance = null
    ): array {
        $userData = $user->toArray();

        if (!is_array($currentRecord)) {
            $currentRecord = [
                'pid' => 0,
                'password' => $saltingInstance->getHashedPassword(md5(uniqid()))
            ];
        }

        return array_merge(
            $currentRecord,
            [
                'email' => $userData['email'],
                'realName' => $userData['name'],
                'username' => $this->getUsernameFromUser($user),
                'usergroup' => $this->getUserGroupsForUser(
                    $this->gitlabDefaultGroups,
                    $this->gitlabProjectPermissions['access_level'],
                    $authenticationInformation['db_groups']['table']
                ),
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
