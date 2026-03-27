<?php

require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-save-customer-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/customers/class-myvh-customer-request-validator.php';

class CustomerServiceProvider
{
    public function register($container): void {
           $container->singleton(CustomerRepository::class);
           $container->singleton(CustomerService::class);
           $container->singleton(CustomerRequestValidator::class);
           $container->singleton(CustomerUserSync::class);
           $container->singleton(CustomerController::class);
    }
}