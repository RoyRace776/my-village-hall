<?php
namespace MYVH\AutoInvoicing;

if (!defined('ABSPATH')) {
    exit;
}

class AutoInvoicingServiceProvider {
    public function register($container): void {
        $container->singleton(SingleBookingAutoInvoiceRuleRepository::class, function ($container) {
            return new SingleBookingAutoInvoiceRuleRepository($container->get(\wpdb::class));
        });
        $container->singleton(SingleBookingAutoInvoiceRuleController::class);
        $container->singleton(SingleBookingAutoInvoicing::class);
        $container->singleton(RecurringBookingAutoInvoicing::class);
        $container->singleton(AutoInvoice::class);
        $container->singleton(AutoInvoicingAjaxController::class);
    }
}