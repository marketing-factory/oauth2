<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Tests\Functional\ResourceServer;

use Mfc\OAuth2\Services\OAuth2LoginService;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;

/**
 * Test class to test \Mfc\OAuth2\Services\OAuth2LoginService
 * @package Mfc\OAuth2\Tests\Functional\Services
 * @author Tim Schreiner <schreiner.tim@gmail.com>
 */
class OAuth2LoginServiceTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/oauth2',
    ];

    public function setUp()
    {
        parent::setUp();

        $GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['oauth2'] = serialize([]);
    }

    /**
     * @test
     */
    public function getUserWithInvalidLoginStatus()
    {
        $subject = new OAuth2LoginService();
        $subject->initAuth(
            '',
            ['status' => 'invalid'],
            [],
            $this->getParentObject()
        );

        $this->assertNull($subject->getUser());
    }

    /**
     * @test
     */
    public function getUserWithMissingOauthProvider()
    {
        $subject = new OAuth2LoginService();
        $subject->initAuth('', ['status' => 'login'], [], $this->getParentObject());

        $this->assertNull($subject->getUser());
    }

    /**
     * @test
     */
    public function getUserWithEmptyStateDoRedirect()
    {
        $_GET['oauth-provider'] = 'gitlab';

        $subject = $this
            ->getMockBuilder(OAuth2LoginService::class)
            ->setMethods(['initializeOAuthProvider', 'sendOAuthRedirect'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('sendOAuthRedirect');

        $subject->initAuth('', ['status' => 'login'], [], $this->getParentObject());
        $subject->getUser();
    }

    protected function getParentObject()
    {
        $backendUserAuthentication = $this
            ->getMockBuilder(BackendUserAuthentication::class)
            ->getMock();

        return $backendUserAuthentication;
    }
}
