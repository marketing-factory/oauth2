TYPO3 Extension `oauth2` (`mfc/oauth2`)
=======================================

[![Latest Stable Version](https://poser.pugx.org/mfc/oauth2/v/stable)](https://packagist.org/packages/mfc/oauth2)
[![License](https://poser.pugx.org/mfc/oauth2/license)](https://packagist.org/packages/mfc/oauth2)

This extension provides OAuth 2.0 to TYPO3 installations 11LTS.


## 1. Features

- Can automatically create new backend users
- Certain OAuth resource servers can control admin permissions and assign backend group memberships

## 2. Usage

### 1) Installation

The only way to install this extension is by using [Composer][1]. In your Composer based TYPO3 project root, just run `composer require mfc/oauth2`.

### 2) Configure the extension

To add an OAuth2 Server for login, we recommend you create your own little extension, use your existing site 
package or put the configuration in your `typo3conf/AdditionalConfiguration.php`.

#### 2.1) Using the GitLab Provider included in this extension  

Configuring the GitLab Login Provider is pretty straight forward. Just put the following configuration into your `ext_localconf.php` 
or the aforementioned `typo3conf/AdditionalConfiguration.php` and customize it to your needs.

```php
Mfc\OAuth2\ResourceServer\Registry::addServer(
    'gitlab', // identifier for the Resource Server
    'Login with GitLab', // Text displayed on the Login Screen
    \Mfc\OAuth2\ResourceServer\GitLab::class,
    [
        'enabled'   => true, // Enable/Disable the provider
        'arguments' => [
            'appId'                => 'your-app-id',
            'appSecret'            => 'your-app-secret',
            'gitlabServer'         => 'https://gitlab.com', // Your GitLab Server
            'gitlabAdminUserLevel' => \Mfc\OAuth2\ResourceServer\GitLab::USER_LEVEL_DEVELOPER, // User level at which the user will be given admin permissions
            'gitlabDefaultGroups'  => '0', // Groups to assign to the User (comma separated list possible)
            'gitlabUserOption'     => 0, // UserConfig
            'blockExternalUser'    => false, // Blocks users with flag external from access the backend
            'projectName'          => 'your/repo', // the repository from which user information is fetched
        ],
    ]
);
``` 

You can obtain the required information for the provider by going to either 
[https://gitlab.com/profile/applications](https://gitlab.com/profile/applications) if you're using the hosted version of GitLab,
or to the equivalent page on your self-hosted GitLab server.

When creating the application within GitLab, you might need the following information:

- Redirect URI: `<your-domain-here>/typo3/index.php`
- Scopes: `api`,`read_user`,`openid`

#### 2.2 Creating your own provider

To create your own Provider, you need to create your own extension, and create a class which extends 
`Mfc\OAuth2\ResourceServer\AbstractResourceServer`. You can then use the same boilerplate shown in 2.1 to register 
your newly created provider. The `arguments` array included in the provider registration will be provided as-is as 
the first argument to your providers constructor, with the addition of a `providerName` key which contains the identifier 
you set in your registration.

**Example**

You've created your own extension, and created the class `Just\AnExample\Providers\ExampleProvider`.
To register your provider you'd extend the configuration as follows

```php
Mfc\OAuth2\ResourceServer\Registry::addServer(
    'example-provider', // identifier for the Resource Server
    'Login with Example', // Text displayed on the Login Screen
    \Just\AnExample\Providers\ExampleProvider::class,
    [
        'enabled'   => true, // Enable/Disable the provider
        'arguments' => [
            'yourarg' => 'somevalue',
            // ...
        ],
    ]
);
```

The first argument passed to your provider will be:

```php
array(
    'providerName' => 'example-provider',
    'yourarg' => 'somevalue',
    // ...
);
```


## 3. License

mfc/oauth2 is released under the terms of the [MIT License](LICENSE.md).

[1]: https://getcomposer.org/
