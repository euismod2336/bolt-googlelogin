<?php

namespace Bolt\Extension\euismod2336\GoogleLogin;

use Bolt\Extension\SimpleExtension;
use Google\appengine\api;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Silex\ControllerCollection;


/**
 * GoogleLoginExtension for logging in through the Google OAuth service
 *
 * @author Your Name <you@example.com>
 */
class GoogleLoginExtension extends SimpleExtension
{
    /**
     * {@inheritdoc}
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $collection->match('/bolt/extensions/oauth2callback', [$this, 'doLogin']);
        $collection->match('/bolt/extensions/googlelogout', [$this, 'doLogout']);
    }

    protected function registerTwigFunctions()
    {
        return [
            'googlelogin_getLoginUrl' => ['getLoginUrl', ['is_safe' => ['html'],'safe'=>true]],
            'googlelogin_isLoggedIn' => ['isLoggedIn', ['is_safe' => ['html'],'safe'=>true]],
            'googlelogin_email' => ['twigGetEmail', ['is_safe' => ['html'],'safe'=>true]],
            'googlelogin_name' => ['twigGetName', ['is_safe' => ['html'],'safe'=>true]],
            'googleloginpage' => ['twigShowLoginPage', ['is_safe' => ['html'],'safe'=>true]],
            'requiregooglelogin' => ['twigRequireGoogleLogin', ['is_safe' => ['html'], 'safe' => true]]
        ];
    }

    /**
     * Function to restrict page access when a user is not logged in, if so, the user will be redirected to the login page
     *
     * @return bool|RedirectResponse
     */
    public function twigRequireGoogleLogin(){
        if(!$this->isLoggedIn()){
            return $this->renderTemplate('redirect.twig',['redirect_url' => $this->container['request']->getSchemeAndHttpHost().'/'.$this->getConfig()['login_page'].'?redirect='.urlencode($this->container['request']->getRequestUri())]);
            //return $this->container['request']->getSchemeAndHttpHost().'/'.$this->getConfig()['login_page'];
            //return new RedirectResponse($this->container['request']->getSchemeAndHttpHost().'/'.$this->getConfig()['login_page'].'?redirect='.urlencode($this->container['request']->getRequestUri()));
        }
        return '<!-- user is logged in -->';
    }

    /**
     * Function to show the complete log in page, including a logout link
     *
     * @return string
     */
    public function twigShowLoginPage(){
        $context = [];
        if($this->container['request']->get('redirect',false)){
            $this->container['session']->set('redirect_after_login',$this->container['request']->get('redirect'));
        } elseif($this->container['session']->has('redirect_after_login')){
            $context['redirect_url'] = $this->container['session']->get('redirect_after_login');
            $this->container['session']->remove('redirect_after_login');
        }
        return $this->renderTemplate('googleloginpage.twig',$context);
    }

    /**
     * Function to forcibly logout a user and delete session data
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse redirect to url from config
     */
    public function doLogout()
    {
        if ($this->container['session']->has('googlelogin.email'))
        {
            $this->container['session']->remove('googlelogin.email');
            $this->container['session']->remove('googlelogin.displayname');
            $this->container['session']->remove('googlelogin.name');
            $this->container['session']->remove('redirect_after_login');
            $this->container['session']->remove('access_token');
        }

        return new RedirectResponse($this->container['request']->getSchemeAndHttpHost().'/'.$this->getConfig()['callback_url'] . '?glr0');
    }

    /**
     * Function for returning the user's email address
     *
     * @return bool|string  the user's email address or FALSE if it's not available
     */
    public function twigGetEmail(){
        return $this->container['session']->get('googlelogin.email',false);
    }

    /**
     * Function for returning the user's name
     *
     * @return bool|string  the user's name or FALSE if it's not available
     */
    public function twigGetName(){
        return $this->container['session']->get('googlelogin.displayname',false);
    }

    /**
     *
     * @return bool true on logged in, false if not
     */
    public function isLoggedIn()
    {
        // simply check if we have valid email
        return $this->container['session']->get('googlelogin.email',false);
    }

    /**
     * Get the auth url for current User.
     *
     * @return string the Auth url to which the user should be directed
     */
    public function getLoginUrl()
    {
        session_start();

        $oGoogleClient = new \Google_Client();
        $oGoogleClient->setAuthConfig($this->getConfig()['google_json']);

        // scope is required, even for the auth url
        $oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.email');
        $oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.profile');

        $sURL = $oGoogleClient->createAuthUrl();

        return $sURL;
    }

    protected function find_in_multidim_array($needle, array $haystack, $strict = false)
    {
        foreach ($haystack as $subarray) {
            if(in_array($needle, $subarray,$strict)){
                return true;
            }
        }
        return false;
    }

    /**
     * The magic is done here, the user is returned from google and funneld here to login.
     * This function will only allow logins from specified domain (unless a * is used)
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse redirect to url from config
     */
    public function doLogin()
    {
        session_start();

        $oGoogleClient = new \Google_Client();

        $oGoogleClient->setAuthConfig($this->getConfig()['google_json']);
        // TODO: could make this more flexible
        $oGoogleClient->setRedirectUri($this->container['request']->getSchemeAndHttpHost().'/bolt/extensions/oauth2callback');

        // set the information we want from the person
        $oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.email');
        $oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.profile');

        $oGoogleClient->authenticate($_GET['code']);
        $this->container['session']->set('access_token',$oGoogleClient->getAccessToken());

        $oGooglePlus = new \Google_Service_Plus($oGoogleClient);
        $oUser = $oGooglePlus->people->get("me");
        $aAllowedAddresses = [];
        foreach($this->getConfig()['allowed_emails'] as $emails){
            $aAllowedAddresses = array_merge($aAllowedAddresses,$emails);
        }
        $aAllowedAddresses = array_map('strtolower',$aAllowedAddresses);

        // set the variables globally accessible
        $aEmails = $oUser->getEmails();
        foreach ($aEmails as $sEmail)
        {
            // only check for valid accounts
            if ($sEmail['type'] == 'account')
            {
                // we need values and not a wildcard as first one
                if (count($aAllowedAddresses))
                {
                    if (! in_array($sEmail['value'], $aAllowedAddresses))
                    {
                        continue;
                    }
                }
                $this->container['session']->set('googlelogin.email',$sEmail['value']);
                break; // no need to look further
            }
        }

        // if we found a valid email, we also store the name
        if ($this->container['session']->has('googlelogin.email'))
        {
            $this->container['session']->set('googlelogin.name',$oUser->getName());
            $this->container['session']->set('googlelogin.displayname',$oUser->getDisplayName());
        }

        // redirect to specified url along with the result
        return new RedirectResponse('/'.$this->getConfig()['login_page'] . '?gloggedin=' . ($this->isLoggedIn() ? '1' : '0'));
        die();
    }
}
