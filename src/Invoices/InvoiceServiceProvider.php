<?php
namespace MYVH\Invoices;

class InvoiceServiceProvider
{
    public function register($container): void {
        $container->singleton(InvoiceRepository::class);
        $container->singleton(InvoiceItemRepository::class);
        $container->singleton(InvoiceService::class);
        $container->singleton(InvoiceRequestValidator::class);
        $container->singleton(InvoiceController::class);
        $container->singleton(InvoiceGeneratorService::class);
    }
}