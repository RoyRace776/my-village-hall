<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Portal\Ajax\PortalBillingAjaxController;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class PortalBillingAjaxControllerTest extends UnitTestCase {
    private $booking_service;
    private $customer_service;
    private $invoice_generator_service;
    private $invoice_service;
    private $payment_service;
    private $client_admin_service;
    private PortalBillingAjaxController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_service = $this->mock(BookingService::class);
        $this->customer_service = $this->mock(CustomerService::class);
        $this->invoice_generator_service = $this->mock(InvoiceGeneratorService::class);
        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->payment_service = $this->mock(PaymentService::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);

        $this->controller = new PortalBillingAjaxController(
            $this->booking_service,
            $this->customer_service,
            $this->invoice_generator_service,
            $this->invoice_service,
            $this->payment_service,
            $this->client_admin_service
        );

        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'wp_unslash' => static fn($value) => $value,
            'is_wp_error' => false,
            'sanitize_email' => static fn($email) => $email,
            'is_email' => static fn($email) => !empty($email) && strpos($email, '@') !== false,
            'get_current_user_id' => 1,
            'get_current_blog_id' => 1,
        ]);

        Functions\when('wp_send_json_success')->alias(function ($data = null, $status_code = null) {
            throw new PortalJsonResponseException(true, $data, (int) ($status_code ?? 200));
        });

        Functions\when('wp_send_json_error')->alias(function ($data = null, $status_code = null) {
            throw new PortalJsonResponseException(false, $data, (int) ($status_code ?? 400));
        });

        // Mock PortalAuth::require_client_admin by ensuring can_administer_blog returns true
        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->byDefault()
            ->andReturn(true);
    }

    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    /** @test */
    public function email_invoice_requires_valid_invoice_id(): void {
        $_POST = ['invoice_id' => 0];

        $response = $this->capture_json_response(function (): void {
            $this->controller->email_invoice();
        });

        $this->assertFalse($response->success);
        $this->assertSame(400, $response->statusCode);
    }

    /** @test */
    public function email_invoice_fails_when_invoice_not_found(): void {
        $_POST = ['invoice_id' => 999];

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(999)
            ->andReturn(null);

        $response = $this->capture_json_response(function (): void {
            $this->controller->email_invoice();
        });

        $this->assertFalse($response->success);
        $this->assertSame(404, $response->statusCode);
    }

    /** @test */
    public function email_invoice_fails_when_no_valid_recipient(): void {
        $_POST = ['invoice_id' => 42];

        $invoice = [
            'Id' => 42,
            'BillingEmail' => '',
            'CustomerEmail' => 'not-an-email',
            'BillingName' => 'Test',
        ];

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(42)
            ->andReturn($invoice);

        $response = $this->capture_json_response(function (): void {
            $this->controller->email_invoice();
        });

        $this->assertFalse($response->success);
        $this->assertSame(400, $response->statusCode);
    }

    private function capture_json_response(callable $callback): PortalJsonResponseException {
        try {
            $callback();
        } catch (PortalJsonResponseException $response) {
            return $response;
        }

        $this->fail('Expected JSON response to be sent.');
    }
}

class PortalJsonResponseException extends \RuntimeException {
    public bool $success;
    public $data;
    public int $statusCode;

    public function __construct(bool $success, $data, int $statusCode) {
        parent::__construct('JSON response intercepted');

        $this->success = $success;
        $this->data = $data;
        $this->statusCode = $statusCode;
    }
}
