<?php

namespace Bolt\Extension\euismod2336\googlelogin;

if (isset($app)) {
    $app['extensions']->register(new Extension($app));
}

