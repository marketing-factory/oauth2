<?php
declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use Gitlab\Client;
use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;

/**
 * Class GitLab
 * @package Mfc\OAuth2\ResourceServer
 * @author Christian Spoo <cs@marketing-factory.de>
 */
class GitLab extends AbstractResourceServer
{
    /** @var int  */
    protected $adminUserLevel;

    /**
     * @var GitLabOAuthProvider
     */
    private $oauthProvider;
    /**
     * @var string
     */
    private $providerName;
    /**
     * @var string
     */
    private $projectName;
    private $userDetailsLoaded = false;
    /**
     * @var array
     */
    private $gitlabProjectMember;

    /**
     * GitLab constructor.
     * @param string $appId
     * @param string $appSecret
     * @param string $providerName
     * @param string $gitlabServer
     * @param string $gitlabAdminUserLevel
     * @param string $projectName
     */
    public function __construct(
        string $appId,
        string $appSecret,
        string $providerName,
        string $gitlabServer,
        string $gitlabAdminUserLevel,
        string $projectName
    ) {
        $this->providerName = $providerName;
        $this->projectName = $projectName;
        $this->adminUserLevel = (int) $gitlabAdminUserLevel;

        $this->oauthProvider = new GitLabOAuthProvider([
            'clientId' => $appId,
            'clientSecret' => $appSecret,
            'redirectUri' => $this->getRedirectUri($providerName),
            'domain' => $gitlabServer,
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
        if (!is_array($this->gitlabProjectMember)) {
            return false;
        }

        $accessLevel = $this->gitlabProjectMember['access_level'];

        // Grant admin access from Developer level onwards
        return $accessLevel >= $this->adminUserLevel;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return \DateTime|null
     */
    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime
    {
        $this->loadUserDetails($user);
        if (!is_array($this->gitlabProjectMember)) {
            return null;
        }

        if (empty($this->gitlabProjectMember['expires_at'])) {
            return null;
        }

        $expirationDate = new \DateTime($this->gitlabProjectMember['expires_at']);
        return $expirationDate;
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
     * @return array
     */
    public function updateUserRecord(ResourceOwnerInterface $user, array $currentRecord): array
    {
        $userData = $user->toArray();

        if (!is_array($currentRecord)) {
            $currentRecord = [
                'pid' => 0,
                'password' => 'invalid'
            ];
        }

        $currentRecord = array_merge(
            $currentRecord,
            [
                'email' => $userData['email'],
                'realname' => $userData['name'],
                'username' => $this->getUsernameFromUser($user)
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
        return substr($this->providerName . '_' . $userData['username'], 0, 50);
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

        /** @var Client $gitlabClient */
        $gitlabClient = $user->getApiClient();

        try {
            $this->gitlabProjectMember = $gitlabClient->projects->member($this->projectName, $user->getId());
        } catch (\Exception $ex) {
            // User not authorized to access this project
        }

        $this->userDetailsLoaded = true;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return bool
     */
    public function userIsActive(ResourceOwnerInterface $user): bool
    {
        $this->loadUserDetails($user);

        return (is_array($this->gitlabProjectMember) && $this->gitlabProjectMember['state'] === 'active');
    }
}
