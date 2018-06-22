<?php
declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Interface ResourceServerInterface
 * @package Mfc\OAuth2\ResourceServer
 * @author Christian Spoo <cs@marketing-factory.de>
 */
interface ResourceServerInterface
{
    /**
     * @return AbstractProvider
     */
    public function getOAuthProvider(): AbstractProvider;

    /**
     * @return string
     */
    public function getAuthorizationUrl(): string;

    /**
     * @param ResourceOwnerInterface $user
     * @return bool
     */
    public function userShouldBeAdmin(ResourceOwnerInterface $user): bool;

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getOAuthIdentifier(ResourceOwnerInterface $user): string;

    /**
     * @param ResourceOwnerInterface $user
     * @param array|null $currentRecord
     * @return array
     */
    public function updateUserRecord(ResourceOwnerInterface $user, array $currentRecord = null): array;

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getUsernameFromUser(ResourceOwnerInterface $user): string;

    /**
     * @param ResourceOwnerInterface $user
     * @return string
     */
    public function getEmailFromUser(ResourceOwnerInterface $user): string;
}
