<?php

namespace Mfc\OAuth2\ResourceServer;

use Mfc\OAuth2\Exceptions\InvalidResourceServerException;
use Mfc\OAuth2\Exceptions\NotRegisteredException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class ResourceServerRegistry
 *
 * @author  Fabian Bettag <hi@chee.codes>
 * @package Mfc\OAuth2\ResourceServer
 */
class Registry
{

    /**
     * @var array Registered Resource Servers
     */
    private static $registry = [];

    /**
     * Registers a new ResourceServer for use within the Login Service
     *
     * @param string $identifier Identifier the ResourceServer is referenced by
     * @param string $title      Title that is displayed on the login screen
     * @param string $className  Implenting class
     * @param array  $options    Array of options for the ResourceServer
     *
     * @throws \Mfc\OAuth2\Exceptions\InvalidResourceServerException
     */
    public static function addServer(
        string $identifier,
        string $title,
        string $className,
        array $options
    ): void {
        if ( !is_subclass_of($className, ResourceServerInterface::class)) {
            $message = sprintf('"%s" does not implement "%s" and is therefor invalid', $className,
                ResourceServerInterface::class);
            throw new InvalidResourceServerException($message, 1558815163);
        }

        $options = self::normalizeOptionsArray($options, $identifier);

        self::$registry[$identifier] = [
            'className' => $className,
            'instance'  => null,
            'options'   => $options,
            'enabled'   => $options['enabled'],
            'title'     => $title,
        ];
    }

    /**
     * Gets an instance out of the registry
     *
     * @param string $identifier
     *
     * @return \Mfc\OAuth2\ResourceServer\AbstractResourceServer
     * @throws \Mfc\OAuth2\Exceptions\NotRegisteredException
     */
    public static function getResourceServerInstance(string $identifier): AbstractResourceServer
    {
        if ( !array_key_exists($identifier, self::$registry)) {
            $message = sprintf('"%s" has not been registered as a ResourceServer', $identifier);
            throw new NotRegisteredException($message, 1558815703);
        }

        if (self::$registry[$identifier]['instance'] === null) {
            self::$registry[$identifier]['instance'] = GeneralUtility::makeInstance(
                self::$registry[$identifier]['className'],
                self::$registry[$identifier]['options']['arguments']
            );
        }

        return self::$registry[$identifier]['instance'];
    }

    public static function getAvailableResourceServers(): array
    {
        $available = [];

        foreach (self::$registry as $identifier => $config) {
            if ($config['enabled']) {
                $available[] = [
                    'title'      => $config['title'],
                    'identifier' => $identifier,
                ];
            }
        }

        return $available;
    }

    /**
     * @param array  $options
     *
     * @param string $identifier
     *
     * @return array
     */
    private static function normalizeOptionsArray(array $options, string $identifier): array
    {
        $options = array_merge([
            'enabled'   => false,
            'arguments' => [],
        ], $options);

        $options['arguments']['providerName'] = $identifier;

        return $options;
    }
}