<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Services;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;
use Omines\OAuth2\Client\Provider\Gitlab;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
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
            $token = $this->oauthProvider->getAccessToken('authorization_code', [
                'code' => GeneralUtility::_GET('code')
            ]);

            try {
                $user = $this->oauthProvider->getResourceOwner($token);
                var_dump($user);
                die();
                return [];
            } catch (\Exception $ex) {
                throw $ex;
            }
        } else {
            unset($_SESSION['oauth2state']);
        }

        return null;
    }

    public function authUser()
    {
        $result = 100;


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
            'clientId' => 'ae8dd2d3b2a031c460789ceae92392b5085e7837f8297697db3ffdee69a360f0',
            'clientSecret' => '0fdf0fa53b35c0e247a5522aa541e6c34125149749a9ae39f3db42a9be102d54',
            'redirectUri' => 'http://127.0.0.1:8080/typo3/index.php?loginProvider=1529672977&login_status=login&oauth-provider=gitlab',
            'domain' => 'https://gitlab.hellmund.eu:2443',
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
}
