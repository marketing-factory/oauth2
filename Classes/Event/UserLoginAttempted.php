<?php

namespace Mfc\OAuth2\Event;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

/**
 * Dispatched when an OAuth user, already confirmed as active by the resource
 * server, is about to be logged in. Listeners can veto the login.
 */
final class UserLoginAttempted
{
    private bool $denied = false;

    public function __construct(
        public readonly ResourceOwnerInterface $resourceOwner,
        public readonly array $userRecord,
    ) {}

    public function deny(): void
    {
        $this->denied = true;
    }

    public function isDenied(): bool
    {
        return $this->denied;
    }
}
