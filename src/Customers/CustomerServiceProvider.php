<?php
namespace MYVH\Customers;

if (!defined('ABSPATH')) exit;

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
