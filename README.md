TYPO3 Extension `oauth2` (`mfc/oauth2`)
=======================================

[![Latest Stable Version](https://poser.pugx.org/mfc/oauth2/v/stable)](https://packagist.org/packages/mfc/oauth2)
[![License](https://poser.pugx.org/mfc/oauth2/license)](https://packagist.org/packages/mfc/oauth2)

This extension provides OAuth 2.0 to TYPO3 installations (only version 8LTS and the upcoming version 9 for the time being).


## 1. Features

- Can automatically create new backend users
- Certain OAuth resource servers can control admin permissions and assign backend group memberships

## 2. Usage

### 1) Installation

The only way to install this extension is by using [Composer][1]. In your Composer based TYPO3 project root, just run `composer require mfc/oauth2`.

### 2) Configure the extension

In order to get this extension working, you need to prepare your GitLab instance 
and your TYPO3 system.

#### 2.1) Preparing your GitLab instance

1. Login to your GitLab instance with an administrator account
2. Move to the *Admin Area* and then to *Applications*
3. Create a new Application
    1. Choose a name you like and describes your application
    2. Add redirect urls like `https://www.example.tld/typo3/index.php`
    3. Enable *trusted* checkbox (optional)
    4. Add scopes: `api`, `read_user` and `openid`
    5. Save the application

You do not need to write down the Application ID and Secret at this moment.
You can view them any time you want to by clicking on the application name.

#### 2.2) Configuring TYPO3

1. Login to your TYPO3 backend with a regular admin account (username and password)
2. If not done yet, enable the extension in the extension manager
3. Move on the extension configuration
    1. For TYPO3 v8 you find them in the Extensions module
    2. For TYPO3 v9 you find them in the new Settings module
4. Enter the settings that match you configuration
    1. Activate *Enable Backend Login* checkbox
    2. Enter the GitLab Application ID that you received after creating the 
       application in step 2.1
    3. Enter the GitLab Application Secret that you received after creating the 
       application in step 2.1
    4. Enter your GitLabs server address like `https://gitlab.example.tld`
    5. Enter a project repository name that will be used to check if a user  
       should get access to the TYPO3 backend. Example: `my-group/my-typo3-repository`
    6. If you want to be able to login with already existing users, you can
       activate the checkbox *override existing user*
    7. If you need to add a user group to every user logged in by oauth2 (even admins!),
       you can add list of IDs here. 
    8. You can configure how TYPO3 should handle the db and file mounts for users
    9. Enter a level at which a user will automatically be an admin after login.
       This level will be tested against GitLabs access level to the project.
       (10 = Guest, 20 = Reporter, 30 = Developer, 40 = Maintainer, 50 = Owner)
    10. Save the configurations

#### 2.3) Configure backend groups

If you want users to get automatically assigned to backend groups, you have to 
do some configuration. Edit a backend user group in TYPO3 backend. In the Tab 
*Extended* you find a multi select. You can choose the access levels that will
be mapped to that group. When a user with that access level logs into your backend,
whe user is automatically assigned to that backend group (even admins).


That's it. You should now be able to login to TYPO3 using your GitLab as an oAuth 2 provider.



## 3. License

mfc/oauth2 is released under the terms of the [MIT License](LICENSE.md).

[1]: https://getcomposer.org/
