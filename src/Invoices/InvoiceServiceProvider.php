<?php

declare(strict_types=1);

namespace MYVH\Invoices;

use MYVH\Container\Container;
use MYVH\Email\EmailService;
use MYVH\Subscriptions\Services\AccountService;
use MYVH\Subscriptions\Services\FeatureService;
use MYVH\Subscriptions\Services\SubscriptionGuard;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class InvoiceServiceProvider
{
    public function register(Container $container): void {
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
        $container->singleton(InvoiceGeneratorService::class, static function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }

            return new InvoiceGeneratorService(
                $container->get(InvoiceService::class),
                $container->get(InvoiceRepository::class),
                $container->get(InvoiceItemRepository::class),
                $container->get(\MYVH\Bookings\BookingRepository::class),
                $container->get(\MYVH\Bookings\BookingService::class),
                $container->get(\MYVH\Bookings\BookingChargeRepository::class),
                $container->get(\MYVH\Bookings\BookingAddonRepository::class),
                $container->get(\MYVH\Addons\AddonRepository::class),
                $container->get(\MYVH\Customers\CustomerRepository::class),
                $container->get(\MYVH\Organisations\OrganisationRepository::class),
                $container->get(\MYVH\Pricing\PricingService::class),
                $container->get(\MYVH\Deposits\DepositService::class),
                $container->get(\MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository::class),
                $container->get(\MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository::class),
                $container->get(SubscriptionGuard::class),
                $container->get(FeatureService::class),
                $container->get(AccountService::class),
                $logger
            );
        });
        $container->singleton(InvoiceAutoSendListener::class, static function ($container) {
            try {
                $logger = $container->get(LoggerInterface::class);
            } catch (\Exception $e) {
                $logger = new NullLogger();
            }
            return new InvoiceAutoSendListener(
                $container->get(InvoiceService::class),
                $container->get(EmailService::class),
                $logger
            );
        });
    }
}