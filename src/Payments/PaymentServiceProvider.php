<?php
namespace MYVH\Payments;

class PaymentServiceProvider
{
    public function register($container): void {
        $container->singleton(PaymentRepository::class);
        $container->singleton(PaymentService::class);
        $container->singleton(PaymentController::class);
    }
}