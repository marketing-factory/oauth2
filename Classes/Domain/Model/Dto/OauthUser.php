<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Domain\Model\Dto;

/**
 * Class OauthUser
 * @package Mfc\OAuth2\Domain\Model\Dto
 * @author Tim Schreiner <schreiner.tim@gmail.com>
 */
class OauthUser
{
    /**
     * @var int
     */
    protected $accessLevel = 0;

    /**
     * GitLabUser constructor.
     * @param int $accessLevel
     */
    public function __construct(int $accessLevel)
    {
        $this->accessLevel = $accessLevel;
    }

    /**
     * @return int
     */
    public function getAccessLevel(): int
    {
        return $this->accessLevel;
    }
}
