<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use MYVH\Email\EmailService;
use MYVH\Invoices\InvoiceAutoSendListener;
use MYVH\Invoices\InvoiceService;
use MYVH\Tests\Unit\UnitTestCase;

class InvoiceAutoSendListenerTest extends UnitTestCase {
    private $invoice_service;
    private $email_service;
    private InvoiceAutoSendListener $listener;
    private bool $auto_send_enabled = true;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            '__' => fn($value) => $value,
            'sanitize_email' => fn($value) => (string) $value,
            'is_email' => fn($value) => is_string($value) && strpos($value, '@') !== false,
            'myvh_setting' => true,
            'is_wp_error' => fn($value) => $value instanceof \WP_Error,
            'MYVH\\Invoices\\sanitize_email' => fn($value) => (string) $value,
            'MYVH\\Invoices\\is_email' => fn($value) => is_string($value) && strpos($value, '@') !== false,
            'MYVH\\Invoices\\myvh_setting' => fn($key, $default = null) => $this->auto_send_enabled,
            'MYVH\\Invoices\\is_wp_error' => fn($value) => $value instanceof \WP_Error,
        ]);

        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->email_service = $this->mock(EmailService::class);

        $this->listener = new InvoiceAutoSendListener(
            $this->invoice_service,
            $this->email_service
        );
    }

    public function tearDown(): void {
        unset($this->listener, $this->invoice_service, $this->email_service);
        parent::tearDown();
    }

    /** @test */
    public function it_does_not_send_email_when_auto_send_is_disabled(): void {
        $this->auto_send_enabled = false;

        $this->invoice_service->shouldNotReceive('get_detail');
        $this->email_service->shouldNotReceive('send');

        $this->listener->handle_invoice_generated(25, [], []);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_sends_invoice_email_with_pdf_attachment_and_marks_sent(): void {
        $this->auto_send_enabled = true;

        $invoice = [
            'Id' => 32,
            'InvoiceNumber' => 'INV-000032',
            'BillingEmail' => '',
            'CustomerEmail' => 'fallback@example.com',
            'CustomerName' => 'Sam Booker',
            'TotalAmount' => '125.50',
            'DueDate' => '2026-05-20',
            'BookingCount' => 2,
        ];

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(32)
            ->andReturn($invoice);

        $this->invoice_service->shouldReceive('generate_pdf')
            ->once()
            ->with(32)
            ->andReturn('/tmp/invoice-32.pdf');

        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturn([
                'logo_url' => 'https://example.test/logo.png',
                'site_name' => 'Village Hall',
                'site_url' => 'https://example.test',
            ]);

        $this->invoice_service->shouldReceive('get_invoice_pdf_url')
            ->once()
            ->with(32)
            ->andReturn('https://example.test/uploads/invoice-32.pdf');

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->with('sent')
            ->andReturn('Sent');

        $this->email_service->shouldReceive('send')
            ->once()
            ->withArgs(function (array $payload): bool {
                return ($payload['to'] ?? '') === 'fallback@example.com'
                    && ($payload['template'] ?? '') === 'invoice'
                    && ($payload['attachments'][0] ?? '') === '/tmp/invoice-32.pdf'
                    && ($payload['template_vars']['invoice_ref'] ?? '') === 'INV-000032'
                    && ($payload['template_vars']['invoice_status'] ?? '') === 'Sent';
            })
            ->andReturn(true);

        $this->invoice_service->shouldReceive('update_status')
            ->once()
            ->with(32, 'sent')
            ->andReturn(true);

        $this->listener->handle_invoice_generated(32, [], []);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_does_not_send_when_recipient_cannot_be_resolved(): void {
        $this->auto_send_enabled = true;

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(33)
            ->andReturn([
                'Id' => 33,
                'BillingEmail' => '',
                'CustomerEmail' => '',
            ]);

        $this->invoice_service->shouldNotReceive('generate_pdf');
        $this->email_service->shouldNotReceive('send');
        $this->invoice_service->shouldNotReceive('update_status');

        $this->listener->handle_invoice_generated(33, [], []);

        $this->assertTrue(true);
    }

    /** @test */
    public function it_keeps_invoice_in_draft_when_email_send_fails(): void {
        $this->auto_send_enabled = true;

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(34)
            ->andReturn([
                'Id' => 34,
                'InvoiceNumber' => 'INV-000034',
                'BillingEmail' => 'billing@example.com',
                'CustomerEmail' => 'fallback@example.com',
                'TotalAmount' => '90.00',
                'DueDate' => '2026-05-25',
                'BookingCount' => 1,
            ]);

        $this->invoice_service->shouldReceive('generate_pdf')
            ->once()
            ->with(34)
            ->andReturn('/tmp/invoice-34.pdf');

        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturn([
                'logo_url' => '',
                'site_name' => 'Village Hall',
                'site_url' => 'https://example.test',
            ]);

        $this->invoice_service->shouldReceive('get_invoice_pdf_url')
            ->once()
            ->with(34)
            ->andReturn('https://example.test/uploads/invoice-34.pdf');

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->with('sent')
            ->andReturn('Sent');

        $this->email_service->shouldReceive('send')
            ->once()
            ->andReturn(false);

        $this->invoice_service->shouldNotReceive('update_status');

        $this->listener->handle_invoice_generated(34, [], []);

        $this->assertTrue(true);
    }
}