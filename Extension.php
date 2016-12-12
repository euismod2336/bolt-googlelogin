<?php

namespace Bolt\Extension\euismod2336\googlelogin;

use Bolt\Application;
use Bolt\BaseExtension;

class Extension extends BaseExtension
{
    public function initialize()
    {
        /*$this->addCss('assets/extension.css');
        $this->addJavascript('assets/start.js', true);*/

        $this->addSnippet('endofbody', 'loginbuttonHTML');
    }

    public function isLoggedIn()
    {
        return true;
    }

    public function performLogin()
    {
        
    }

    public function loginbuttonHTML()
    {
        // which dumb system works in a way where html is just dumped in php files...
        return '<div class=""><p><a href="#">Klik hier om in te loggen</a> </p></div>';
    }

    public function getName()
    {
        return "GoogleLogin";
    }
}
