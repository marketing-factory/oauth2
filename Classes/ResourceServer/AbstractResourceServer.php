<?php

declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\SecurityAspect;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\Http\CookieHeaderTrait;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Security\Nonce;
use TYPO3\CMS\Core\Security\RequestToken;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractResourceServer implements ResourceServerInterface
{
    use CookieHeaderTrait;

    protected const COOKIE_PREFIX = 'typo3nonce_';
    protected const SECURE_PREFIX = '__Secure-';

    protected function getRedirectUri(
        string $resourceServerIdentifier,
        bool $withRequestToken = false,
        $requestToken = ''
    ): array {
        $cookie = null;
        $requestTokenParameter = '';

        if ($withRequestToken) {
            [$requestTokenParameter, $cookie] = $this->getRequestTokenParameter();
        } elseif ($requestToken !== '') {
            $requestTokenParameter = '&' . RequestToken::PARAM_NAME . '=' . $requestToken;
        }

        $redirectUri = GeneralUtility::locationHeaderUrl(
            '/typo3/index.php?loginProvider=1529672977'
            . '&login_status=login'
            . '&resource-server-identifier=' . $resourceServerIdentifier
            . $requestTokenParameter
        );

        return [$redirectUri, $cookie];
    }

    protected function getRequestTokenParameter(): array
    {
        $request = $this->getRequest();
        $loginType = ApplicationType::fromRequest($request)->isBackend() ? 'BE' : 'FE';
        $requestToken = RequestToken::create('core/user-auth/' . strtolower($loginType));

        $context = GeneralUtility::makeInstance(Context::class);
        $securityAspect = SecurityAspect::provideIn($context);
        $signingType = 'nonce';
        $signingProvider = $securityAspect->getSigningSecretResolver()->findByType($signingType);

        $nonce = $signingProvider->provideSigningSecret();
        $jwt = $requestToken->toHashSignedJwt($nonce);

        // needed to get a cookie stored as lax
        $cookie = $this->getNonceCookie($nonce, $request, $loginType);
        $requestTokenParameter = '&' . RequestToken::PARAM_NAME . '=' . $jwt;

        return [$requestTokenParameter, $cookie];
    }

    protected function getNonceCookie(
        Nonce $nonce,
        ServerRequestInterface $request,
        string $loginType
    ): Cookie {
        $cookieSameSite = $this->sanitizeSameSiteCookieValue(
            strtolower($GLOBALS['TYPO3_CONF_VARS'][$loginType]['cookieSameSite'] ?? Cookie::SAMESITE_STRICT)
        );

        $secure = $this->isHttps($request);
        $normalizedParams = $request->getAttribute('normalizedParams');
        $path = $normalizedParams->getSitePath();
        $securePrefix = $secure ? self::SECURE_PREFIX : '';
        $cookiePrefix = $securePrefix . self::COOKIE_PREFIX;

        $createCookie = static fn (string $name, string $value, int $expire): Cookie => new Cookie(
            $name,
            $value,
            $expire,
            $path,
            null,
            $secure,
            true,
            false,
            $cookieSameSite
        );
        return $createCookie($cookiePrefix . $nonce->getSigningIdentifier()->name, $nonce->toHashSignedJwt(), 0);
    }

    protected function isHttps(ServerRequestInterface $request): bool
    {
        $normalizedParams = $request->getAttribute('normalizedParams');
        return $normalizedParams instanceof NormalizedParams && $normalizedParams->isHttps();
    }

    protected function getRequest(): ServerRequestInterface
    {
        return $GLOBALS['TYPO3_REQUEST'];
    }
}
