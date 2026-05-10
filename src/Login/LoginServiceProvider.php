<?php
namespace MYVH\Login;

use MYVH\Container\Container;

if (!defined('ABSPATH')) {
    exit;
}

class LoginServiceProvider {
    public function register(Container $container): void {
        $container->singleton(PasswordValidator::class);
        $container->singleton(CustomerEmailVerificationService::class);
        $container->singleton(LoginHandler::class);
        $container->singleton(PasswordResetHandler::class);
    }
}