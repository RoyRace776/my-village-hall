<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Portal\Ajax\PortalBillingPageRenderer;
use MYVH\Tests\Unit\UnitTestCase;

class PortalBillingPageRendererTest extends UnitTestCase {
    private BookingService $booking_service;
    private $invoice_service;
    private $payment_service;
    private PortalBillingPageRenderer $renderer;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_url' => static fn($value) => (string) $value,
            'sanitize_key' => static fn($value) => strtolower((string) $value),
            'selected' => static function ($selected, $current = true, $display = true) {
                return $selected == $current ? 'selected="selected"' : '';
            },
            'checked' => static function ($checked, $current = true, $display = true) {
                return $checked == $current ? 'checked="checked"' : '';
            },
            'wp_create_nonce' => static fn($action = '') => 'nonce-token',
            'admin_url' => static fn($path = '') => '/wp-admin/' . ltrim((string) $path, '/'),
            'add_query_arg' => static fn($args, $url = '') => (string) $url,
            'sanitize_text_field' => static fn($value) => (string) $value,
            'wp_unslash' => static fn($value) => $value,
            'current_time' => static function (string $type = 'mysql') {
                return $type === 'timestamp' ? strtotime('2026-05-13 12:00:00') : '2026-05-13';
            },
        ]);

        $this->booking_service = $this->mock(BookingService::class);
        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->payment_service = $this->mock(PaymentService::class);

        $this->renderer = new PortalBillingPageRenderer(
            $this->booking_service,
            $this->invoice_service,
            $this->payment_service
        );
    }

    /** @test */
    public function get_unpaid_invoice_summary_returns_empty_when_customer_id_missing(): void {
        $this->invoice_service->shouldNotReceive('get_for_portal');

        $result = $this->renderer->get_unpaid_invoice_summary([]);

        $this->assertSame([], $result);
    }

    /** @test */
    public function get_unpaid_invoice_summary_filters_statuses_and_limits_results(): void {
        $invoices = [
            ['Id' => 11, 'InvoiceNumber' => 'INV-000011'],
            ['Id' => 12, 'InvoiceNumber' => 'INV-000012'],
            ['Id' => 13, 'InvoiceNumber' => 'INV-000013'],
        ];

        $this->invoice_service->shouldReceive('get_for_portal')
            ->once()
            ->with(55, ['sent', 'part-paid', 'overdue'])
            ->andReturn($invoices);

        $result = $this->renderer->get_unpaid_invoice_summary(['Id' => 55], 2);

        $this->assertCount(2, $result);
        $this->assertSame('INV-000011', $result[0]['InvoiceNumber']);
        $this->assertSame('INV-000012', $result[1]['InvoiceNumber']);
    }

    /** @test */
    public function get_unpaid_invoice_summary_enforces_minimum_limit_of_one(): void {
        $invoices = [
            ['Id' => 11, 'InvoiceNumber' => 'INV-000011'],
            ['Id' => 12, 'InvoiceNumber' => 'INV-000012'],
        ];

        $this->invoice_service->shouldReceive('get_for_portal')
            ->once()
            ->with(77, ['sent', 'part-paid', 'overdue'])
            ->andReturn($invoices);

        $result = $this->renderer->get_unpaid_invoice_summary(['Id' => 77], 0);

        $this->assertCount(1, $result);
        $this->assertSame('INV-000011', $result[0]['InvoiceNumber']);
    }

    /** @test */
    public function render_invoices_applies_default_date_range_and_passes_it_to_the_service(): void {
        $_GET = [];

        $this->invoice_service->shouldReceive('get_valid_statuses')
            ->once()
            ->andReturnUsing(static fn() => ['draft', 'sent']);

        $this->invoice_service->shouldReceive('get_with_customers')
            ->once()
            ->with('2026-04-13', '2026-05-13')
            ->andReturn([]);

        $this->invoice_service->shouldNotReceive('get_for_portal');

        ob_start();
        $this->renderer->render_invoices([], true, false);
        ob_end_clean();

        $this->assertTrue(true);
    }

    /** @test */
    public function render_invoices_normalizes_date_filters_for_customer_portal(): void {
        $_GET = [
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-01',
            'statuses' => 'sent,paid',
        ];

        $this->invoice_service->shouldReceive('get_valid_statuses')
            ->once()
            ->andReturnUsing(static fn() => ['draft', 'sent', 'paid']);

        $this->invoice_service->shouldReceive('get_for_portal')
            ->once()
            ->with(55, ['sent', 'paid'], '2026-03-01', '2026-05-01')
            ->andReturn([]);

        $this->invoice_service->shouldNotReceive('get_with_customers');

        ob_start();
        $this->renderer->render_invoices(['Id' => 55], false, true);
        ob_end_clean();

        $this->assertTrue(true);
    }

    /** @test */
    public function render_invoices_supports_paid_with_deposit_status_filter(): void {
        $_GET = [
            'start_date' => '2026-03-01',
            'end_date' => '2026-05-01',
            'statuses' => 'paid-with-deposit',
        ];

        $this->invoice_service->shouldReceive('get_valid_statuses')
            ->once()
            ->andReturnUsing(static fn() => ['draft', 'sent', 'part-paid', 'paid', 'overdue', 'cancelled']);

        $this->invoice_service->shouldReceive('get_for_portal')
            ->once()
            ->with(55, ['paid'], '2026-03-01', '2026-05-01')
            ->andReturn([
                [
                    'Id' => 201,
                    'InvoiceNumber' => 'INV-DEP-201',
                    'InvoiceDate' => '2026-04-10',
                    'DueDate' => '2026-04-20',
                    'Status' => 'paid',
                    'TotalAmount' => 125,
                    'AmountPaid' => 125,
                    'OrganisationName' => 'Community Group',
                    'BillingName' => 'Alex Booker',
                ],
                [
                    'Id' => 202,
                    'InvoiceNumber' => 'INV-PAID-202',
                    'InvoiceDate' => '2026-04-11',
                    'DueDate' => '2026-04-21',
                    'Status' => 'paid',
                    'TotalAmount' => 100,
                    'AmountPaid' => 100,
                    'OrganisationName' => 'Community Group',
                    'BillingName' => 'Taylor Smith',
                ],
            ]);

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(201)
            ->andReturn([
                'Id' => 201,
                'Items' => [
                    ['ItemType' => 'deposit', 'TotalAmount' => 25],
                ],
            ]);

        $this->invoice_service->shouldReceive('get_detail')
            ->once()
            ->with(202)
            ->andReturn([
                'Id' => 202,
                'Items' => [
                    ['ItemType' => 'charge', 'TotalAmount' => 100],
                ],
            ]);

        $this->invoice_service->shouldReceive('has_deposit_items')
            ->twice()
            ->andReturnUsing(static fn(array $invoice) => (int) ($invoice['Id'] ?? 0) === 201);

        $this->invoice_service->shouldReceive('get_status_label')
            ->once()
            ->andReturn('Paid with deposit');

        $this->invoice_service->shouldNotReceive('get_with_customers');

        ob_start();
        $this->renderer->render_invoices(['Id' => 55], false, true);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('INV-DEP-201', $html);
        $this->assertStringNotContainsString('INV-PAID-202', $html);
        $this->assertStringContainsString('Paid with deposit', $html);
    }

    /** @test */
    public function render_invoices_defaults_client_portal_without_draft_status(): void {
        $_GET = [];

        $this->invoice_service->shouldReceive('get_valid_statuses')
            ->once()
            ->andReturnUsing(static fn() => ['draft', 'sent', 'part-paid', 'paid', 'overdue', 'cancelled']);

        $this->invoice_service->shouldReceive('get_for_portal')
            ->once()
            ->with(55, ['sent', 'part-paid', 'paid', 'overdue', 'cancelled'], '2026-04-13', '2026-05-13')
            ->andReturn([
                [
                    'Id' => 7,
                    'InvoiceNumber' => 'INV-000007',
                    'InvoiceDate' => '2026-05-01',
                    'DueDate' => '2026-05-31',
                    'Status' => 'sent',
                    'TotalAmount' => 120,
                    'AmountPaid' => 0,
                    'OrganisationName' => 'Community Group',
                    'BillingName' => 'Alex Booker',
                ],
            ]);

        $this->invoice_service->shouldNotReceive('get_with_customers');

        ob_start();
        $this->renderer->render_invoices(['Id' => 55], false, true);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('INV-000007', $html);
        $this->assertStringNotContainsString('value="draft"', $html);
    }

    /** @test */
    public function render_invoices_shows_client_filter_fields_for_admin_view(): void {
        $_GET = [];

        $this->invoice_service->shouldReceive('get_valid_statuses')
            ->once()
            ->andReturnUsing(static fn() => ['draft', 'sent']);

        $this->invoice_service->shouldReceive('get_with_customers')
            ->once()
            ->with('2026-04-13', '2026-05-13')
            ->andReturn([
                [
                    'Id' => 1,
                    'InvoiceNumber' => 'INV-000123',
                    'CustomerName' => 'Acme Community Group',
                    'CustomerEmail' => 'acme@example.com',
                    'InvoiceDate' => '2026-05-01',
                    'DueDate' => '2026-05-31',
                    'Status' => 'sent',
                    'TotalAmount' => 100,
                    'AmountPaid' => 0,
                    'OrganisationName' => 'Acme Community Group',
                    'BillingName' => 'Acme Community Group',
                ],
            ]);

        ob_start();
        $this->renderer->render_invoices([], true, false);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="client_name"', $html);
        $this->assertStringContainsString('name="invoice_number"', $html);
        $this->assertStringContainsString('Begins with', $html);
        $this->assertStringContainsString('Contains', $html);
        $this->assertStringContainsString('data-invoice-filter-reset', $html);
    }

    /** @test */
    public function render_payments_defaults_to_last_month_and_limits_invoice_dropdown_statuses(): void {
        $_GET = [];

        $this->payment_service->shouldReceive('get_payments')
            ->once()
            ->with(0)
            ->andReturn([
                [
                    'Id' => 1,
                    'InvoiceId' => 1,
                    'InvoiceNumber' => 'INV-SENT',
                    'CustomerName' => 'Sent Customer',
                    'PaymentDate' => '2026-05-10',
                    'Amount' => 10,
                    'PaymentMethod' => 'card',
                    'TransactionReference' => 'IN-RANGE',
                    'Notes' => '',
                ],
                [
                    'Id' => 2,
                    'InvoiceId' => 1,
                    'InvoiceNumber' => 'INV-SENT',
                    'CustomerName' => 'Sent Customer',
                    'PaymentDate' => '2026-03-31',
                    'Amount' => 8,
                    'PaymentMethod' => 'card',
                    'TransactionReference' => 'OUT-OF-RANGE',
                    'Notes' => '',
                ],
            ]);

        $this->payment_service->shouldReceive('get_valid_methods')
            ->once()
            ->andReturnUsing(static fn() => ['bank_transfer']);

        $this->invoice_service->shouldReceive('get_with_customers')
            ->once()
            ->andReturn([
                [
                    'Id' => 1,
                    'InvoiceNumber' => 'INV-SENT',
                    'CustomerName' => 'Sent Customer',
                    'Status' => 'sent',
                ],
                [
                    'Id' => 2,
                    'InvoiceNumber' => 'INV-PART',
                    'CustomerName' => 'Part Customer',
                    'Status' => 'part-paid',
                ],
                [
                    'Id' => 3,
                    'InvoiceNumber' => 'INV-DRAFT',
                    'CustomerName' => 'Draft Customer',
                    'Status' => 'draft',
                ],
            ]);

        ob_start();
        $this->renderer->render_payments(true);
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('INV-SENT', $html);
        $this->assertStringContainsString('INV-PART', $html);
        $this->assertStringNotContainsString('INV-DRAFT', $html);
        $this->assertStringContainsString('IN-RANGE', $html);
        $this->assertStringNotContainsString('OUT-OF-RANGE', $html);
        $this->assertStringContainsString('name="start_date" value="2026-04-13"', $html);
        $this->assertStringContainsString('name="end_date" value="2026-05-13"', $html);
    }
}
