<?php

declare(strict_types=1);

namespace Mfc\OAuth2\Services;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use League\OAuth2\Client\Token\AccessToken;
use Mfc\OAuth2\ResourceServer\AbstractResourceServer;
use Mfc\OAuth2\ResourceServer\Registry;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Authentication\LoginType;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashFactory;

class OAuth2LoginService extends AbstractAuthenticationService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    private ResponseFactoryInterface $responseFactory;

    private array $extensionConfig;

    private ?AccessToken $accessToken = null;

    private ?AbstractResourceServer $resourceServer = null;

    public function __construct(
        ResponseFactoryInterface $responseFactory,
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->responseFactory = $responseFactory;
        $this->extensionConfig = $extensionConfiguration->get('oauth2');
    }

    /**
     * @param string $mode
     * @param array $loginData
     * @param array $authInfo
     * @param AbstractUserAuthentication $pObj
     */
    public function initAuth($mode, $loginData, $authInfo, $pObj)
    {
        $this->pObj = $pObj;
        // subtype
        $this->mode = $mode;
        $this->login = $loginData;
        $this->authInfo = $authInfo;
        $this->authInfo['db_groups']['table'] = (($mode == 'getUserBE') ? 'be_groups' : 'fe_groups');
        $this->db_user = $this->authInfo['db_user'];

        $request = $this->getRequest();
        if (!isset($_SESSION) && ($request->getQueryParams()['loginProvider'] ?? '') === '1529672977') {
            @session_start();
        }
    }

    public function getUser(): ?array
    {
        if ($this->login['status'] !== LoginType::LOGIN) {
            return null;
        }

        $request = $this->getRequest();

        if (!$this->initializeResourceServer($request)) {
            return null;
        }

        $state = $request->getQueryParams()['state'] ?? '';
        if ($state === '') {
            $this->sendOAuthRedirect();
        } elseif ($this->isOAuthRedirectRequest($state)) {
            $this->accessToken = $this->getAccessToken($request);
            if ($resourceOwner = $this->getResourceOwner($this->accessToken)) {
                return $this->findOrCreateUserByResourceOwner($resourceOwner);
            }
        }
        unset($_SESSION['oauth2state']);

        return null;
    }

    public function authUser(array $userRecord): int
    {
        $result = 100;

        // Check if $this->resourceServer is already instantiated
        // (this indicates that we were previously in the getUser() function)
        if ($userRecord['oauth_identifier'] !== '' && $this->resourceServer !== null) {
            $resourceOwner = $this->getResourceOwner($this->accessToken);

            if ($this->accessToken && $this->resourceServer->userIsActive($resourceOwner)) {
                $result = 200;
            }
        }

        return $result;
    }

    protected function initializeResourceServer(ServerRequestInterface $request): ?AbstractResourceServer
    {
        try {
            $resourceServerIdentifier = $request->getQueryParams()['resource-server-identifier']
                ?? $request->getParsedBody()['resource-server-identifier']
                ?? '';
            $this->resourceServer = Registry::getResourceServerInstance($resourceServerIdentifier);
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return $this->resourceServer;
    }

    protected function sendOAuthRedirect(): void
    {
        [$authorizationUrl, $oauth2state, $nonceCookie] = $this->resourceServer->getAuthorizationUrl();
        $_SESSION['oauth2state'] = $oauth2state;

        $response = $this->responseFactory
            ->createResponse(303)
            ->withAddedHeader('location', $authorizationUrl)
            ->withAddedHeader('Set-Cookie', (string)$nonceCookie);

        throw new PropagateResponseException($response);
    }

    protected function isOAuthRedirectRequest(string $state): bool
    {
        return $state === $_SESSION['oauth2state'];
    }

    protected function getAccessToken(ServerRequestInterface $request): ?AccessToken
    {
        $accessToken = null;
        try {
            $requestToken = $request->getQueryParams()[RequestToken::PARAM_NAME];
            $accessToken = $this->resourceServer
                ->getOAuthProvider($requestToken)
                ->getAccessToken(
                    'authorization_code',
                    [
                        'code' => $request->getQueryParams()['code'] ?? ''
                    ]
                );
        } catch (\Exception $exception) {
            $this->logger->error($exception->getMessage());
        }
        return $accessToken;
    }

    protected function getResourceOwner(AccessToken $accessToken): ?ResourceOwnerInterface
    {
        $record = null;
        if ($this->accessToken) {
            try {
                $record = $this->resourceServer
                    ->getOAuthProvider()
                    ->getResourceOwner($accessToken);
            } catch (\Exception $exception) {
                $this->logger->error($exception->getMessage());
            }
        }
        return $record;
    }

    protected function findOrCreateUserByResourceOwner(ResourceOwnerInterface $user): ?array
    {
        // Try to find the user first by its OAuth Identifier
        $record = $this->findUserByOauthIdentifier($user);

        if (empty($record)) {
            // previous user record by gitlab id not found, find by username and email
            $record = $this->findUserByUsernameOrEmail($user);
        }

        if (!empty($record)) {
            // previous user record found
            $this->updateFoundUser($user, $record);
        } else {
            // previous user record not found, insert a new record
            $this->createUser($user);

            $record = $this->findUserByUsername($this->resourceServer->getUsernameFromUser($user));
        }

        return $record;
    }

    protected function findUserByUsername(string $username): ?array
    {
        $user = null;
        if ($username) {
            $queryBuilder = $this->getQueryBuilderForTable($this->db_user['table']);

            $constraints = array_filter([
                $this->db_user['enable_clause'] ?? '',
                $queryBuilder->expr()->eq(
                    $this->db_user['username_column'],
                    $queryBuilder->createNamedParameter($username)
                )
            ]);

            $user = $queryBuilder
                ->select('*')
                ->from($this->db_user['table'])
                ->where(...$constraints)
                ->executeQuery()
                ->fetchAssociative();
        }
        return $user;
    }

    protected function findUserByOauthIdentifier(ResourceOwnerInterface $user): ?array
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->authInfo['db_user']['table']);
        $result = $queryBuilder
            ->select('*')
            ->from($this->authInfo['db_user']['table'])
            ->where(
                $queryBuilder->expr()->eq(
                    'oauth_identifier',
                    $queryBuilder->createNamedParameter($this->resourceServer->getOAuthIdentifier($user))
                ),
                ...array_filter([
                    QueryHelper::stripLogicalOperatorPrefix($this->authInfo['db_user']['check_pid_clause'] ?? ''),
                    $this->authInfo['db_user']['enable_clause'] ?? '',
                ])
            )
            ->executeQuery()
            ->fetchAssociative();
        return $result ?: [];
    }

    protected function findUserByUsernameOrEmail(ResourceOwnerInterface $user): ?array
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->authInfo['db_user']['table']);
        $result = $queryBuilder
            ->select('*')
            ->from($this->authInfo['db_user']['table'])
            ->where(
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'username',
                        $queryBuilder->createNamedParameter($this->resourceServer->getUsernameFromUser($user))
                    ),
                    $queryBuilder->expr()->eq(
                        'email',
                        $queryBuilder->createNamedParameter($this->resourceServer->getEmailFromUser($user))
                    )
                ),
                ...array_filter([
                    QueryHelper::stripLogicalOperatorPrefix($this->authInfo['db_user']['check_pid_clause'] ?? ''),
                    $this->authInfo['db_user']['enable_clause'] ?? '',
                ])
            )
            ->executeQuery()
            ->fetchAssociative();
        return $result ?: [];
    }

    protected function createUser(ResourceOwnerInterface $user): void
    {
        $loginType = ApplicationType::fromRequest($this->getRequest())->isBackend() ? 'BE' : 'FE';
        $saltingInstance = GeneralUtility::makeInstance(PasswordHashFactory::class)
            ->getDefaultHashInstance($loginType);

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

        $expirationDate = null; //$this->resourceServer->userExpiresAt($user);
        if ($expirationDate instanceof \DateTime) {
            $record['endtime'] = $expirationDate->format('U');
        }

        $record = $this->resourceServer->updateUserRecord($user, $record, $this->authInfo, $saltingInstance);

        $queryBuilder = $this->getQueryBuilderForTable($this->authInfo['db_user']['table']);
        $queryBuilder
            ->insert($this->authInfo['db_user']['table'])
            ->values($record)
            ->executeStatement();
    }

    protected function updateFoundUser(ResourceOwnerInterface $user, array $record): void
    {
        if ($this->extensionConfig['overrideUser']) {
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

            $expirationDate = null; //$this->resourceServer->userExpiresAt($user);
            if ($expirationDate instanceof \DateTime) {
                $record['endtime'] = $expirationDate->format('U');
            }

            $record = $this->resourceServer->updateUserRecord($user, $record, $this->authInfo);
        } else {
            $record = array_merge($record, [ 'oauth_identifier' => $this->resourceServer->getOAuthIdentifier($user) ]);
        }

        // update user record
        $queryBuilder = $this->getQueryBuilderForTable($this->authInfo['db_user']['table']);
        $queryBuilder
            ->update($this->authInfo['db_user']['table'])
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($record['uid'])
                )
            );

        foreach ($record as $key => $value) {
            $type = Connection::PARAM_STR;
            if ($key === 'uc') {
                $type = Connection::PARAM_LOB;
            }
            $queryBuilder->set($key, $value, true, $type);
        }

        $queryBuilder->executeStatement();
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }

    protected function getQueryBuilderForTable(string $table): QueryBuilder
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }
}
