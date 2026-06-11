<?php
namespace MYVH\Core\Support;

use MYVH\Venues\VenueServiceProvider;
use MYVH\Rooms\RoomServiceProvider;
use MYVH\Customers\CustomerServiceProvider;
use MYVH\Organisations\OrganisationServiceProvider;
use MYVH\Pricing\PricingServiceProvider;
use MYVH\Addons\AddonServiceProvider;
use MYVH\Bookings\BookingServiceProvider;
use MYVH\Availability\AvailabilityServiceProvider;
use MYVH\Invoices\InvoiceServiceProvider;
use MYVH\AutoInvoicing\AutoInvoicingServiceProvider;
use MYVH\Payments\PaymentServiceProvider;
use MYVH\Calendar\CalendarServiceProvider;
use MYVH\Login\LoginServiceProvider;
use MYVH\Portal\PortalServiceProvider;
use MYVH\Network\NetworkServiceProvider;
use MYVH\Reports\ReportServiceProvider;
use MYVH\Core\Logging\LoggerFactory;
use MYVH\Email\Mailer\MailerService;
use MYVH\Email\Mailer\MailTransport;
use MYVH\Email\Mailer\MailTransportFactory;
use MYVH\Email\Mailer\WpMailTransport;
use MYVH\Email\Mailer\SmtpTransport;
use MYVH\Email\Mailer\ApiTransport;
use MYVH\Settings\EmailServiceSettings;
use MYVH\Subscriptions\Repositories\SettingsRepository;
use Psr\Log\LoggerInterface;

use wpdb;

/**
 * DI Container setup
 *
 * Builds the Container instance, registers all service providers,
 * and returns the configured container so the caller can assign it to
 * $myvh_container.
 *
 * Service providers are responsible for binding concrete implementations
 * to the container (repositories, services, controllers).
 *
 * Usage (from my-village-hall.php):
 *   $myvh_container = require MYVH_PLUGIN_DIR . 'bootstrap/myvh-container.php';
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

global $wpdb;
global $myvh_container;

use MYVH\Container\Container;

$myvh_container = new Container();


// Register wpdb so it can be injected like any other dependency
$myvh_container->singleton( wpdb::class, function () {
    global $wpdb;
    return $wpdb;
} );

$myvh_container->singleton( EmailServiceSettings::class );

$myvh_container->singleton( LoggerInterface::class, static function () {
    return LoggerFactory::get();
} );

$myvh_container->singleton( WpMailTransport::class );
$myvh_container->singleton( SmtpTransport::class );
$myvh_container->singleton( ApiTransport::class, static function ($container) {
    return new ApiTransport(
        $container->get(EmailServiceSettings::class),
        $container->get(LoggerInterface::class),
        $container->get(WpMailTransport::class)
    );
} );

$myvh_container->singleton( MailTransport::class, static function ($container) {
    $factory = new MailTransportFactory(
        $container->get(EmailServiceSettings::class),
        $container->get(WpMailTransport::class),
        $container->get(SmtpTransport::class),
        $container->get(ApiTransport::class)
    );

    return $factory->create();
} );

$myvh_container->singleton( MailerService::class, static function ($container) {
    return new MailerService(
        $container->get(EmailServiceSettings::class),
        $container->get(MailTransport::class),
        $container->get(LoggerInterface::class)
    );
} );

// ── Register providers with the container ─────────────────────────────────────
// Order follows the dependency graph (no provider should depend on a later one).

$providers = [
    VenueServiceProvider::class,
    RoomServiceProvider::class,
    CustomerServiceProvider::class,
    OrganisationServiceProvider::class,
    PricingServiceProvider::class,
    AddonServiceProvider::class,
    BookingServiceProvider::class,
    AvailabilityServiceProvider::class,
    InvoiceServiceProvider::class,
    AutoInvoicingServiceProvider::class,
    PaymentServiceProvider::class,
    CalendarServiceProvider::class,
    LoginServiceProvider::class,
    ReportServiceProvider::class,
    PortalServiceProvider::class,
    NetworkServiceProvider::class,
];

foreach ( $providers as $provider ) {
    ( new $provider() )->register( $myvh_container );
}

return $myvh_container;
