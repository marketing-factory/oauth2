<?php
declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use Gitlab\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Mfc\OAuth2\Domain\Model\Dto\OauthUser;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Class GitLab
 * @package Mfc\OAuth2\ResourceServer
 * @author Christian Spoo <cs@marketing-factory.de>
 */
class GitLab implements ResourceServerInterface
{
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
     * @var OauthUser
     */
    private $gitLabUser;

    /**
     * GitLab constructor.
     * @param AbstractProvider $provider
     * @param string $providerName
     * @param string $gitlabAdminUserLevel
     * @param string $gitlabDefaultGroups
     * @param string $gitlabUserOption
     * @param string|null $projectName
     */
    public function __construct(
        AbstractProvider $provider,
        string $providerName,
        string $gitlabAdminUserLevel,
        string $gitlabDefaultGroups,
        string $gitlabUserOption,
        ?string $projectName
    ) {
        $this->oauthProvider = $provider;
        $this->providerName = $providerName;
        $this->projectName = $projectName;
        $this->adminUserLevel = (int)$gitlabAdminUserLevel;
        $this->gitlabDefaultGroups = GeneralUtility::trimExplode(',', $gitlabDefaultGroups, true);
        $this->userOption = (int)$gitlabUserOption;
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
        $oauthUser = $this->loadUserDetails($user);
        if (!$user) {
            return false;
        }

        // Grant admin access from Developer level onwards
        return $oauthUser->getAccessLevel() >= $this->adminUserLevel;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return OauthUser|null
     */
    public function loadUserDetails(ResourceOwnerInterface $user): ?OauthUser
    {
        if (!$user instanceof GitlabResourceOwner) {
            throw new \InvalidArgumentException(
                'Resource owner "' . (string)$user . '" is no suitable GitLab resource owner'
            );
        }

        if ($this->gitLabUser instanceof OauthUser) {
            return $this->gitLabUser;
        }

        if (empty($this->projectName)) {
            return null;
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
                    $accessLevel = max($accessLevel, $sharedGroup['group_access_level']);
                }
            }

            $this->gitLabUser = GeneralUtility::makeInstance(OauthUser::class, (int)$accessLevel);

            return $this->gitLabUser;
        } catch (\Exception $ex) {
            // User not authorized to access this project
            return null;
        }
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return \DateTime|null
     * Todo Needs to be implemented correctly
     */
    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime
    {
        $user = $this->loadUserDetails($user);
        if (!$user) {
            return null;
        }

        return null;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getOAuthIdentifier(ResourceOwnerInterface $user): string
    {
        return $this->providerName . '|' . $user->getId();
    }

    /**
     * @param ResourceOwnerInterface $user
     * @param array|null $currentRecord
     * @param array $authentificationInformation
     * @return array
     */
    public function updateUserRecord(
        ResourceOwnerInterface $user,
        array $currentRecord,
        array $authentificationInformation
    ): array {
        $oauthUser = $this->loadUserDetails($user);
        $userData = $user->toArray();

        $currentRecord = array_merge(
            $currentRecord,
            [
                'email' => $userData['email'],
                'realname' => $userData['name'],
                'username' => $this->getUsernameFromUser($user),
                'usergroup' => $this->getUserGroupsForUser(
                    $this->gitlabDefaultGroups,
                    $oauthUser->getAccessLevel(),
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
        $user = $this->loadUserDetails($user);

        return $user && ($user->getAccessLevel() > 0);
    }
}
