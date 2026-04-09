<?php

namespace MYVH\Tests\Unit\Services;

use MYVH\Invoices\InvoiceRepository;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentRepository;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class InvoiceServiceTest extends UnitTestCase {
    private $invoice_repo;
    private $payment_repo;
    private InvoiceService $service;

    protected function setUp(): void {
        parent::setUp();

        $this->invoice_repo = $this->mock(InvoiceRepository::class);
        $this->payment_repo = $this->mock(PaymentRepository::class);
        $this->service = new InvoiceService($this->invoice_repo, $this->payment_repo);
    }

    public function tearDown(): void {
        unset($this->service, $this->invoice_repo, $this->payment_repo);
        parent::tearDown();
    }

    /** @test */
    public function get_detail_returns_invoice_with_items(): void {
        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with(42)
            ->andReturn(['Id' => 42, 'InvoiceNumber' => 'INV-000042']);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with(42)
            ->andReturn([
                ['Id' => 1, 'InvoiceId' => 42, 'BookingId' => 7],
            ]);

        $result = $this->service->get_detail(42);

        $this->assertSame(42, $result['Id']);
        $this->assertCount(1, $result['Items']);
        $this->assertSame(7, $result['Items'][0]['BookingId']);
        $this->assertCount(1, $result['BookingsSummary']);
        $this->assertSame(7, $result['BookingsSummary'][0]['BookingId']);
    }

    /** @test */
    public function get_detail_groups_multiple_invoice_items_into_one_booking_total(): void {
        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with(43)
            ->andReturn(['Id' => 43, 'InvoiceNumber' => 'INV-000043']);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with(43)
            ->andReturn([
                [
                    'Id' => 1,
                    'BookingId' => 12,
                    'Description' => 'Room charge',
                    'BookingDescription' => 'Wedding reception',
                    'TotalAmount' => '100.00',
                    'StartDate' => '2026-06-01',
                ],
                [
                    'Id' => 2,
                    'BookingId' => 12,
                    'Description' => 'Chair add-on',
                    'BookingDescription' => 'Wedding reception',
                    'TotalAmount' => '25.50',
                    'StartDate' => '2026-06-01',
                ],
            ]);

        $result = $this->service->get_detail(43);

        $this->assertCount(1, $result['BookingsSummary']);
        $this->assertSame('Wedding reception', $result['BookingsSummary'][0]['Description']);
        $this->assertSame(125.5, $result['BookingsSummary'][0]['TotalAmount']);
    }

    /** @test */
    public function get_detail_for_portal_uses_portal_query_for_customers(): void {
        $this->invoice_repo->shouldReceive('get_portal_detail')
            ->once()
            ->with(18, 5)
            ->andReturn(['Id' => 18, 'InvoiceNumber' => 'INV-000018']);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with(18)
            ->andReturn([]);

        $result = $this->service->get_detail_for_portal(18, 5, false);

        $this->assertSame(18, $result['Id']);
        $this->assertSame([], $result['Items']);
    }

    /** @test */
    public function get_detail_for_portal_uses_full_detail_for_client_admins(): void {
        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 21, 'InvoiceNumber' => 'INV-000021']);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with(21)
            ->andReturn([]);

        $result = $this->service->get_detail_for_portal(21, 0, true);

        $this->assertSame(21, $result['Id']);
    }

    /** @test */
    public function update_status_rejects_invalid_status(): void {
        $result = $this->service->update_status(15, 'archived');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }
}