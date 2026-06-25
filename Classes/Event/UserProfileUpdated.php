<?php

namespace Mfc\OAuth2\Event;

use League\OAuth2\Client\Provider\ResourceOwnerInterface;

class UserProfileUpdated
{
    public function __construct(
        public ResourceOwnerInterface $resourceOwner,
        public array $userRecord,
    ) {
    }
}
