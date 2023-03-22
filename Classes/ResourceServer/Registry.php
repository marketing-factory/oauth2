<?php

declare(strict_types=1);

namespace Mfc\OAuth2\ResourceServer;

use Mfc\OAuth2\Exceptions\InvalidResourceServerException;
use Mfc\OAuth2\Exceptions\NotRegisteredException;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class Registry
{
    /**
     * Registered Resource Servers
     */
    private static array $registry = [];

    /**
     * Registers a new ResourceServer for use within the Login Service
     *
     * @param string $identifier Identifier the ResourceServer is referenced by
     * @param string $title Title that is displayed on the login screen
     * @param string $className Implenting class
     * @param array $options Array of options for the ResourceServer
     *
     * @throws InvalidResourceServerException
     */
    public static function addServer(
        string $identifier,
        string $title,
        string $className,
        array $options
    ): void {
        if (!is_subclass_of($className, ResourceServerInterface::class)) {
            $message = sprintf(
                '"%s" does not implement "%s" and is therefor invalid',
                $className,
                ResourceServerInterface::class
            );
            throw new InvalidResourceServerException($message, 1558815163);
        }

        $options = self::normalizeOptionsArray($options, $identifier);

        self::$registry[$identifier] = [
            'className' => $className,
            'instance' => null,
            'options' => $options,
            'enabled' => $options['enabled'],
            'title' => $title,
        ];
    }

    /**
     * Gets an instance of $identifier from registry
     */
    public static function getResourceServerInstance(string $identifier): AbstractResourceServer
    {
        if (!array_key_exists($identifier, self::$registry)) {
            $message = sprintf('"%s" has not been registered as a ResourceServer', $identifier);
            throw new NotRegisteredException($message, 1558815703);
        }

        $entry = &self::$registry[$identifier];
        if ($entry['instance'] === null) {
            $entry['instance'] = GeneralUtility::makeInstance(
                $entry['className'],
                $entry['options']['arguments']
            );
        }

        return $entry['instance'];
    }

    public static function getAvailableResourceServers(): array
    {
        $available = [];

        foreach (self::$registry as $identifier => $config) {
            if ($config['enabled']) {
                $available[] = [
                    'title' => $config['title'],
                    'identifier' => $identifier,
                ];
            }
        }

        return $available;
    }

    private static function normalizeOptionsArray(array $options, string $identifier): array
    {
        $options = array_merge(
            [
                'enabled' => false,
                'arguments' => [],
            ],
            $options
        );

        $options['arguments']['providerName'] = $identifier;

        return $options;
    }
}
