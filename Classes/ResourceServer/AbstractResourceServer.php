<?php
declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class AbstractResourceServer
 * @package Mfc\OAuth2\ResourceServer
 * @author Christian Spoo <cs@marketing-factory.de>
 */
abstract class AbstractResourceServer implements ResourceServerInterface
{
    /**
     * @param string $providerName
     * @return string
     */
    protected function getRedirectUri(string $providerName): string
    {
        return GeneralUtility::locationHeaderUrl('/typo3/index.php?loginProvider=1529672977&login_status=login&oauth-provider=' . $providerName);
    }
}
