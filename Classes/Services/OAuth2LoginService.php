<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Services;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Mfc\OAuth2\ResourceServer\AbstractResourceServer;
use Mfc\OAuth2\ResourceServer\GitLab;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Service\AbstractService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Saltedpasswords\Salt\SaltFactory;

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
     * @var ?AccessToken
     */
    private $currentAccessToken;
    /**
     * @var array
     */
    private $extensionConfig;
    /**
     * @var AbstractResourceServer
     */
    private $resourceServer;

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
            $this->sendOAuthRedirect();
            exit;
        } elseif ($this->isOAuthRedirectRequest()) {
            try {
                $this->currentAccessToken = $this->resourceServer->getOAuthProvider()->getAccessToken(
                    'authorization_code',
                    [
                        'code' => GeneralUtility::_GET('code')
                    ]
                );
            } catch (\Exception $ex) {
                return false;
            }

            if ($this->currentAccessToken instanceof AccessToken) {
                try {
                    $user = $this->resourceServer->getOAuthProvider()->getResourceOwner($this->currentAccessToken);
                    $record = $this->findOrCreateUserByResourceOwner($user);

                    if (!$record) {
                        return false;
                    }

                    return $record;
                } catch (\Exception $ex) {
                    return false;
                }
            }
        } else {
            unset($_SESSION['oauth2state']);
        }

        return null;
    }

    private function initializeOAuthProvider($oauthProvider)
    {
        switch ($oauthProvider) {
            case 'gitlab':
                $provider = new GitLabOAuthProvider([
                    'clientId' => $this->extensionConfig['gitlabAppId'],
                    'clientSecret' => $this->extensionConfig['gitlabAppSecret'],
                    'redirectUri' => $this->getRedirectUri('gitlab'),
                    'domain' => $this->extensionConfig['gitlabServer'],
                ]);
                $this->resourceServer = new GitLab(
                    $provider,
                    'gitlab',
                    $this->extensionConfig['gitlabAdminUserLevel'],
                    $this->extensionConfig['gitlabDefaultGroups'] ?? '',
                    $this->extensionConfig['gitlabUserOption'] ?? '0',
                    $this->extensionConfig['gitlabRepositoryName']
                );
                break;
        }
    }

    /**
     * @return string
     */
    private function sendOAuthRedirect()
    {
        $authorizationUrl = $this->resourceServer->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $this->resourceServer->getOAuthProvider()->getState();
        HttpUtility::redirect($authorizationUrl, HttpUtility::HTTP_STATUS_303);
    }

    private function isOAuthRedirectRequest()
    {
        $state = GeneralUtility::_GET('state');
        return (!empty($state) && ($state === $_SESSION['oauth2state']));
    }

    /**
     * @param ResourceOwnerInterface $user
     * @return array|null
     */
    private function findOrCreateUserByResourceOwner(ResourceOwnerInterface $user): ?array
    {
        $oauthIdentifier = $this->resourceServer->getOAuthIdentifier($user);

        // Try to find the user first by its OAuth Identifier
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->authenticationInformation['db_user']['table']);
        $queryBuilder->getRestrictions()
            ->removeAll()
            ->add(new DeletedRestriction());

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
                                $this->resourceServer->getUsernameFromUser($user),
                                Connection::PARAM_STR
                            )
                        ),
                        $queryBuilder->expr()->eq(
                            'email',
                            $queryBuilder->createNamedParameter(
                                $this->resourceServer->getEmailFromUser($user),
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
            $saltingInstance = SaltFactory::getSaltingInstance(null);

            $record = [
                'crdate' => time(),
                'tstamp' => time(),
                'admin' => (int)$this->resourceServer->userShouldBeAdmin($user),
                'disable' => 0,
                'starttime' => 0,
                'endtime' => 0,
                'oauth_identifier' => $this->resourceServer->getOAuthIdentifier($user),
                'password' => $saltingInstance->getHashedPassword(md5(uniqid()))
            ];

            $expirationDate = $this->resourceServer->userExpiresAt($user);
            if ($expirationDate instanceof \DateTime) {
                $record['endtime'] = $expirationDate->format('U');
            }

            $record = $this->resourceServer->updateUserRecord($user, $record, $this->authenticationInformation);

            $queryBuilder->insert(
                $this->authenticationInformation['db_user']['table']
            )
                ->values($record)
                ->execute();

            $record = $this->parentObject->fetchUserRecord(
                $this->authenticationInformation['db_user'],
                $this->resourceServer->getUsernameFromUser($user)
            );
        } else {
            if ($this->extensionConfig['gitlabOverrideUser'] && $this->extensionConfig['gitlabRepositoryName']) {
                $this->resourceServer->loadUserDetails($user);

                $record = array_merge(
                    $record,
                    [
                        'admin' => (int)$this->resourceServer->userShouldBeAdmin($user),
                        'disable' => 0,
                        'starttime' => 0,
                        'endtime' => 0,
                        'oauth_identifier' => $this->resourceServer->getOAuthIdentifier($user)
                    ]
                );

                if (ExtensionManagementUtility::isLoaded('be_secure_pw')) {
                    $record['tx_besecurepw_lastpwchange'] = time();
                }

                $expirationDate = $this->resourceServer->userExpiresAt($user);
                if ($expirationDate instanceof \DateTime) {
                    $record['endtime'] = $expirationDate->format('U');
                }

                $record = $this->resourceServer->updateUserRecord($user, $record, $this->authenticationInformation);
            } else {
                $record = array_merge(
                    $record,
                    [
                        'oauth_identifier' => $this->resourceServer->getOAuthIdentifier($user)
                    ]
                );
            }

            $qb = $queryBuilder->update(
                $this->authenticationInformation['db_user']['table']
            )
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid',
                        $queryBuilder->createNamedParameter(
                            $record['uid'],
                            Connection::PARAM_STR
                        )
                    )
                );

            foreach ($record as $key => $value) {
                $qb->set($key, $value);
            }

            $qb->execute();
        }

        return is_array($record) ? $record : null;
    }

    public function authUser(array $userRecord)
    {
        $result = 100;

        // Check if $this->resourceServer is already instantiated (this indicates that we were previously in the
        // getUser() function)
        if ($userRecord['oauth_identifier'] !== '' && $this->resourceServer instanceof AbstractResourceServer) {
            $user = $this->resourceServer->getOAuthProvider()->getResourceOwner($this->currentAccessToken);

            if ($this->currentAccessToken instanceof AccessToken && $this->resourceServer->userIsActive($user)) {
                $result = 200;
            }
        }

        return $result;
    }

    /**
     * @param string $providerName
     * @return string
     */
    protected function getRedirectUri(string $providerName): string
    {
        return GeneralUtility::locationHeaderUrl('/typo3/index.php?loginProvider=1529672977&login_status=login&oauth-provider=' . $providerName);
    }
}
