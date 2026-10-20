<?php

declare(strict_types=1);

/*
 * This file is part of the package mfc/oauth2.
 *
 * For the full copyright and license information, please read the
 * LICENSE file that was distributed with this source code.
 */

namespace Mfc\OAuth2\Tests\Unit\ResourceServer;

use Mfc\OAuth2\ResourceServer\AbstractResourceServer;
use Mfc\OAuth2\ResourceServer\Keycloak;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AbstractResourceServerTest extends UnitTestCase
{
    public static function backendEntryPoints(): \Generator
    {
        yield 'default entry point' => [null, '', 'https://example.com/typo3/index.php'];
        yield 'custom entry point' => ['/admin', '', 'https://example.com/admin/index.php'];
        yield 'installation in a subdirectory' => [null, '/sub', 'https://example.com/sub/typo3/index.php'];
        yield 'backend on its own domain' => ['https://admin.example.org/cms', '', 'https://admin.example.org/cms/index.php'];
    }

    #[DataProvider('backendEntryPoints')]
    #[Test]
    public function redirectUriPointsToTheBackendLogin(?string $entryPoint, string $sitePath, string $expected): void
    {
        if ($entryPoint !== null) {
            $GLOBALS['TYPO3_CONF_VARS']['BE']['entryPoint'] = $entryPoint;
        }
        $serverParams = ['HTTP_HOST' => 'example.com', 'HTTPS' => 'on', 'SCRIPT_NAME' => $sitePath . '/index.php'];
        $normalizedParams = new NormalizedParams($serverParams, [], '/var/www/html/public/index.php', '/var/www/html/public/');
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('https://example.com' . $sitePath . '/typo3/', 'GET', 'php://temp', [], $serverParams))
            ->withAttribute('normalizedParams', $normalizedParams);

        [$redirectUri, $cookie] = (new \ReflectionMethod(AbstractResourceServer::class, 'getRedirectUri'))
            ->invoke((new \ReflectionClass(Keycloak::class))->newInstanceWithoutConstructor(), 'keycloak', false, 'token123');

        self::assertSame(
            $expected . '?loginProvider=1529672977&login_status=login'
            . '&resource-server-identifier=keycloak&__RequestToken=token123',
            $redirectUri
        );
        self::assertNull($cookie);
    }
}
