<?php

namespace Bolt\Extension\euismod2336\googlelogin;

use Bolt\BaseExtension;
use Google\appengine\api;

class Extension extends BaseExtension
{
	/**
	 * Initialize the extension. Here we set the routes and function to which we listen.
	 */
    public function initialize()
    {
	    $this->addTwigFunction('googlelogin_getLoginUrl', 'getLoginUrl');
        $this->addTwigFunction('googlelogin_isLoggedIn', 'isLoggedIn');
        $this->addTwigFunction('googlelogin_email', 'twigGetEmail');
        $this->addTwigFunction('googlelogin_name', 'twigGetName');

	    // TODO: could make this more flexible
	    $this->app->match('/bolt/extensions/oauth2callback', array($this,'doLogin'));
	    $this->app->match('/bolt/extensions/googlelogout', array($this,'doLogout'));

	    if ($this->isLoggedIn())
	    {
		    //print_r($this->app['twig']);
	    }
    }

	/**
	 * Function to forcibly logout a user and delete session data
	 *
	 * @return \Symfony\Component\HttpFoundation\RedirectResponse redirect to url from config
	 */
	public function doLogout()
	{
		if (isset($_SESSION['googlelogin.email']))
		{
			unset($_SESSION['googlelogin.email']);
			unset($_SESSION['googlelogin.displayname']);
			unset($_SESSION['googlelogin.name']);
		}

		return $this->app->redirect($this->config['callback_url'] . '?glr0');
    }

    /**
     * Function for returning the user's email address
     *
     * @return bool|string  the user's email address or FALSE if it's not available
     */
    public function twigGetEmail(){
        return isset($_SESSION['googlelogin.email']) ? $_SESSION['googlelogin.email'] : false;
    }

    /**
     * Function for returning the user's name
     *
     * @return bool|string  the user's name or FALSE if it's not available
     */
    public function twigGetName(){
        return isset($_SESSION['googlelogin.displayname']) ? $_SESSION['googlelogin.displayname'] : false;
    }

	/**
	 *
	 * @return bool true on logged in, false if not
	 */
    public function isLoggedIn()
    {
    	// simply check if we have valid email
        return isset($_SESSION['googlelogin.email']) && strlen($_SESSION['googlelogin.email']);
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
		$oGoogleClient->setAuthConfig($this->config['google_json']);

		// scope is required, even for the auth url
		$oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.email');
		$oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.profile');

		$sURL = $oGoogleClient->createAuthUrl();

		return $sURL;
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

		$oGoogleClient->setAuthConfig($this->config['google_json']);
		// TODO: could make this more flexible
		$oGoogleClient->setRedirectUri('http://' . $_SERVER['HTTP_HOST'] . '/bolt/extensions/oauth2callback');

		// set the information we want from the person
		$oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.email');
		$oGoogleClient->addScope('https://www.googleapis.com/auth/userinfo.profile');

		$oGoogleClient->authenticate($_GET['code']);
		$_SESSION['access_token'] = $oGoogleClient->getAccessToken();

		$oGooglePlus = new \Google_Service_Plus($oGoogleClient);
		$oUser = $oGooglePlus->people->get("me");

		$aAllowedDomains = $this->config['allowed'];

		// set the variables globally accessible
		$aEmails = $oUser->getEmails();
		foreach ($aEmails as $sEmail)
		{
			// only check for valid accounts
			if ($sEmail['type'] == 'account')
			{
				// we need values and not a wildcard as first one
				if (count($aAllowedDomains) && $aAllowedDomains[0] != '*')
				{
					$aExpMail = explode('@', $sEmail['value']);
					$sDomain = array_pop($aExpMail);

					if (!in_array($sDomain, $aAllowedDomains))
					{
						continue;
					}
				}

				$_SESSION['googlelogin.email'] = $sEmail['value'];
				break; // no need to look further
			}
		}

		// if we found a valid email, we also store the name
		if (isset($_SESSION['googlelogin.email']) && strlen($_SESSION['googlelogin.email']))
		{
			$_SESSION['googlelogin.name'] = $oUser->getName();
			$_SESSION['googlelogin.displayname'] = $oUser->getDisplayName();
		}

		// redirect to specified url along with the result
		return $this->app->redirect($this->config['callback_url'] . '?glr' . ($this->isLoggedIn() ? '1' : '0'));
    }

	/**
	 * Mandatory function to get the name of the extension
	 *
	 * @return string The name of the extension
	 */
    public function getName()
    {
        return "GoogleLogin";
    }

    /**
     * Mandatory function to ensure the plugin twig function can be loaded into a page contents
     * @return bool
     */
    public function isSafe()
    {
        return true;
    }
}
