<?php
namespace MYVH\AutoInvoicing;

use MYVH\Container\Container;
use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Email\EmailService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

if (!defined('ABSPATH')) {
    exit;
}

class AutoInvoicingServiceProvider {
    public function register(Container $container): void {
        $container->singleton(SingleBookingAutoInvoiceRuleRepository::class, function ($container) {
            return new SingleBookingAutoInvoiceRuleRepository($container->get(\wpdb::class));
        });
        $container->singleton(SingleBookingAutoInvoiceRuleController::class);
        $container->singleton(SingleBookingAutoInvoicing::class, function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            return new SingleBookingAutoInvoicing(
                $container->get(\MYVH\Invoices\InvoiceGeneratorService::class),
                $container->get(\MYVH\Bookings\BookingService::class),
                $container->get(SingleBookingAutoInvoiceRuleRepository::class),
                $container->get(RecurringBookingAutoInvoiceRuleRepository::class),
                $logger
            );
        });
        $container->singleton(RecurringBookingAutoInvoiceRuleRepository::class, function ($container) {
            return new RecurringBookingAutoInvoiceRuleRepository($container->get(\wpdb::class));
        });
        $container->singleton(RecurringBookingAutoInvoiceRuleController::class);
        $container->singleton(RecurringBookingAutoInvoicing::class, function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            return new RecurringBookingAutoInvoicing(
                $container->get(\MYVH\Invoices\InvoiceGeneratorService::class),
                $container->get(\MYVH\Bookings\BookingService::class),
                $container->get(RecurringBookingAutoInvoiceRuleRepository::class),
                $logger
            );
        });
        $container->singleton(AutoInvoice::class);
        $container->singleton(AutoInvoicingAjaxController::class);
        $container->singleton(AutoInvoicingOvernightJob::class);
        $container->singleton(OvernightBatchRunner::class, function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            return new OvernightBatchRunner(
                [ $container->get(AutoInvoicingOvernightJob::class) ],
                $container->get(EmailService::class),
                $logger
            );
        });
    }
}