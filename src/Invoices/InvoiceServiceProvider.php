<?php

declare(strict_types=1);

namespace MYVH\Invoices;

class InvoiceServiceProvider
{
    public function register($container): void {
        $container->singleton(InvoiceRepository::class);
        $container->singleton(InvoiceItemRepository::class);
        $container->singleton(PdfGenerator::class);
        $container->singleton(InvoiceFileStorage::class);
        $container->singleton(InvoicePdfRenderer::class, static function () {
            // Templates directory is resolved once at container build time.
            return new InvoicePdfRenderer(
                __DIR__ . '/../../templates/Invoices/'
            );
        });
        $container->singleton(InvoiceService::class);
        $container->singleton(InvoiceRequestValidator::class);
        $container->singleton(InvoiceController::class);
        $container->singleton(InvoiceGeneratorService::class);
    }
}