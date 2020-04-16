<?php
declare(strict_types = 1);

namespace Mfc\OAuth2\ResourceServer;

use Gitlab\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;

/**
 * Class GitLab
 * @package Mfc\OAuth2\ResourceServer
 * @author Christian Spoo <cs@marketing-factory.de>
 */
class GitLab extends AbstractResourceServer
{
    public const USER_LEVEL_GUEST      = 10;
    public const USER_LEVEL_REPORTER   = 20;
    public const USER_LEVEL_DEVELOPER  = 30;
    public const USER_LEVEL_MAINTAINER = 40;

    /**
     * @var int
     */
    private $adminUserLevel;
    /**
     * @var array
     */
    private $gitlabDefaultGroups;
    /**
     * @var int
     */
    private $userOption;
    /**
     * @var GitLabOAuthProvider
     */
    private $oauthProvider;
    /**
     * @var string
     */
    private $providerName;
    /**
     * @var string|null
     */
    private $projectName;
    /**
     * @var bool
     */
    private $userDetailsLoaded = false;
    /**
     * @var bool
     */
    private $blockExternalUser = false;
    /**
     * @var array
     */
    private $gitlabProjectPermissions;

    /**
     * GitLab constructor.
     *
     * @param array $arguments
     */
    public function __construct(array $arguments) {
        $this->providerName        = $arguments['providerName'];
        $this->projectName         = $arguments['projectName'];
        $this->adminUserLevel      = (int)$arguments['gitlabAdminUserLevel'];
        $this->gitlabDefaultGroups = GeneralUtility::trimExplode(',', $arguments['gitlabDefaultGroups'], true);
        $this->userOption          = (int)$arguments['gitlabUserOption'];
        $this->blockExternalUser   = (bool)$arguments['blockExternalUser'];

        $this->oauthProvider = new GitLabOAuthProvider([
            'clientId'     => $arguments['appId'],
            'clientSecret' => $arguments['appSecret'],
            'redirectUri'  => $this->getRedirectUri($arguments['providerName']),
            'domain'       => $arguments['gitlabServer'],
        ]);
    }

    /**
     * @return AbstractProvider
     */
    public function getOAuthProvider(): AbstractProvider
    {
        return $this->oauthProvider;
    }

    /**
     * @return string
     */
    public function getAuthorizationUrl(): string
    {
        return $this->oauthProvider->getAuthorizationUrl([
            'scope' => ['api', 'read_user', 'openid']
        ]);
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return bool
     */
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

    /**
     * @param ResourceOwnerInterface $user
     */
    public function loadUserDetails(ResourceOwnerInterface $user): void
    {
        if (!$user instanceof GitlabResourceOwner) {
            throw new \InvalidArgumentException(
                'Resource owner "' . (string)$user . '" is no suitable GitLab resource owner'
            );
        }

        if ($this->userDetailsLoaded) {
            return;
        }

        if (empty($this->projectName)) {
            throw new \InvalidArgumentException(
                'A "projectName" must be set in order for the GitLab Provider to function',
                1558972080
            );
        }

        /** @var Client $gitlabClient */
        $gitlabClient = $user->getApiClient();

        try {
            $project = $gitlabClient->projects->show($this->projectName);

            $accessLevel = 0;
            if (isset($project['permissions']['project_access'])) {
                $accessLevel = max($accessLevel, $project['permissions']['project_access']['access_level']);
            }
            if (isset($project['permissions']['group_access'])) {
                $accessLevel = max($accessLevel, $project['permissions']['group_access']['access_level']);
            }
            if (isset($project['shared_with_groups']) && is_array($sharedGroups = $project['shared_with_groups'])) {
                foreach ($sharedGroups as $sharedGroup) {
                    try {
                        $response = $gitlabClient
                            ->getHttpClient()
                            ->get('groups/' . $sharedGroup['group_id'] . '/members/' . $user->getId());
                        // only assign access level is current user is member of group
                        if ($response->getStatusCode() == 200) {
                            $accessLevel = max($accessLevel, $sharedGroup['group_access_level']);
                        }
                    } catch (\Exception $ex) {
                        // user has no access to see details
                    }
                }
            }
            if ($this->blockExternalUser && $user->isExternal()) {
                $accessLevel = 0;
            }

            $this->gitlabProjectPermissions = [
                'access_level' => $accessLevel
            ];

            $this->userDetailsLoaded = true;
        } catch (\Exception $ex) {
            // User not authorized to access this project
        }
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return \DateTime|null
     */
    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime
    {
        $this->loadUserDetails($user);
        if (!is_array($this->gitlabProjectPermissions)) {
            return null;
        }

        return null;
        /*        if (empty($this->gitlabProjectPermissions['expires_at'])) {
                    return null;
                }

                $expirationDate = new \DateTime($this->gitlabProjectPermissions['expires_at']);
                return $expirationDate;*/
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getOAuthIdentifier(ResourceOwnerInterface $user): string
    {
        $userData = $user->toArray();

        return $this->providerName . '|' . $userData['id'];
    }

    /**
     * @param ResourceOwnerInterface $user
     * @param array|null $currentRecord
     * @param array $authentificationInformation
     * @return array
     */
    public function updateUserRecord(
        ResourceOwnerInterface $user,
        array $currentRecord = null,
        array $authentificationInformation
    ): array {
        $userData = $user->toArray();

        if (!is_array($currentRecord)) {
            $saltingInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)->getDefaultHashInstance('FE');

            $currentRecord = [
                'pid' => 0,
                'password' => $saltingInstance->getHashedPassword(md5(uniqid()))
            ];
        }

        $currentRecord = array_merge(
            $currentRecord,
            [
                'email' => $userData['email'],
                'realname' => $userData['name'],
                'username' => $this->getUsernameFromUser($user),
                'usergroup' => $this->getUserGroupsForUser(
                    $this->gitlabDefaultGroups,
                    $this->gitlabProjectPermissions['access_level'],
                    $authentificationInformation['db_groups']['table']
                ),
                'options' => $this->userOption
            ]
        );

        return $currentRecord;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getUsernameFromUser(ResourceOwnerInterface $user): string
    {
        $userData = $user->toArray();
        return substr($userData['username'], 0, 50);
    }

    /**
     * @param array $defaultUserGroups
     * @param int $userLevel
     * @param string $table
     * @return string
     */
    protected function getUserGroupsForUser(
        array $defaultUserGroups,
        int $userLevel = 0,
        string $table = 'be_groups'
    ) {
        $userGroups = $defaultUserGroups;

        if ($userLevel > 0) {
            $tempGroups = $this->getUserGroupsForAccessLevel($userLevel, $table);
            if (!empty($tempGroups)) {
                $userGroups = $tempGroups;
            }
        }

        return implode(',', $userGroups);
    }

    /**
     * @param integer $level
     * @param string $table
     * @return array
     */
    protected function getUserGroupsForAccessLevel($level, $table): array
    {
        // Try to find the user first by its OAuth Identifier
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

        $record = $queryBuilder
            ->select('uid')
            ->from($table)
            ->where(
                $queryBuilder->expr()->inSet(
                    'gitlabGroup',
                    $queryBuilder->createNamedParameter(
                        $level,
                        Connection::PARAM_STR
                    )
                )
            )
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);

        return empty($record) ? [] : array_values($record);
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getEmailFromUser(ResourceOwnerInterface $user): string
    {
        $userData = $user->toArray();
        return $userData['email'];
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return bool
     */
    public function userIsActive(ResourceOwnerInterface $user): bool
    {
        $this->loadUserDetails($user);

        return !is_null($this->gitlabProjectPermissions) && is_array($this->gitlabProjectPermissions)
            && ($this->gitlabProjectPermissions['access_level'] > 0);
    }
}
