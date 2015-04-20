<?php
/**
 * @author antoniofrignani@gmail.com
 */

namespace Codeception\Lib\Driver;

use Facebook\FacebookSession;
use Facebook\FacebookRequest;
use Facebook\FacebookRequestException;
use Facebook\FacebookRedirectLoginHelper;

class Facebook
{
    protected $logCallback;
    protected $configuration;
    protected $accessToken;
    protected $session;
    protected $request;
    protected $response;
    protected $testUser;

    public function __construct($config, $logCallback = null)
    {
        if (is_callable($logCallback)) {
            $this->logCallback = $logCallback;
        }

        FacebookSession::setDefaultApplication($config['app_id'], $config['secret']);
    }

    public function setAccessToken($accessToken)
    {
        try {
            $this->session = $this->makeSession($accessToken);
        } catch (FacebookRequestException $e) {
            $this->session = null;
        }

        $this->accessToken = $accessToken;
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
        return $this->accessToken;
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
    public function api( /* polymorphic */)
    {
        if (is_callable($this->logCallback)) {
            call_user_func($this->logCallback, 'Facebook API request', func_get_args());
        }

        $this->request = new FacebookRequest($this->session, func_get_args());

        $this->response = $this->request->execute();

        if (is_callable($this->logCallback)) {
            call_user_func($this->logCallback, 'Facebook API response', $response);
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
        
        $response = (new FacebookRequest(
                $this->session, 
                'POST', 
                '/' . $this->getAppId() . '/accounts/test-users', 
                ['permissions'  => implode(',', $permissions)]
            )
        )->execute();
        
        // set user access token
        $this->accessToken = $response->access_token;
        $this->setAccessToken($this->accessToken);

        return $response;
    }

    public function deleteTestUser($testUserID)
    {
        $this->session = FacebookSession::newAppSession();
        $this->api('/'.$testUserID,
                 'DELETE',
                 ['access_token' => $this->getApplicationAccessToken()]
        );
    }

    public function getLastPostsForTestUser()
    {
        return $this->api('me/posts', 'GET');
    }
}
