<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Availability\AvailabilityService;
use MYVH\Addons\AddonRequestValidator;
use MYVH\Addons\AddonService;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Organisations\OrganisationService;
use MYVH\Organisations\OrganisationTypeService;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\PortalController;
use MYVH\Pricing\RoomRateRequestValidator;
use MYVH\Pricing\RoomRateService;
use MYVH\Rooms\RoomRequestValidator;
use MYVH\Rooms\RoomService;
use MYVH\Tests\Unit\UnitTestCase;
use MYVH\Venues\VenueRequestValidator;
use MYVH\Venues\VenueService;

class PortalControllerTest extends UnitTestCase {
    private $addon_service;
    private $addon_request_validator;
    private $booking_service;
    private $customer_service;
    private $organisation_service;
    private $organisation_type_service;
    private $client_admin_service;
    private $invoice_generator_service;
    private $invoice_service;
    private $payment_service;
    private $room_service;
    private $room_request_validator;
    private $room_rate_service;
    private $room_rate_request_validator;
    private $venue_service;
    private $venue_request_validator;
    private PortalController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->addon_service = $this->mock(AddonService::class);
        $this->addon_request_validator = $this->mock(AddonRequestValidator::class);
        $this->booking_service = $this->mock(BookingService::class);
        $this->customer_service = $this->mock(CustomerService::class);
        $this->organisation_service = $this->mock(OrganisationService::class);
        $this->organisation_type_service = $this->mock(OrganisationTypeService::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);
        $this->invoice_generator_service = $this->mock(InvoiceGeneratorService::class);
        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->payment_service = $this->mock(PaymentService::class);
        $this->room_service = $this->mock(RoomService::class);
        $this->room_request_validator = $this->mock(RoomRequestValidator::class);
        $this->room_rate_service = $this->mock(RoomRateService::class);
        $this->room_rate_request_validator = $this->mock(RoomRateRequestValidator::class);
        $this->venue_service = $this->mock(VenueService::class);
        $this->venue_request_validator = $this->mock(VenueRequestValidator::class);

        $this->controller = new PortalController(
            $this->addon_service,
            $this->addon_request_validator,
            $this->booking_service,
            $this->customer_service,
            $this->organisation_service,
            $this->organisation_type_service,
            $this->client_admin_service,
            $this->invoice_generator_service,
            $this->invoice_service,
            $this->payment_service,
            $this->room_service,
            $this->room_request_validator,
            $this->room_rate_service,
            $this->room_rate_request_validator,
            $this->venue_service,
            $this->venue_request_validator
        );

        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'get_current_user_id' => 77,
            'get_current_blog_id' => 9,
            'wp_unslash' => fn($value) => $value,
            'sanitize_email' => fn($value) => (string) $value,
            'esc_attr' => fn($value) => (string) $value,
            'esc_html' => fn($value) => (string) $value,
            'esc_url_raw' => fn($value) => (string) $value,
            'is_wp_error' => fn($value) => $value instanceof \WP_Error,
        ]);

        Functions\when('wp_send_json_success')->alias(function ($data = null, $status_code = null) {
            throw new JsonResponseException(true, $data, (int) ($status_code ?? 200));
        });

        Functions\when('wp_send_json_error')->alias(function ($data = null, $status_code = null) {
            throw new JsonResponseException(false, $data, (int) ($status_code ?? 400));
        });
    }

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];
        unset($GLOBALS['myvh_container']);

        parent::tearDown();
    }

    /** @test */
    public function client_admin_can_set_organisation_type_when_creating_an_organisation(): void {
        $_POST = [
            'name' => 'Neighbourhood Forum',
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234 567890',
            'organisation_type_id' => 12,
        ];

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 33]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->organisation_service->shouldReceive('save')
            ->once()
            ->withArgs(function (array $payload, bool $allow_type_changes): bool {
                return $allow_type_changes === true
                    && (int) ($payload['organisation_type_id'] ?? 0) === 12
                    && $payload['name'] === 'Neighbourhood Forum';
            })
            ->andReturn(55);

        $this->organisation_service->shouldReceive('add_member')
            ->once()
            ->with(55, 33, true)
            ->andReturn(101);

        $response = $this->capture_json_response(function (): void {
            $this->controller->add_organisation();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Organisation created', $response->data['message']);
        $this->assertSame(55, $response->data['organisation_id']);
    }

    /** @test */
    public function non_client_admin_cannot_set_organisation_type_when_creating_an_organisation(): void {
        $_POST = [
            'name' => 'Neighbourhood Forum',
            'contact_email' => 'hello@example.org',
            'contact_phone' => '01234 567890',
            'organisation_type_id' => 12,
        ];

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => ['Id' => 33]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(false);

        $this->organisation_service->shouldReceive('save')
            ->once()
            ->withArgs(function (array $payload, bool $allow_type_changes): bool {
                return $allow_type_changes === false
                    && !array_key_exists('organisation_type_id', $payload)
                    && $payload['name'] === 'Neighbourhood Forum';
            })
            ->andReturn(55);

        $this->organisation_service->shouldReceive('add_member')
            ->once()
            ->with(55, 33, true)
            ->andReturn(101);

        $response = $this->capture_json_response(function (): void {
            $this->controller->add_organisation();
        });

        $this->assertTrue($response->success);
        $this->assertSame(55, $response->data['organisation_id']);
    }

    /** @test */
    public function client_admin_can_update_organisation_type_without_overwriting_existing_details(): void {
        $_POST = [
            'organisation_id' => 15,
            'organisation_type_id' => 4,
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->organisation_service->shouldReceive('get_by_id')
            ->once()
            ->with(15)
            ->andReturnUsing(static fn(): array => [
                'Id' => 15,
                'Name' => 'Village Players',
                'ContactEmail' => 'contact@example.org',
                'ContactPhone' => '01999 123456',
                'WebsiteUrl' => 'https://players.example.org',
                'OrganisationTypeId' => 2,
                'InvoiceOrganisationBookings' => 1,
                'BillingContactName' => 'Accounts Team',
                'BillingEmail' => 'billing@example.org',
                'BillingAddressLine1' => '1 High Street',
                'BillingAddressLine2' => 'Suite 4',
                'BillingTownCity' => 'Exampletown',
                'BillingPostcode' => 'AB1 2CD',
                'BillingReference' => 'PO-42',
                'IsActive' => 1,
                'IsDefault' => 0,
                'DefaultPublic' => 1,
            ]);

        $this->organisation_service->shouldReceive('save')
            ->once()
            ->withArgs(function (array $payload, bool $allow_type_changes): bool {
                return $allow_type_changes === true
                    && $payload['organisation_id'] === 15
                    && $payload['organisation_type_id'] === 4
                    && $payload['name'] === 'Village Players'
                    && $payload['billing_email'] === 'billing@example.org'
                    && $payload['invoice_organisation_bookings'] === 1
                    && $payload['default_public'] === 1;
            })
            ->andReturn(true);

        $response = $this->capture_json_response(function (): void {
            $this->controller->save_organisation_type_assignment();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Organisation type updated', $response->data['message']);
    }

    /** @test */
    public function client_admin_can_create_payment_in_portal(): void {
        $_POST = [
            'invoice_id' => 14,
            'payment_amount' => '25.00',
            'payment_method' => 'card',
            'payment_date' => '2026-04-09',
            'redirect_route' => 'invoice-view?invoice_id=14',
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->payment_service->shouldReceive('create')
            ->once()
            ->with($_POST)
            ->andReturn(88);

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(14)
            ->andReturn([
                'Id' => 14,
                'Status' => 'part-paid',
                'AmountPaid' => 25,
                'AmountDue' => 75,
            ]);

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->with('part-paid')
            ->andReturn('Part Paid');

        $response = $this->capture_json_response(function (): void {
            $this->controller->create_payment();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Payment saved.', $response->data['message']);
        $this->assertSame('invoice-view?invoice_id=14', $response->data['redirect']);
        $this->assertSame('part-paid', $response->data['status']);
    }

    /** @test */
    public function client_admin_can_delete_payment_in_portal(): void {
        $_POST = [
            'payment_id' => 88,
            'invoice_id' => 14,
            'redirect_route' => 'payments?invoice_id=14',
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->payment_service->shouldReceive('delete')
            ->once()
            ->with(88)
            ->andReturn(true);

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(14)
            ->andReturn([
                'Id' => 14,
                'Status' => 'sent',
                'AmountPaid' => 0,
                'AmountDue' => 100,
            ]);

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->with('sent')
            ->andReturn('Sent');

        $response = $this->capture_json_response(function (): void {
            $this->controller->delete_payment();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Payment deleted.', $response->data['message']);
        $this->assertSame('payments?invoice_id=14', $response->data['redirect']);
        $this->assertSame('sent', $response->data['status']);
    }

    /** @test */
    public function client_admin_can_create_venue_in_portal(): void {
        $_POST = [
            'name' => 'Main Hall',
            'short_name' => 'Hall',
            'post_code' => 'AB1 2CD',
            'address_line1' => '1 High Street',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->venue_request_validator->shouldReceive('validate')
            ->once()
            ->withArgs(function (array $payload): bool {
                return $payload['name'] === 'Main Hall'
                    && $payload['short_name'] === 'Hall'
                    && $payload['opening_time'] === '09:00'
                    && $payload['closing_time'] === '17:00';
            })
            ->andReturn(true);

        $this->venue_service->shouldReceive('save')
            ->once()
            ->withArgs(function (array $payload): bool {
                return empty($payload['venue_id'])
                    && $payload['name'] === 'Main Hall';
            })
            ->andReturn(41);

        $response = $this->capture_json_response(function (): void {
            $this->controller->save_venue();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Venue created', $response->data['message']);
        $this->assertSame(41, $response->data['venue_id']);
        $this->assertSame('venues', $response->data['redirect']);
    }

    /** @test */
    public function client_admin_can_delete_venue_in_portal(): void {
        $_POST = [
            'venue_id' => 41,
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->venue_service->shouldReceive('delete')
            ->once()
            ->with(41)
            ->andReturn(true);

        $response = $this->capture_json_response(function (): void {
            $this->controller->delete_venue();
        });

        $this->assertTrue($response->success);
        $this->assertSame('Venue deleted', $response->data['message']);
    }

    /** @test */
    public function load_page_renders_venues_list_with_room_counts(): void {
        $_GET = [
            'page' => 'venues',
        ];

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(77)
            ->andReturn([]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->venue_service->shouldReceive('get_all')
            ->once()
            ->andReturn([
                [
                    'Id' => 5,
                    'Name' => 'Main Hall',
                    'ShortName' => 'Hall',
                    'PostCode' => 'AB1 2CD',
                    'AddressLine1' => '1 High Street',
                    'OpeningTime' => '09:00:00',
                    'ClosingTime' => '17:00:00',
                ],
            ]);

        $this->room_service->shouldReceive('get_all_with_venues')
            ->once()
            ->andReturn([
                ['Id' => 11, 'VenueId' => 5, 'Name' => 'Large Room'],
                ['Id' => 12, 'VenueId' => 5, 'Name' => 'Small Room'],
            ]);

        $output = $this->capture_page_output(function (): void {
            $this->controller->load_page();
        });

        $this->assertStringContainsString('All Venues', $output);
        $this->assertStringContainsString('Main Hall', $output);
        $this->assertStringContainsString('Delete rooms first.', $output);
        $this->assertStringContainsString('>2<', $output);
    }

    /** @test */
    public function load_page_renders_venue_add_form(): void {
        $_GET = [
            'page' => 'venue-add',
        ];

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(77)
            ->andReturn([]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $availability_service = $this->mock(AvailabilityService::class);
        $availability_service->shouldReceive('get_time_options')
            ->twice()
            ->andReturn('<option value="09:00">09:00</option>');

        $GLOBALS['myvh_container'] = new class($availability_service) {
            public function __construct(private $availability_service) {}

            public function get(string $class) {
                return $this->availability_service;
            }
        };

        $output = $this->capture_page_output(function (): void {
            $this->controller->load_page();
        });

        $this->assertStringContainsString('Add Venue', $output);
        $this->assertStringContainsString('Create Venue', $output);
        $this->assertStringContainsString('myvh_portal_save_venue', $output);
    }

    /** @test */
    public function load_page_renders_venue_edit_form(): void {
        $_GET = [
            'page' => 'venue-edit',
            'id' => 5,
        ];

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(77)
            ->andReturn([]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(77, 9)
            ->andReturn(true);

        $this->venue_service->shouldReceive('get')
            ->once()
            ->with(5)
            ->andReturn([
                'Id' => 5,
                'Name' => 'Main Hall',
                'ShortName' => 'Hall',
                'PostCode' => 'AB1 2CD',
                'AddressLine1' => '1 High Street',
                'OpeningTime' => '09:00',
                'ClosingTime' => '17:00',
            ]);

        $availability_service = $this->mock(AvailabilityService::class);
        $availability_service->shouldReceive('get_time_options')
            ->twice()
            ->andReturn('<option value="09:00">09:00</option>');

        $GLOBALS['myvh_container'] = new class($availability_service) {
            public function __construct(private $availability_service) {}

            public function get(string $class) {
                return $this->availability_service;
            }
        };

        $output = $this->capture_page_output(function (): void {
            $this->controller->load_page();
        });

        $this->assertStringContainsString('Edit Venue', $output);
        $this->assertStringContainsString('Update Venue', $output);
        $this->assertStringContainsString('value="Main Hall"', $output);
    }

    private function capture_page_output(callable $callback): string {
        Functions\when('wp_die')->alias(function (): void {
            throw new PageLoadCompleteException();
        });

        ob_start();

        try {
            $callback();
        } catch (PageLoadCompleteException) {
            return (string) ob_get_clean();
        } catch (\Throwable $throwable) {
            ob_end_clean();
            throw $throwable;
        }

        ob_end_clean();
        $this->fail('Expected page rendering to terminate via wp_die().');
    }

    private function capture_json_response(callable $callback): JsonResponseException {
        try {
            $callback();
        } catch (JsonResponseException $response) {
            return $response;
        }

        $this->fail('Expected a JSON response to be sent.');
    }
}

class JsonResponseException extends \RuntimeException {
    public function __construct(
        public bool $success,
        public mixed $data,
        public int $statusCode
    ) {
        parent::__construct('JSON response intercepted');
    }
}

class PageLoadCompleteException extends \RuntimeException {
}