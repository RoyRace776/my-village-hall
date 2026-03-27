<?php

require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-save-invoice-request.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-request-validator.php';
require_once MYVH_PLUGIN_DIR . 'modules/invoices/class-myvh-invoice-generator-service.php';

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