.. include:: /Includes.rst.txt

================
Developer's Manual
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

This chapter provides technical documentation for developers working with the OAuth2 extension.

----

**For this document:**

:t3extapi:`TYPO3 Extension API r:n.n.n`

----

**Screenshots:**

(none)

----

**Target group:**

* **Developers**: Creating custom providers
* **Integrators**: Understanding the extension architecture
* **System Administrators**: Technical implementation details

----

**How to use this document:**

This document provides technical details and implementation guidelines for developers.

----

**Architecture Overview**

The extension follows these key architectural principles:

* Provider-based authentication
* Event-driven user management
* Flexible configuration system
* Extensible provider framework

----

**Creating a Custom Provider**

To create a custom provider:

1. Create a new class extending `AbstractResourceServer`:

   .. code-block:: php

      namespace YourNamespace\Provider;

      use Mfc\OAuth2\ResourceServer\AbstractResourceServer;

      class CustomProvider extends AbstractResourceServer
      {
          public function __construct(array $configuration)
          {
              parent::__construct($configuration);
              // Initialize your provider
          }

          public function getLoginUrl(): string
          {
              // Return OAuth2 login URL
          }

          public function handleCallback(array $request): array
          {
              // Handle OAuth2 callback
          }
      }

2. Register your provider:

   .. code-block:: php

      Mfc\OAuth2\ResourceServer\Registry::addServer(
          'custom-provider',
          'Login with Custom Provider',
          \YourNamespace\Provider\CustomProvider::class,
          [
              'enabled'   => true,
              'arguments' => [
                  // Your configuration options
              ],
          ]
      );

----

**Provider Interface**

The `AbstractResourceServer` class defines these key methods:

* `getLoginUrl()`: Returns the OAuth2 login URL
* `handleCallback()`: Processes the OAuth2 callback
* `getUserData()`: Retrieves user information
* `createBackendUser()`: Creates TYPO3 backend user

----

**Event System**

The extension provides events for customization:

* `BeforeUserCreationEvent`: Modify user data before creation
* `AfterUserCreationEvent`: Perform actions after user creation
* `LoginFailureEvent`: Handle login failures
* `ProviderConfigurationEvent`: Modify provider configuration

----

**User Management**

User management features:

* Automatic user creation
* Group assignment
* Permission mapping
* User validation

----

**Security Considerations**

Important security aspects:

* OAuth2 flow implementation
* Token handling
* User validation
* Permission checks
* Error handling

----

**Testing**

Testing guidelines:

* Unit testing providers
* Integration testing
* Security testing
* User flow testing

----

**Debugging**

Debugging tools and techniques:

* Logging system
* Debug mode
* Error handling
* Provider debugging

----

**Best Practices**

Development recommendations:

* Follow OAuth2 standards
* Implement proper error handling
* Use type hints
* Document your code
* Write tests
* Follow TYPO3 coding standards

----

**API Reference**

Key classes and interfaces:

* `AbstractResourceServer`
* `Registry`
* `UserManager`
* `TokenManager`

----

**Contributing**

How to contribute:

1. Fork the repository
2. Create a feature branch
3. Write tests
4. Submit a pull request

----

**Next Steps**

After understanding the technical details:

* Review the source code
* Study existing providers
* Create test implementations
* Consider contributing

----

**Related Resources**

* OAuth2 specification
* TYPO3 documentation
* Security guidelines
* Testing documentation 