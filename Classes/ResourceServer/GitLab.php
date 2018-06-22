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

    /**
     * GitLab constructor.
     * @param string $appId
     * @param string $appSecret
     * @param string $providerName
     * @param string $gitlabServer
     * @param string $projectName
     */
    public function __construct(
        string $appId,
        string $appSecret,
        string $providerName,
        string $gitlabServer,
        string $projectName
    ) {
        $this->providerName = $providerName;
        $this->projectName = $projectName;

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
        if (!$user instanceof GitlabResourceOwner) {
            throw new \InvalidArgumentException(
                'Resource owner "' . (string)$user . '" is no suitable GitLab resource owner'
            );
        }

        /** @var Client $gitlabClient */
        $gitlabClient = $user->getApiClient();

        $member = $gitlabClient->projects->member($this->projectName, $user->getId());
        $accessLevel = $member['access_level'];

        // Grant admin access from Developer level onwards
        return $accessLevel >= 30;
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
    public function updateUserRecord(ResourceOwnerInterface $user, array $currentRecord = null): array
    {
        $userData = $user->toArray();

        if (!is_array($currentRecord)) {
            $currentRecord = [
                'crdate' => time(),
                'tstamp' => time(),
                'pid' => 0,
                'username' => substr($this->providerName . '_' . $userData['username'], 0, 50),
            ];
        }

        return $currentRecord;
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getUsernameFromUser(ResourceOwnerInterface $user): string
    {
        $userData = $user->toArray();
        return $userData['username'];
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
}
