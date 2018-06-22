<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Services;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Omines\OAuth2\Client\Provider\Gitlab;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Service\AbstractService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;

/**
 * Class OAuth2LoginService
 * @package Mfc\OAuth2\Services
 * @author Christian Spoo <cs@marketing-factory.de>
 */
class OAuth2LoginService extends AbstractService
{
    /**
     * @var array
     */
    private $loginData;
    /**
     * @var array
     */
    private $authenticationInformation;
    /**
     * @var AbstractUserAuthentication
     */
    private $parentObject;

    /**
     * @var AbstractProvider
     */
    private $oauthProvider;
    /**
     * @var ?AccessToken
     */
    private $currentAccessToken;
    /**
     * @var array
     */
    private $extensionConfig;

    /**
     * @param $subType
     * @param array $loginData
     * @param array $authenticationInformation
     * @param AbstractUserAuthentication $parentObject
     */
    public function initAuth(
        $subType,
        array $loginData,
        array $authenticationInformation,
        AbstractUserAuthentication &$parentObject
    ) {
        $this->extensionConfig = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oauth2']);

        $this->loginData = $loginData;
        $this->authenticationInformation = $authenticationInformation;
        $this->parentObject = $parentObject;

        if (!is_array($_SESSION)) {
            @session_start();
        }
    }

    public function getUser()
    {
        if ($this->loginData['status'] !== 'login') {
            return null;
        }

        $oauthProvider = GeneralUtility::_GP('oauth-provider');
        if (empty($oauthProvider)) {
            return null;
        }
        $this->initializeOAuthProvider($oauthProvider);

        if (empty($_GET['state'])) {
            $this->sendOAuthRedirect($oauthProvider);
            exit;
        } elseif ($this->isOAuthRedirectRequest()) {
            $this->currentAccessToken = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => GeneralUtility::_GET('code')
            ]);

            if ($this->currentAccessToken instanceof AccessToken) {
                try {
                    $user = $this->oauthProvider->getResourceOwner($this->currentAccessToken);
                    $userData = $user->toArray();

                    $user = $this->findOrCreateUserByResourceOwner($user, $oauthProvider);
                    return $user;
                } catch (\Exception $ex) {
                    return false;
                }
            }

        } else {
            unset($_SESSION['oauth2state']);
        }

        return null;
    }

    public function authUser(array $userRecord)
    {
        $result = 100;

        if ($userRecord['oauth_identifier'] !== '') {
            if ($this->currentAccessToken instanceof AccessToken) {
                $result = 200;
            }
        }

        return $result;
    }

    private function isOAuthRedirectRequest()
    {
        $state = GeneralUtility::_GET('state');
        return (!empty($state) && ($state === $_SESSION['oauth2state']));
    }

    /**
     * @param string $providerName
     */
    private function initializeOAuthProvider(string $providerName)
    {
        if ($this->oauthProvider instanceof AbstractProvider) {
            return;
        }

        $this->oauthProvider = new Gitlab([
            'clientId' => $this->extensionConfig['gitlabAppId'],
            'clientSecret' => $this->extensionConfig['gitlabAppSecret'],
            'redirectUri' => GeneralUtility::locationHeaderUrl('/typo3/index.php?loginProvider=1529672977&login_status=login&oauth-provider=' . $providerName),
            'domain' => $this->extensionConfig['gitlabServer'],
        ]);
    }

    /**
     * @param string $providerName
     * @return string
     */
    private function sendOAuthRedirect(string $providerName)
    {
        $authorizationUrl = $this->oauthProvider->getAuthorizationUrl([
            'scope' => ['read_user','openid']
        ]);

        $_SESSION['oauth2state'] = $this->oauthProvider->getState();
        HttpUtility::redirect($authorizationUrl, HttpUtility::HTTP_STATUS_303);
    }

    /**
     * @param ResourceOwnerInterface $user
     * @param string $providerName
     * @return array
     */
    private function findOrCreateUserByResourceOwner(ResourceOwnerInterface $user, string $providerName): array
    {
        $userData = $user->toArray();
        $oauthIdentifier = $providerName . '|' . $userData['id'];

        // Try to find the user first by its OAuth Identifier
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->authenticationInformation['db_user']['table']);
        $queryBuilder->getRestrictions()
            ->removeAll();

        $record = $queryBuilder
            ->select('*')
            ->from($this->authenticationInformation['db_user']['table'])
            ->where(
                $queryBuilder->expr()->eq(
                    'oauth_identifier',
                    $queryBuilder->createNamedParameter(
                        $oauthIdentifier,
                        Connection::PARAM_STR
                    )
                ),
                $this->authenticationInformation['db_user']['check_pid_clause'],
                $this->authenticationInformation['db_user']['enable_clause']
            )
            ->execute()
            ->fetch();

        if (!$record) {
            $record = $queryBuilder
                ->select('*')
                ->from($this->authenticationInformation['db_user']['table'])
                ->where(
                    $queryBuilder->expr()->orX(
                        $queryBuilder->expr()->eq(
                            'username',
                            $queryBuilder->createNamedParameter(
                                $userData['username'],
                                Connection::PARAM_STR
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            'email',
                            $queryBuilder->createNamedParameter(
                                $userData['email'],
                                Connection::PARAM_STR
                            )
                        )
                    ),
                    $this->authenticationInformation['db_user']['check_pid_clause'],
                    $this->authenticationInformation['db_user']['enable_clause']
                )
                ->execute()
                ->fetch();
        }

        if (!is_array($record)) {
            // User still not found. Create it.
            $user = [
                'crdate' => time(),
                'tstamp' => time(),
                'pid' => 0,
                'username' => $providerName . '_' . $userData['username'],
                'password' => 'invalid',
                'admin' => 1,
                'oauth_identifier' => $oauthIdentifier
            ];

            $queryBuilder->insert(
                $this->authenticationInformation['db_user']['table']
            )
                ->values($user)
                ->execute();

            $record = $this->parentObject->fetchUserRecord(
                $this->authenticationInformation['db_user'],
                $user['username']
            );
        }

        return $record;
    }
}
