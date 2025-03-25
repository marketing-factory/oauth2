.. include:: /Includes.rst.txt

================
Installation
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

This chapter explains how to install and set up the OAuth2 extension in your TYPO3 installation.

----

**For this document:**

:t3extapi:`TYPO3 Extension API r:n.n.n`

----

**Screenshots:**

(none)

----

**Target group:**

* **Administrators**: Setting up the extension
* **Developers**: Understanding the installation process

----

**How to use this document:**

This document provides step-by-step instructions for installing and configuring the OAuth2 extension.

----

**Prerequisites:**

* TYPO3 v11.5 or higher
* Composer-based installation
* Access to your TYPO3 installation
* OAuth2 provider credentials (if using external providers)

----

**Installation via Composer**

The recommended way to install this extension is using Composer:

.. code-block:: bash

   composer require mfc/oauth2

----

**Basic Configuration**

After installation, you need to configure the extension:

1. Configure cookie settings in your TYPO3 installation:

   .. code-block:: php

      $GLOBALS['TYPO3_CONF_VARS']['BE']['cookieSameSite'] = 'lax';

2. Add this configuration to your `typo3conf/AdditionalConfiguration.php` or `ext_localconf.php`.

----

**Provider Setup**

1. Choose your OAuth2 provider (e.g., GitLab, Google, etc.)
2. Register your application with the provider
3. Obtain the necessary credentials (client ID, client secret)
4. Configure the provider in your TYPO3 installation

----

**Example: GitLab Provider Setup**

1. Go to your GitLab instance (e.g., https://gitlab.com/profile/applications)
2. Create a new application with the following settings:

   * Redirect URI: `<your-domain>/typo3/index.php`
   * Scopes: `api`, `read_user`, `openid`

3. Configure the provider in your TYPO3 installation:

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

----

**Verification**

After installation and configuration:

1. Clear TYPO3's cache
2. Log out of the TYPO3 backend
3. Try logging in with your OAuth2 provider
4. Verify that user creation and permissions work as expected

----

**Troubleshooting**

Common issues and solutions:

* **Login not working**: Check cookie settings and provider configuration
* **User creation failing**: Verify database permissions and configuration
* **Permission issues**: Review group assignments and admin levels

----

**Next Steps**

After successful installation:

* Review the :ref:`Configuration` chapter for detailed settings
* Check the :ref:`EditorsManual` for user instructions
* Consult the :ref:`DevelopersManual` for custom provider development 