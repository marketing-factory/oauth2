<?php
declare(strict_types=1);

namespace Mfc\OAuth2\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class OAuth2Eid
 * @package Mfc\OAuth2\Services
 * @author Christian Spoo <cs@marketing-factory.de>
 */
class OAuth2Eid
{
    public function processRequest(ServerRequestInterface $request, ResponseInterface $response)
    {
        return $response;
    }
}
