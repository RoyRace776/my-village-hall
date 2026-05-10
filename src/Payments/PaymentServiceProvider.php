<?php
namespace MYVH\Payments;

use MYVH\Container\Container;

class PaymentServiceProvider
{
    public function register(Container $container): void {
        $container->singleton(PaymentRepository::class);
        $container->singleton(PaymentService::class);
        $container->singleton(PaymentController::class);
    }
}