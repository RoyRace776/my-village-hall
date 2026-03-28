<?php

class PaymentServiceProvider
{
    public function register($container): void {
        $container->singleton(PaymentRepository::class);
    }
}