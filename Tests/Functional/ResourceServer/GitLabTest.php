<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Tests\Functional\ResourceServer;

use Mfc\OAuth2\Domain\Model\Dto\OauthUser;
use Mfc\OAuth2\ResourceServer\GitLab;
use Nimut\TestingFramework\TestCase\FunctionalTestCase;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;

/**
 * Test class to test \Mfc\OAuth2\ResourceServer\GitLab
 * @package Mfc\OAuth2\Tests\Functional\ResourceServer
 * @author Tim Schreiner <schreiner.tim@gmail.com>
 */
class GitLabTest extends FunctionalTestCase
{
    protected $testExtensionsToLoad = [
        'typo3conf/ext/oauth2',
    ];

    public function setUp()
    {
        parent::setUp();

        $this->importDataSet(__DIR__ . '/../Fixtures/be_groups.xml');
    }

    /**
     * @test
     */
    public function updateUserRecordWithGuestGroup()
    {
        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$this->getProviderMock(), '', '30', '', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn($this->getOauthUser(10));

        $user = $subject->updateUserRecord($this->getUserRecord(), [], $this->getAuthenticationInformation());

        $this->assertSame('1', $user['usergroup']);
    }

    /**
     * @test
     */
    public function updateUserRecordWithGuestAndReporterGroup()
    {
        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$this->getProviderMock(), '', '30', '', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn($this->getOauthUser(20));

        $user = $subject->updateUserRecord($this->getUserRecord(), [], $this->getAuthenticationInformation());

        $this->assertSame('1,2', $user['usergroup']);
    }

    /**
     * @test
     */
    public function updateUserRecordWithDefaultUserGroup()
    {
        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$this->getProviderMock(), '', '30', '3,4', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn($this->getOauthUser(0));

        $user = $subject->updateUserRecord($this->getUserRecord(), [], $this->getAuthenticationInformation());

        $this->assertSame('3,4', $user['usergroup']);
    }

    protected function getProviderMock()
    {
        return $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();
    }

    protected function getUserRecord()
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->setMethods(['toArray'])
            ->getMock();
        $resourceOwnerMock
            ->expects($this->any())
            ->method('toArray')
            ->willReturn([
                'username' => 'test',
                'email' => 'test@example.tld',
                'name' => 'test name',
            ]);

        return $resourceOwnerMock;
    }

    protected function getAuthenticationInformation()
    {
        return [
            'db_groups' => [
                'table' => 'be_groups',
            ],
        ];
    }

    protected function getOauthUser(int $level)
    {
        return new OauthUser($level);
    }
}
