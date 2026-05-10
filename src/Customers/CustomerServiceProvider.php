<?php
namespace MYVH\Customers;

use MYVH\Container\Container;

if (!defined('ABSPATH')) exit;

class CustomerServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(CustomerRepository::class);
        $container->singleton(CustomerService::class);
        $container->singleton(CustomerRequestValidator::class);
        $container->singleton(CustomerUserSync::class);
        $container->singleton(CustomerController::class);
    }
}
