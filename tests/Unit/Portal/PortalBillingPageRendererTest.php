<?php

namespace MYVH\Tests\Unit\Portal;

use MYVH\Bookings\BookingService;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentService;
use MYVH\Portal\Ajax\PortalBillingPageRenderer;
use MYVH\Tests\Unit\UnitTestCase;

class PortalBillingPageRendererTest extends UnitTestCase {
    private BookingService $booking_service;
    private InvoiceService $invoice_service;
    private PaymentService $payment_service;
    private PortalBillingPageRenderer $renderer;

    protected function setUp(): void {
        parent::setUp();

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
}
