<?php

namespace MYVH\Tests\Unit\Invoices;

use Brain\Monkey\Functions;
use MYVH\Email\EmailService;
use MYVH\Invoices\InvoiceController;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceRequestValidator;
use MYVH\Invoices\InvoiceService;
use MYVH\Tests\Unit\UnitTestCase;

class InvoiceControllerTest extends UnitTestCase {
    private $invoice_service;
    private $generator_service;
    private $request_validator;
    private $email_service;
    private InvoiceController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->generator_service = $this->mock(InvoiceGeneratorService::class);
        $this->request_validator = $this->mock(InvoiceRequestValidator::class);
        $this->email_service = $this->mock(EmailService::class);

        $this->controller = new InvoiceController(
            $this->invoice_service,
            $this->generator_service,
            $this->request_validator,
            $this->email_service
        );

        Functions\stubs([
            '__' => static fn($text) => $text,
            'current_user_can' => true,
            'check_admin_referer' => true,
            'sanitize_key' => static fn($value) => (string) $value,
            'sanitize_text_field' => static fn($value) => (string) $value,
            'sanitize_email' => static fn($value) => (string) $value,
            'is_email' => static fn($value) => is_string($value) && strpos($value, '@') !== false,
            'is_wp_error' => static fn($value) => $value instanceof \WP_Error,
            'admin_url' => static fn($path = '') => 'https://example.test/wp-admin/' . ltrim((string) $path, '/'),
            'add_query_arg' => static function (array $args, string $url): string {
                return $url . '?' . http_build_query($args);
            },
        ]);

        Functions\when('wp_safe_redirect')->alias(function (string $url): void {
            throw new InvoiceRedirectException($url);
        });

        Functions\when('wp_die')->alias(function ($message = ''): void {
            throw new \RuntimeException((string) $message);
        });
    }

    protected function tearDown(): void {
        $_REQUEST = [];
        parent::tearDown();
    }

    /** @test */
    public function email_invoice_sends_email_marks_sent_and_redirects_with_success_flag(): void {
        $_REQUEST = [
            'id' => 42,
            'redirect_page' => 'myvh-invoices',
        ];

        $invoice = [
            'Id' => 42,
            'InvoiceNumber' => 'INV-000042',
            'BillingEmail' => '',
            'CustomerEmail' => 'customer@example.com',
            'CustomerName' => 'Alex Booker',
            'BillingName' => 'Alex Booker',
            'BillingOrganisationName' => 'Village Club',
            'TotalAmount' => '120.00',
            'DueDate' => '2026-06-01',
            'BookingCount' => 1,
            'BookingsSummary' => [
                ['Description' => 'Main Hall Booking'],
            ],
        ];

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(42)
            ->andReturn($invoice);

        $this->invoice_service->shouldReceive('generate_pdf')
            ->once()
            ->with(42)
            ->andReturn('/tmp/invoice-42.pdf');

        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturn([
                'logo_url' => 'https://example.test/logo.png',
                'site_name' => 'Village Hall',
                'site_url' => 'https://example.test',
            ]);

        $this->invoice_service->shouldReceive('get_invoice_pdf_url')
            ->once()
            ->with(42)
            ->andReturn('https://example.test/uploads/invoice-42.pdf');

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->with('sent')
            ->andReturn('Sent');

        $this->email_service->shouldReceive('send')
            ->once()
            ->withArgs(function (array $payload): bool {
                return ($payload['to'] ?? '') === 'customer@example.com'
                    && ($payload['template'] ?? '') === 'invoice'
                    && ($payload['attachments'][0] ?? '') === '/tmp/invoice-42.pdf'
                    && ($payload['template_vars']['invoice_ref'] ?? '') === 'INV-000042';
            })
            ->andReturn(true);

        $this->invoice_service->shouldReceive('update_status')
            ->once()
            ->with(42, 'sent')
            ->andReturn(true);

        try {
            $this->controller->email_invoice();
            $this->fail('Expected redirect to interrupt flow.');
        } catch (InvoiceRedirectException $redirect) {
            $this->assertStringContainsString('page=myvh-invoices', $redirect->url);
            $this->assertStringContainsString('emailed=1', $redirect->url);
        }
    }

    /** @test */
    public function email_invoice_redirects_with_error_when_recipient_missing(): void {
        $_REQUEST = [
            'id' => 43,
            'redirect_page' => 'myvh-invoices',
        ];

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(43)
            ->andReturn([
                'Id' => 43,
                'BillingEmail' => '',
                'CustomerEmail' => '',
            ]);

        $this->invoice_service->shouldNotReceive('generate_pdf');
        $this->email_service->shouldNotReceive('send');
        $this->invoice_service->shouldNotReceive('update_status');

        try {
            $this->controller->email_invoice();
            $this->fail('Expected redirect to interrupt flow.');
        } catch (InvoiceRedirectException $redirect) {
            $this->assertStringContainsString('page=myvh-invoices', $redirect->url);
            $this->assertStringContainsString('error=No+valid+recipient+email+found+for+this+invoice.', $redirect->url);
        }
    }
}

class InvoiceRedirectException extends \RuntimeException {
    public string $url;

    public function __construct(string $url) {
        parent::__construct('Redirect intercepted');
        $this->url = $url;
    }
}
