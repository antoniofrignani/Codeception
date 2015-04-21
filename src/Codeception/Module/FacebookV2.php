<?php

namespace Codeception\Module;

use Codeception\Exception\Module as ModuleException;
use Codeception\Exception\ModuleConfig as ModuleConfigException;
use Codeception\Module as BaseModule;
use Codeception\Lib\Driver\FacebookV2 as FacebookDriver;

/**
 * Provides testing for projects integrated with Facebook API (using api V2 and SDK 4).
 * Relies on Facebook's tool Test User API.
 *
 * <div class="alert alert-info">
 * To use this module with Composer you need <em>"facebook/php-sdk": "~4.0.*"</em> package.
 * </div>
 *
 * ## Status
 *
 * * Maintainer: **antoniofrignani**
 * * Stability: **beta**
 * * Contact: antoniofrignani@gmail.com
 *
 * ## Config
 *
 * * app_id *required* - Facebook application ID
 * * secret *required* - Facebook application secret
 * * permissions *optional* a list of permission your app is requesting to users
 * * test_user - Facebook test user parameters:
 *     * name - You can specify a name for the test user you create. The specified name will also be used in the email address assigned to the test user.
 *     * locale - You can specify a locale for the test user you create, the default is en_US. The list of supported locales is available at https://www.facebook.com/translations/FacebookLocales.xml
 *     * permissions - An array of permissions. Your app is granted these permissions for the new test user. The full list of permissions is available at https://developers.facebook.com/docs/authentication/permissions
 *
 * ### Config example
 *
 *     modules:
 *         enabled: [FacebookV2]
 *         config:
 *             FacebookV2:
 *                 app_id: 412345678901234
 *                 secret: ccb79c1b0fdff54e4f7c928bf233aea5
 *                 permissions: [public_profile,user_friends,email]
 *
 * ###  Test example:
 *
 * ``` php
 * <?php
 * $I = new ApiGuy($scenario);
 * $I->am('Guest');
 * $I->wantToTest('check-in to a place be published on the Facebook using API');
 * $I->haveFacebookTestUserAccount();
 * $accessToken = $I->grabFacebookTestUserAccessToken();
 * $I->haveHttpHeader('Auth', 'FacebookToken ' . $accessToken);
 * $I->amGoingTo('send request to the backend, so that it will publish on user\'s wall on Facebook');
 * $I->sendPOST('/api/v1/some-api-endpoint');
 * $I->seePostOnFacebookWithAttachedPlace('167724369950862');
 *
 * ```
 *
 * ``` php
 * <?php
 * $I = new WebGuy($scenario);
 * $I->am('Guest');
 * $I->wantToTest('log in to site using Facebook');
 * $I->haveFacebookTestUserAccount(); // create facebook test user
 * $I->haveTestUserLoggedInOnFacebook(); // so that facebook will not ask us for login and password
 * $fbUserFirstName = $I->grabFacebookTestUserFirstName();
 * $I->amOnPage('/welcome');
 * $I->see('Welcome, Guest');
 * $I->click('Login with Facebook');
 * $I->see('Welcome, ' . $fbUserFirstName);
 *
 * ```
 *
 * @since 2.1.0
 * @author antoniofrignani@gmail.com
 */
class FacebookV2 extends BaseModule
{
    protected $requiredFields = array('app_id', 'secret');

    /**
     * @var FacebookDriver
     */
    protected $facebook;

    /**
     * @var array
     */
    protected $testUser = array();

    protected function deleteTestUser()
    {
        if ($this->testUser) {
            // make api-call for test user deletion
            $this->facebook->deleteTestUser($this->testUser);
            $this->testUser = array();
        }
    }

    public function _initialize()
    {
        if (! array_key_exists('test_user', $this->config)) {
            $this->config['test_user'] = array(
                'permissions' => array()
            );
        } elseif (! array_key_exists('permissions', $this->config['test_user'])) {
            $this->config['test_user']['permissions'] = array();
        }

        $this->facebook = new FacebookDriver($this->config,
            function ($title, $message) {
                if (version_compare(PHP_VERSION, '5.4', '>=')) {
                    $this->debugSection($title, $message);
                }
            }
        );
    }

    public function _afterSuite()
    {
        $this->deleteTestUser();
    }

    /**
     * Get facebook test user be created.
     *
     * *Please, note that the test user is created only at first invoke, unless $renew arguments is true.*
     *
     * @param bool $renew true if the test user should be recreated
     */
    public function haveFacebookTestUserAccount($renew = false)
    {
        if ($renew && $this->testUser) {
            $this->deleteTestUser($this->testUser);
        }

        // make api-call for test user creation only if it's not yet created
        if (empty($this->testUser)) {
            $this->testUser = $this->facebook->createTestUser(
                $this->config['permissions']
            );
        }
    }

    /**
     * Get facebook test user be logged in on facebook.
     *
     * @throws ModuleConfigException
     */
    public function haveTestUserLoggedInOnFacebook()
    {
        if (! $this->hasModule('PhpBrowser')) {
            throw new ModuleConfigException(
                __CLASS__,
                'PhpBrowser module has to be enabled to be able to login to Facebook.'
            );
        }

        if (empty($this->testUser)) {
            throw new ModuleException(
                __CLASS__,
                'Facebook test user was not found. Did you forget to create one?'
            );
        }

        /** @var PhpBrowser $phpBrowser */
        $phpBrowser = $this->getModule('PhpBrowser');
        $phpBrowserURL = $phpBrowser->_getUrl();

        // go to facebook and make login; it work only if you visit facebook.com first
        $phpBrowser->amOnUrl('https://www.facebook.com/');
        //$phpBrowser->amOnPage($this->testUser->grabFacebookTestUserLoginUrl());
        $phpBrowser->fillField(['name' => 'email'], $this->grabFacebookTestUserEmail());
        $phpBrowser->fillField(['name' => 'pass'], $this->grabFacebookTestUserPassword());
        $phpBrowser->click('input[type=submit]', '#loginbutton');
        $phpBrowser->amOnUrl($phpBrowserURL);
    }

    /**
     * Returns the test user access token.
     *
     * @return string
     */
    public function grabFacebookTestUserAccessToken()
    {
        return $this->testUser->getAccessToken();
    }

    /**
     * Returns the test user id.
     *
     * @return string
     */
    public function grabFacebookTestUserId()
    {
        return $this->testUser->getTestUserID();
    }

    /**
     * Returns the test user email.
     *
     * @return string
     */
    public function grabFacebookTestUserEmail()
    {
        return $this->testUser->getTestUserEmail();
    }

    /**
     * Returns the test user password.
     *
     * @return string
     */
    public function grabFacebookTestUserPassword()
    {
        return $this->testUser->getTestUserPassword();
    }

    /**
     * Returns URL for test user auto-login.
     *
     * @return string
     */
    public function grabFacebookTestUserLoginUrl()
    {
        return $this->facebook->grabFacebookTestUserLoginUrl();
    }

    /**
     * Returns the test user first name.
     *
     * @return string
     */
    public function grabFacebookTestUserFirstName()
    {
        if (! array_key_exists('profile', $this->testUser)) {
            $this->testUser['profile'] = $this->facebook->api('/me');
        }
        return $this->testUser['profile']['first_name'];
    }

    /**
     *
     * Please, note that you must have publish_stream permission to be able to publish to user's feed.
     *
     * @param string $placeId Place identifier to be verified against user published posts
     */
    public function seePostOnFacebookWithAttachedPlace($placeId)
    {
        $posts = $this->facebook->getLastPostsForTestUser();

        if ($posts['data']) {
            foreach ($posts['data'] as $post) {
                if (array_key_exists('place', $post) && ($post['place']['id'] == $placeId)) {
                    return; // success
                }
            }
        }

        $this->fail('Failed to see post on Facebook with attached place with id ' . $placeId);
    }
}
