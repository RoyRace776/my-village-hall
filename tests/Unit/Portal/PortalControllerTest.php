<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
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
            $this->venue_service
        );

        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'get_current_user_id' => 77,
            'get_current_blog_id' => 9,
            'wp_unslash' => fn($value) => $value,
            'sanitize_email' => fn($value) => (string) $value,
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
        $_POST = [];

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