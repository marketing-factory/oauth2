<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Tests\Unit\ResourceServer;

use Mfc\OAuth2\Domain\Model\Dto\OauthUser;
use Mfc\OAuth2\ResourceServer\GitLab;
use Nimut\TestingFramework\TestCase\UnitTestCase;
use Omines\OAuth2\Client\Provider\Gitlab as GitLabOAuthProvider;
use Omines\OAuth2\Client\Provider\GitlabResourceOwner;

/**
 * Test class to test \Mfc\OAuth2\ResourceServer\GitLab
 * @package Mfc\OAuth2\Tests\Unit\ResourceServer
 * @author Tim Schreiner <schreiner.tim@gmail.com>
 */
class GitLabTest extends UnitTestCase
{
    /**
     * @test
     */
    public function authorizationUrlSuccessfulReturned()
    {
        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->setMethods(['getAuthorizationUrl'])
            ->getMock();
        $providerMock
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->with($this->equalTo([
                'scope' => ['api', 'read_user', 'openid']
            ]))
            ->willReturn('https://gitlab.example.tld');

        $subject = new GitLab($providerMock, 'gitlab', '', '', '', '');

        $this->assertSame('https://gitlab.example.tld', $subject->getAuthorizationUrl());
    }

    /**
     * @test
     */
    public function userExpectedToBeAdminIsAdmin()
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$providerMock, '', '30', '', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn(new OauthUser(30));

        $this->assertTrue($subject->userShouldBeAdmin($resourceOwnerMock));
    }

    /**
     * @test
     */
    public function userExpectedNotToBeAdminIsNotAdmin()
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$providerMock, '', '30', '', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn(new OauthUser(20));

        $this->assertFalse($subject->userShouldBeAdmin($resourceOwnerMock));
    }

    /**
     * @test
     */
    public function generateCorrectOAuthIdentifierForProject()
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->setMethods(['getId'])
            ->getMock();
        $resourceOwnerMock
            ->expects($this->once())
            ->method('getId')
            ->willReturn(1);

        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$providerMock, 'gitlab', '', '', '', ''])
            ->setMethods(['loadUserDetails'])
            ->getMock();

        $this->assertSame('gitlab|1', $subject->getOAuthIdentifier($resourceOwnerMock));
    }

    /**
     * Data provider for updateUserRecordWithValidData
     */
    public function updateUserRecordWithValidDataDataProvider()
    {
        return [
            // Check if all necessary data gets set without an existing user.
            [
                [],
                [
                    'email' => 'test@example.tld',
                    'name' => 'test user',
                    'username' => 'myTestUser',
                    'usergroup' => '1,2',
                ],
                [
                    'email' => 'test@example.tld',
                    'realname' => 'test user',
                    'username' => 'myTestUser',
                    'usergroup' => '1,2',
                    'options' => 0,
                ]
            ],
            // Check if the email address is getting updated.
            [
                [
                    'email' => 'test@example.tld',
                    'name' => 'test user',
                    'username' => 'myTestUser',
                    'usergroup' => '',
                ],
                [
                    'email' => 'test2@example.tld',
                    'name' => 'test user',
                ],
                [
                    'email' => 'test2@example.tld',
                    'realname' => 'test user',
                    'username' => 'myTestUser',
                    'usergroup' => '',
                    'options' => 0,
                ]
            ],
            // Check if email address and name is getting updated.
            [
                [
                    'email' => '',
                    'name' => '',
                    'username' => '',
                    'usergroup' => '1,2',
                ],
                [
                    'email' => 'test@example.tld',
                    'name' => 'test name',
                ],
                [
                    'email' => 'test@example.tld',
                    'realname' => 'test name',
                    'username' => '',
                    'usergroup' => '1,2',
                    'options' => 0,
                ]
            ],
            // Check if a field (myCustomField) that is not required by method is kept during process.
            [
                [
                    'email' => '',
                    'name' => '',
                    'username' => '',
                    'usergroup' => '1,2',
                    'myCustomField' => 'myValue',
                ],
                [
                    'email' => 'test@example.tld',
                    'name' => 'test name',
                ],
                [
                    'email' => 'test@example.tld',
                    'realname' => 'test name',
                    'username' => '',
                    'usergroup' => '1,2',
                    'myCustomField' => 'myValue',
                    'options' => 0,
                ]
            ],
        ];
    }

    /**
     * @test
     * @dataProvider updateUserRecordWithValidDataDataProvider
     * @param array $current
     * @param array $input
     * @param array $expected
     */
    public function updateUserRecordWithValidData(array $current, array $input, array $expected)
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->setMethods(['toArray'])
            ->getMock();
        $resourceOwnerMock
            ->expects($this->once())
            ->method('toArray')
            ->willReturn($input);

        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->setConstructorArgs([$providerMock, 'gitlab', '', '', '', ''])
            ->setMethods(['getUsernameFromUser', 'getUserGroupsForUser', 'loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('getUsernameFromUser')
            ->willReturn($input['username'] ?? $expected['username']);
        $subject
            ->expects($this->once())
            ->method('getUserGroupsForUser')
            ->willReturn($input['usergroup'] ?? $expected['usergroup']);
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn(new OauthUser(0));

        $result = $subject->updateUserRecord(
            $resourceOwnerMock,
            $current,
            [
                'db_groups' => [
                    'table' => ''
                ],
            ]
        );

        foreach($expected as $key => $value) {
            $this->assertSame($value, $result[$key]);
        }
    }

    /**
     * @test
     */
    public function getEmailFromUserWithValidData()
    {
        $expected = 'test@example.tld';
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->setMethods(['toArray'])
            ->getMock();
        $resourceOwnerMock
            ->expects($this->once())
            ->method('toArray')
            ->willReturn(['email' => $expected]);

        $providerMock = $this
            ->getMockBuilder(GitLabOAuthProvider::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = new GitLab($providerMock, 'gitlab', '', '', '', '');

        $this->assertSame($expected, $subject->getEmailFromUser($resourceOwnerMock));
    }

    /**
     * @test
     */
    public function userIsActiveWithAccessLevelGreaterZero()
    {
        $user = new OauthUser(30);

        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->disableOriginalConstructor()
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn($user);

        $this->assertTrue($subject->userIsActive($resourceOwnerMock));
    }

    /**
     * @test
     */
    public function userIsInactiveWithAccessLevelZero()
    {
        $user = new OauthUser(0);

        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->disableOriginalConstructor()
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn($user);

        $this->assertFalse($subject->userIsActive($resourceOwnerMock));
    }

    /**
     * @test
     */
    public function userIsInactiveWithInvalidUser()
    {
        $resourceOwnerMock = $this
            ->getMockBuilder(GitlabResourceOwner::class)
            ->disableOriginalConstructor()
            ->getMock();

        $subject = $this
            ->getMockBuilder(GitLab::class)
            ->disableOriginalConstructor()
            ->setMethods(['loadUserDetails'])
            ->getMock();
        $subject
            ->expects($this->once())
            ->method('loadUserDetails')
            ->willReturn(null);

        $this->assertFalse($subject->userIsActive($resourceOwnerMock));
    }
}
