<?php

namespace MYVH\Tests\Unit\Services;

use Brain\Monkey\Functions;
use MYVH\Invoices\InvoiceFileStorage;
use MYVH\Invoices\InvoiceItemRepository;
use MYVH\Invoices\InvoicePdfRenderer;
use MYVH\Invoices\InvoiceRepository;
use MYVH\Invoices\InvoiceService;
use MYVH\Invoices\PdfGenerator;
use MYVH\Payments\PaymentRepository;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class InvoiceServiceTest extends UnitTestCase {
    private $invoice_repo;
    private $invoice_item_repo;
    private $payment_repo;
    private $pdf_renderer;
    private $pdf_generator;
    private $file_storage;
    private InvoiceService $service;

    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => fn($value) => (string) $value,
            'current_time' => fn($type) => $type === 'timestamp' ? strtotime('2026-04-09 12:00:00') : '2026-04-09',
            '__' => fn($value) => $value,
            'is_wp_error' => fn($value) => $value instanceof WP_Error,
            'MYVH\\Invoices\\is_wp_error' => fn($value) => $value instanceof WP_Error,
        ]);

        $this->invoice_repo  = $this->mock(InvoiceRepository::class);
        $this->invoice_item_repo = $this->mock(InvoiceItemRepository::class);
        $this->payment_repo  = $this->mock(PaymentRepository::class);
        $this->pdf_renderer  = $this->mock(InvoicePdfRenderer::class);
        $this->pdf_generator = $this->mock(PdfGenerator::class);
        $this->file_storage  = $this->mock(InvoiceFileStorage::class);

        $this->service = new InvoiceService(
            $this->invoice_repo,
            $this->invoice_item_repo,
            $this->payment_repo,
            $this->pdf_renderer,
            $this->pdf_generator,
            $this->file_storage
        );
    }

    public function tearDown(): void {
        unset(
            $this->service,
            $this->invoice_repo,
            $this->invoice_item_repo,
            $this->payment_repo,
            $this->pdf_renderer,
            $this->pdf_generator,
            $this->file_storage
        );
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
                ['Id' => 1, 'InvoiceId' => 42, 'BookingId' => 7, 'BookingDescription' => '', 'Description' => 'Room hire'],
            ]);

        $this->payment_repo->shouldReceive('get_by_invoice')
            ->once()
            ->with(42)
            ->andReturn([]);

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

        $this->payment_repo->shouldReceive('get_by_invoice')
            ->once()
            ->with(43)
            ->andReturn([]);

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

        $this->payment_repo->shouldReceive('get_by_invoice')
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

        $this->payment_repo->shouldReceive('get_by_invoice')
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

    /** @test */
    public function update_status_rejects_cancelling_invoice_with_payments(): void {
        $this->invoice_repo->shouldReceive('get_by_id')
            ->once()
            ->with(15)
            ->andReturn(['Id' => 15, 'Status' => 'sent', 'TotalAmount' => 100, 'DueDate' => '2026-06-01']);

        $this->payment_repo->shouldReceive('count_by_invoice')
            ->once()
            ->with(15)
            ->andReturn(1);

        $result = $this->service->update_status(15, 'cancelled');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function record_payment_creates_payment_and_updates_invoice_state(): void {
        $this->invoice_repo->shouldReceive('get_by_id')
            ->twice()
            ->with(19)
            ->andReturn(['Id' => 19, 'Status' => 'sent', 'TotalAmount' => 100, 'DueDate' => '2026-06-01']);

        $this->payment_repo->shouldReceive('create')
            ->once()
            ->withArgs(function (array $data): bool {
                return (int) ($data['InvoiceId'] ?? 0) === 19
                    && (float) ($data['Amount'] ?? 0) === 25.0
                    && ($data['PaymentMethod'] ?? '') === 'card';
            })
            ->andReturn(44);

        $this->payment_repo->shouldReceive('get_total_by_invoice')
            ->once()
            ->with(19)
            ->andReturn(25.0);

        $this->invoice_repo->shouldReceive('update')
            ->once()
            ->with(
                [
                    'AmountPaid' => 25.0,
                    'AmountDue' => 75.0,
                    'Status' => 'part-paid',
                ],
                ['Id' => 19]
            )
            ->andReturn(true);

        $result = $this->service->record_payment(19, 25.0, 'card', '2026-04-09', 'REF-1', 'Paid by card');

        $this->assertSame(44, $result);
    }

    /** @test */
    public function delete_payment_recalculates_invoice_after_removal(): void {
        $this->payment_repo->shouldReceive('get_by_id')
            ->once()
            ->with(55)
            ->andReturn(['Id' => 55, 'InvoiceId' => 22]);

        $this->payment_repo->shouldReceive('delete')
            ->once()
            ->with(55)
            ->andReturn(true);

        $this->invoice_repo->shouldReceive('get_by_id')
            ->once()
            ->with(22)
            ->andReturn(['Id' => 22, 'Status' => 'part-paid', 'TotalAmount' => 100, 'DueDate' => '2099-06-01']);

        $this->payment_repo->shouldReceive('get_total_by_invoice')
            ->once()
            ->with(22)
            ->andReturn(0.0);

        $this->invoice_repo->shouldReceive('update')
            ->once()
            ->with(
                [
                    'AmountPaid' => 0.0,
                    'AmountDue' => 100.0,
                    'Status' => 'sent',
                ],
                ['Id' => 22]
            )
            ->andReturn(true);

        $result = $this->service->delete_payment(55);

        $this->assertTrue($result);
    }

    /** @test */
    public function delete_returns_wp_error_when_pdf_delete_fails(): void {
        $this->payment_repo->shouldReceive('count_by_invoice')
            ->once()
            ->with(15)
            ->andReturn(0);

        $this->file_storage->shouldReceive('delete')
            ->once()
            ->with(15)
            ->andReturn(false);

        $this->invoice_repo->shouldNotReceive('delete');

        $result = $this->service->delete(15);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('filesystem', $result->get_error_code());
    }

    /** @test */
    public function delete_deletes_pdf_and_invoice_when_valid(): void {
        $this->payment_repo->shouldReceive('count_by_invoice')
            ->once()
            ->with(16)
            ->andReturn(0);

        $this->file_storage->shouldReceive('delete')
            ->once()
            ->with(16)
            ->andReturn(true);

        $this->invoice_repo->shouldReceive('delete')
            ->once()
            ->with(16)
            ->andReturn(true);

        $result = $this->service->delete(16);

        $this->assertSame(16, $result);
    }

    // ── generate_pdf ───────────────────────────────────────────────────

    /** @test */
    public function generate_pdf_returns_existing_path_without_regenerating(): void {
        $existing_path = '/var/www/uploads/myvh-invoices/2026/04/invoice-INV-000010.pdf';

        $this->file_storage->shouldReceive('exists')
            ->once()
            ->with(10)
            ->andReturn(true);

        $this->file_storage->shouldReceive('getPath')
            ->once()
            ->with(10)
            ->andReturn($existing_path);

        // Renderer, generator, and save must NOT be called on a cache hit.
        $this->pdf_renderer->shouldNotReceive('renderHtml');
        $this->pdf_generator->shouldNotReceive('render');
        $this->file_storage->shouldNotReceive('save');
        $this->invoice_repo->shouldNotReceive('set_pdf_path');

        $result = $this->service->generate_pdf(10);

        $this->assertSame($existing_path, $result);
    }

    /** @test */
    public function generate_pdf_renders_and_saves_when_no_pdf_exists(): void {
        $invoice_id = 11;
        $invoice    = [
            'Id'            => $invoice_id,
            'InvoiceNumber' => 'INV-000011',
            'InvoiceDate'   => '2026-04-17',
            'TotalAmount'   => '150.00',
        ];
        $expected_path = '/var/www/uploads/myvh-invoices/2026/04/invoice-INV-000011.pdf';

        $this->file_storage->shouldReceive('exists')
            ->once()
            ->with($invoice_id)
            ->andReturn(false);

        // get_detail internally calls these three repo/payment methods.
        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with($invoice_id)
            ->andReturn($invoice);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with($invoice_id)
            ->andReturn([]);

        $this->payment_repo->shouldReceive('get_by_invoice')
            ->once()
            ->with($invoice_id)
            ->andReturn([]);

        $this->pdf_renderer->shouldReceive('renderHtml')
            ->once()
            ->withArgs(fn(array $inv): bool => $inv['Id'] === $invoice_id)
            ->andReturn('<html>invoice html</html>');

        $this->pdf_generator->shouldReceive('render')
            ->once()
            ->with('<html>invoice html</html>')
            ->andReturn('%PDF-binary');

        $this->file_storage->shouldReceive('save')
            ->once()
            ->with($invoice_id, '%PDF-binary')
            ->andReturn($expected_path);

        $this->invoice_repo->shouldReceive('set_pdf_path')
            ->once()
            ->with($invoice_id, $expected_path)
            ->andReturn(true);

        $result = $this->service->generate_pdf($invoice_id);

        $this->assertSame($expected_path, $result);
    }

    /** @test */
    public function generate_pdf_returns_wp_error_when_invoice_not_found(): void {
        $this->file_storage->shouldReceive('exists')
            ->once()
            ->with(99)
            ->andReturn(false);

        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with(99)
            ->andReturn(null);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->never();

        $result = $this->service->generate_pdf(99);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    // ── get_invoice_pdf_url ──────────────────────────────────────────

    /** @test */
    public function get_invoice_pdf_url_returns_public_url(): void {
        $invoice_id    = 12;
        $invoice       = [
            'Id'            => $invoice_id,
            'InvoiceNumber' => 'INV-000012',
            'InvoiceDate'   => '2026-04-17',
            'TotalAmount'   => '200.00',
        ];
        $saved_path    = '/var/www/uploads/myvh-invoices/2026/04/invoice-INV-000012.pdf';
        $expected_url  = 'https://example.com/wp-content/uploads/myvh-invoices/2026/04/invoice-INV-000012.pdf';

        // PDF does not exist yet — goes through full generation pipeline.
        $this->file_storage->shouldReceive('exists')
            ->once()
            ->with($invoice_id)
            ->andReturn(false);

        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with($invoice_id)
            ->andReturn($invoice);

        $this->invoice_repo->shouldReceive('get_items_for_invoice')
            ->once()
            ->with($invoice_id)
            ->andReturn([]);

        $this->payment_repo->shouldReceive('get_by_invoice')
            ->once()
            ->with($invoice_id)
            ->andReturn([]);

        $this->pdf_renderer->shouldReceive('renderHtml')
            ->once()
            ->andReturn('<html>ok</html>');

        $this->pdf_generator->shouldReceive('render')
            ->once()
            ->andReturn('%PDF-binary');

        $this->file_storage->shouldReceive('save')
            ->once()
            ->with($invoice_id, '%PDF-binary')
            ->andReturn($saved_path);

        $this->invoice_repo->shouldReceive('set_pdf_path')
            ->once()
            ->with($invoice_id, $saved_path)
            ->andReturn(true);

        $this->file_storage->shouldReceive('getUrl')
            ->once()
            ->with($invoice_id)
            ->andReturn($expected_url);

        $result = $this->service->get_invoice_pdf_url($invoice_id);

        $this->assertSame($expected_url, $result);
    }

    /** @test */
    public function get_invoice_pdf_url_propagates_wp_error_from_generate_pdf(): void {
        $this->file_storage->shouldReceive('exists')
            ->once()
            ->with(88)
            ->andReturn(false);

        $this->invoice_repo->shouldReceive('get_detail')
            ->once()
            ->with(88)
            ->andReturn(null);

        $result = $this->service->get_invoice_pdf_url(88);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }
}