<?php

use Laravel\Fortify\Features;

return ['guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'lowercase_usernames' => true, 'home' => '/', 'prefix' => 'api/auth', 'domain' => null, 'middleware' => ['web', 'throttle:auth-general'], 'limiters' => ['login' => 'login', 'two-factor' => 'two-factor'], 'views' => false, 'features' => [Features::registration(), Features::resetPasswords(), Features::emailVerification(), Features::updateProfileInformation(), Features::updatePasswords(), Features::twoFactorAuthentication(['confirm' => true, 'confirmPassword' => true])]];
