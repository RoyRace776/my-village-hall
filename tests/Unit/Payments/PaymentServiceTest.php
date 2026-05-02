<?php

namespace MYVH\Tests\Unit\Payments;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Invoices\InvoiceService;
use MYVH\Payments\PaymentRepository;
use MYVH\Payments\PaymentService;
use MYVH\Tests\Unit\UnitTestCase;

class PaymentServiceTest extends UnitTestCase
{
    /** @var PaymentRepository&\Mockery\MockInterface */
    private $repo;
    /** @var InvoiceService&\Mockery\MockInterface */
    private $invoice_service;
    private PaymentService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repo            = Mockery::mock(PaymentRepository::class);
        $this->invoice_service = Mockery::mock(InvoiceService::class);
        $this->service         = new PaymentService($this->repo, $this->invoice_service);

        Functions\stubs([
            'sanitize_key'           => fn($v) => (string) $v,
            'sanitize_textarea_field' => fn($v) => (string) $v,
            'current_time'           => '2026-05-02',
            'is_wp_error'            => fn($v) => $v instanceof \WP_Error,
        ]);
    }

    // ── get_valid_methods ─────────────────────────────────────────────────

    /** @test */
    public function get_valid_methods_returns_all_method_keys(): void
    {
        $methods = $this->service->get_valid_methods();

        $this->assertContains('cash', $methods);
        $this->assertContains('card', $methods);
        $this->assertContains('cheque', $methods);
        $this->assertContains('transfer', $methods);
        $this->assertContains('other', $methods);
    }

    /** @test */
    public function get_method_label_capitalises_method_name(): void
    {
        $this->assertSame('Cash', $this->service->get_method_label('cash'));
        $this->assertSame('Card', $this->service->get_method_label('card'));
    }

    // ── create – validation ───────────────────────────────────────────────

    /** @test */
    public function create_returns_error_when_invoice_id_missing(): void
    {
        $result = $this->service->create(['payment_amount' => 50, 'payment_method' => 'cash', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_amount_is_zero(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 0, 'payment_method' => 'cash', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_for_invalid_payment_method(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 50, 'payment_method' => 'bitcoin', 'payment_date' => '2026-05-01']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_for_invalid_date(): void
    {
        $result = $this->service->create(['invoice_id' => 1, 'payment_amount' => 50, 'payment_method' => 'cash', 'payment_date' => 'not-a-date']);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_invoice_not_found(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturn(null);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('not_found', $result->get_error_code());
    }

    /** @test */
    public function create_returns_error_when_invoice_already_fully_paid(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturn(['AmountDue' => 0]);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_returns_error_when_amount_exceeds_balance(): void
    {
        $this->invoice_service->shouldReceive('get')->with(1)->andReturn(['AmountDue' => 30.00]);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function create_records_payment_when_valid(): void
    {
        $this->invoice_service->shouldReceive('get')
            ->with(1)
            ->andReturn(['AmountDue' => 100.00]);

        $this->invoice_service->shouldReceive('record_payment')
            ->once()
            ->with(1, 50.0, 'cash', '2026-05-01', '', '')
            ->andReturn(11);

        $result = $this->service->create([
            'invoice_id'     => 1,
            'payment_amount' => 50,
            'payment_method' => 'cash',
            'payment_date'   => '2026-05-01',
        ]);

        $this->assertSame(11, $result);
    }

    // ── delete ───────────────────────────────────────────────────────────

    /** @test */
    public function delete_returns_error_when_payment_id_is_zero(): void
    {
        $result = $this->service->delete(0);

        $this->assertInstanceOf(\WP_Error::class, $result);
    }

    /** @test */
    public function delete_delegates_to_invoice_service(): void
    {
        $this->invoice_service->shouldReceive('delete_payment')->with(5)->andReturn(true);

        $result = $this->service->delete(5);

        $this->assertTrue($result);
    }
}
