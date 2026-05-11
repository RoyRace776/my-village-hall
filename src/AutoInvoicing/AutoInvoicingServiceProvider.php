<?php
namespace MYVH\AutoInvoicing;
use MYVH\Container\Container;

if (!defined('ABSPATH')) {
    exit;
}

class AutoInvoicingServiceProvider {
    public function register(Container $container): void {
        $container->singleton(SingleBookingAutoInvoiceRuleRepository::class, function ($container) {
            return new SingleBookingAutoInvoiceRuleRepository($container->get(\wpdb::class));
        });
        $container->singleton(SingleBookingAutoInvoiceRuleController::class);
        $container->singleton(SingleBookingAutoInvoicing::class);
        $container->singleton(RecurringBookingAutoInvoiceRuleRepository::class, function ($container) {
            return new RecurringBookingAutoInvoiceRuleRepository($container->get(\wpdb::class));
        });
        $container->singleton(RecurringBookingAutoInvoiceRuleController::class);
        $container->singleton(RecurringBookingAutoInvoicing::class);
        $container->singleton(AutoInvoice::class);
        $container->singleton(AutoInvoicingAjaxController::class);
    }
}