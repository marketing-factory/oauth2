.. include:: /Includes.rst.txt

================
Configuration
================

:Extension key:
   oauth2

:Package name:
   mfc/oauth2

:Language:
   en

:Author:
   Marketing Factory

:License:
   This document is published under the
   `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
   license.

:Rendered:
   |today|

----

**Abstract:**

This chapter explains how to configure the OAuth2 extension and its various providers.

----

**For this document:**

:t3extapi:`TYPO3 Extension API r:n.n.n`

----

**Screenshots:**

(none)

----

**Target group:**

* **Administrators**: Configuring OAuth2 providers and settings
* **Developers**: Understanding configuration options

----

**How to use this document:**

This document provides detailed information about all configuration options available in the OAuth2 extension.

----

**Basic Configuration**

The basic configuration is done in your TYPO3 installation's configuration file:

.. code-block:: php

   $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieSameSite'] = 'lax';

----

**Provider Configuration**

Providers are configured using the Registry class:

.. code-block:: php

   Mfc\OAuth2\ResourceServer\Registry::addServer(
       'provider-identifier',
       'Display Name',
       ProviderClass::class,
       [
           'enabled'   => true,
           'arguments' => [
               // Provider-specific arguments
           ],
       ]
   );

----

**GitLab Provider Configuration**

The GitLab provider supports the following configuration options:

.. code-block:: php

   Mfc\OAuth2\ResourceServer\Registry::addServer(
       'gitlab',
       'Login with GitLab',
       \Mfc\OAuth2\ResourceServer\GitLab::class,
       [
           'enabled'   => true,
           'arguments' => [
               'appId'                => 'your-app-id',
               'appSecret'            => 'your-app-secret',
               'gitlabServer'         => 'https://gitlab.com',
               'gitlabAdminUserLevel' => \Mfc\OAuth2\ResourceServer\GitLab::USER_LEVEL_DEVELOPER,
               'gitlabDefaultGroups'  => '0',
               'gitlabUserOption'     => 0,
               'blockExternalUser'    => false,
               'projectName'          => 'your/repo',
           ],
       ]
   );

Configuration Options:

* **appId**: Your GitLab application ID
* **appSecret**: Your GitLab application secret
* **gitlabServer**: URL of your GitLab instance
* **gitlabAdminUserLevel**: User level for admin permissions
* **gitlabDefaultGroups**: Default backend groups for new users
* **gitlabUserOption**: User configuration options
* **blockExternalUser**: Whether to block external users
* **projectName**: GitLab project name

----

**Custom Provider Configuration**

When creating a custom provider, you can pass any configuration options:

.. code-block:: php

   Mfc\OAuth2\ResourceServer\Registry::addServer(
       'custom-provider',
       'Login with Custom Provider',
       \YourNamespace\Provider\CustomProvider::class,
       [
           'enabled'   => true,
           'arguments' => [
               'customOption1' => 'value1',
               'customOption2' => 'value2',
           ],
       ]
   );

----

**User Management Configuration**

The extension supports various user management options:

* **Automatic User Creation**: Users can be created automatically on first login
* **Group Assignment**: Users can be assigned to specific backend groups
* **Permission Control**: Admin permissions can be controlled via OAuth provider
* **External User Blocking**: Option to block external users

----

**Security Configuration**

Important security settings:

* **Cookie Settings**: Must be set to 'lax' for OAuth2 redirects
* **Provider Scopes**: Configure required OAuth2 scopes
* **User Validation**: Set up user validation rules
* **Permission Checks**: Configure permission verification

----

**Troubleshooting Configuration**

Common configuration issues and solutions:

* **Login Failures**: Check provider credentials and redirect URIs
* **Permission Issues**: Verify group assignments and admin levels
* **User Creation Problems**: Review database permissions and configuration

----

**Best Practices**

Configuration recommendations:

* Use environment variables for sensitive data
* Implement proper error handling
* Regular security audits
* Keep provider configurations up to date
* Monitor user access patterns

----

**Next Steps**

After configuration:

* Test the setup thoroughly
* Review the :ref:`EditorsManual` for user instructions
* Check the :ref:`DevelopersManual` for advanced customization 