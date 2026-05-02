<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Payments {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Payments\PaymentRepository;
use MYVH\Tests\Unit\UnitTestCase;

class PaymentRepositoryTest extends UnitTestCase
{
    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;
    private PaymentRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($q, ...$a) => $q)
            ->byDefault();

        $this->repo = new PaymentRepository($this->wpdb);
    }

    // ── count_by_invoice ─────────────────────────────────────────────────

    /** @test */
    public function count_by_invoice_returns_integer(): void
    {
        $this->wpdb->shouldReceive('get_var')->once()->andReturn('3');

        $result = $this->repo->count_by_invoice(5);

        $this->assertSame(3, $result);
    }

    // ── get_by_invoice ───────────────────────────────────────────────────

    /** @test */
    public function get_by_invoice_returns_rows(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 1, 'InvoiceId' => 5, 'Amount' => '50.00']]);

        $result = $this->repo->get_by_invoice(5);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function get_by_invoice_returns_empty_array_when_no_results(): void
    {
        $this->wpdb->shouldReceive('get_results')->once()->andReturn(null);

        $result = $this->repo->get_by_invoice(99);

        $this->assertSame([], $result);
    }

    // ── get_total_by_invoice ─────────────────────────────────────────────

    /** @test */
    public function get_total_by_invoice_returns_float(): void
    {
        $this->wpdb->shouldReceive('get_var')->once()->andReturn('125.50');

        $result = $this->repo->get_total_by_invoice(5);

        $this->assertEqualsWithDelta(125.50, $result, 0.001);
    }

    // ── get_with_invoice_details ─────────────────────────────────────────

    /** @test */
    public function get_with_invoice_details_returns_all_payments_when_no_filter(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 1], ['Id' => 2]]);

        $result = $this->repo->get_with_invoice_details();

        $this->assertCount(2, $result);
    }

    /** @test */
    public function get_with_invoice_details_filters_by_invoice_id(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 1]]);

        $result = $this->repo->get_with_invoice_details(7);

        $this->assertCount(1, $result);
    }
}

} // end namespace
