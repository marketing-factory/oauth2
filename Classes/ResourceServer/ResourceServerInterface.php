<?php

declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\Cookie;
use TYPO3\CMS\Core\Crypto\PasswordHashing\PasswordHashInterface;

interface ResourceServerInterface
{
    public function getOAuthProvider(string $requestToken = ''): AbstractProvider;

    /**
     * @return array<string, string, Cookie>
     */
    public function getAuthorizationUrl(): array;

    public function userShouldBeAdmin(ResourceOwnerInterface $user): bool;

    public function userExpiresAt(ResourceOwnerInterface $user): ?\DateTime;

    public function userIsActive(ResourceOwnerInterface $user): bool;

    public function getOAuthIdentifier(ResourceOwnerInterface $user): string;

    public function loadUserDetails(ResourceOwnerInterface $user): void;

    public function getUsernameFromUser(ResourceOwnerInterface $user): string;

    public function getEmailFromUser(ResourceOwnerInterface $user): string;

    public function updateUserRecord(
        ResourceOwnerInterface $user,
        array $currentRecord,
        array $authenticationInformation,
        PasswordHashInterface $saltingInstance
    ): array;
}
