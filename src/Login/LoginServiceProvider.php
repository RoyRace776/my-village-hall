<?php
namespace MYVH\Login;

if (!defined('ABSPATH')) {
    exit;
}

class LoginServiceProvider {
    public function register($container): void {
        $container->singleton(PasswordValidator::class);
        $container->singleton(LoginHandler::class);
        $container->singleton(PasswordResetHandler::class);
    }
}