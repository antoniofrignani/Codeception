<?php
/**
 * @author antoniofrignani@gmail.com
 */

namespace Codeception\Lib\Driver;

use Exception;
use Facebook\FacebookSDKException;
use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookRedirectLoginHelper;

class FacebookV2
{
    protected $logCallback;
    protected $configuration;
    protected $session;
    protected $request;
    protected $response;
    protected $testUser;

    public function __construct($config, $logCallback = null)
    {
        if (is_callable($logCallback)) {
            $this->logCallback = $logCallback;
        }

        $this->configuration = $config;

        FacebookSession::setDefaultApplication($this->configuration['app_id'], $this->configuration['secret']);
    }

    protected function getAppID()
    {
        return ($this->configuration['app_id']);
    }

    public function setAccessToken($accessToken)
    {
        try {
            $this->session = $this->makeSession($accessToken);
        } catch (FacebookRequestException $e) {
            $this->session = null;
        }

        return $this;
    }

    public function makeSession($token)
    {
        try {
            $this->session = new FacebookSession($token);
            if (!$this->session->validate()) {
                $this->session = null;
                $this->request = null;
                $this->response = null;
            }
        } catch (FacebookRequestException $e) {
            // catch any exceptions
            $this->session = null;
            $this->request = null;
            $this->response = null;
            throw new Exception($e->getMessage(), $e->getCode());
        } catch (FacebookSDKException $e) {
            // catch any exceptions
            $this->session = null;
            $this->request = null;
            $this->response = null;
            throw new Exception($e->getMessage(), $e->getCode());
        }

        return $this->session;
    }

    public function getAccessToken()
    {
        if ($this->testUser) return $this->testUser->getProperty('access_token');
    }

    /**
     * Returns URL for test user auto-login.
     *
     * @return string
     */
    public function grabFacebookTestUserLoginUrl()
    {
        if ($this->testUser) return $this->testUser->getProperty('login_url');
    }

    public function getTestUserID()
    {
        if ($this->testUser) return $this->testUser->getProperty('id');
    }

    public function getTestUserEmail()
    {
        if ($this->testUser) return $this->testUser->getProperty('email');
    }

    public function getTestUserPassword()
    {
        if ($this->testUser) return $this->testUser->getProperty('password');
    }

    public function getLoginUrl()
    {
        $helper = new FacebookRedirectLoginHelper($this->redirect);
        return $helper->getLoginUrl($this->configuration['FB_APP_SCOPE']);
    }

    /**
     * @inheritdoc
     */
    protected function setPersistentData($key, $value)
    {
        // TODO: Implement setPersistentData() method.
    }

    /**
     * @inheritdoc
     */
    protected function getPersistentData($key, $default = false)
    {
        // TODO: Implement getPersistentData() method.
    }

    /**
     * @inheritdoc
     */
    protected function clearPersistentData($key)
    {
        // TODO: Implement clearPersistentData() method.
    }

    /**
     * @inheritdoc
     */
    protected function clearAllPersistentData()
    {
        // TODO: Implement clearAllPersistentData() method.
    }

    /**
     * @inheritdoc
     */
    public function api($method = 'GET', $endpoint = '/me', $options = [])
    {
        if (is_callable($this->logCallback)) {
            call_user_func($this->logCallback, 'Facebook API request', func_get_args());
        }

        $this->request = new FacebookRequest($this->session, $method, $endpoint, $options);

        $this->response = $this->request->execute();

        if (is_callable($this->logCallback)) {
            call_user_func($this->logCallback, 'Facebook API response', $this->response);
        }

        return $this->response;
    }

    /**
     * @param array $permissions
     *
     * @return array
     */
    public function createTestUser(array $permissions)
    {
        try {
            $this->session = FacebookSession::newAppSession();

            $response = $this->api(
                    'POST',
                    '/' . $this->getAppID() . '/accounts/test-users',
                    [
                        'permissions'  => implode(',', $permissions),
                        'installed' => true,

                    ]
                );

            // set test user data
            $this->testUser = $response->getGraphObject();
        } catch (FacebookSDKException $e) {
            return;
        }

        return $this;
    }

    public function deleteTestUser($testUser)
    {
        FacebookSession::setDefaultApplication($this->configuration['app_id'], $this->configuration['secret']);

        $this->session = FacebookSession::newAppSession();
        $this->setAccessToken($testUser->getAccessToken());

        $response = $this->api(
            'DELETE',
            '/' . $testUser->getTestUserID(),
             ['access_token' => $testUser->getAccessToken()]
        );

        return $response;
    }

    public function getLastPostsForTestUser()
    {
        return $this->api('GET', '/me/posts');
    }
}
