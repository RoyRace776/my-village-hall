<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {}
    }
    if (!defined('ARRAY_A')) {
        define('ARRAY_A', 'ARRAY_A');
    }
}

namespace MYVH\Tests\Unit\Customers {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Customers\CustomerRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CustomerRepositoryTest extends UnitTestCase
{
    /** @var \Mockery\MockInterface&\wpdb */
    private $wpdb;
    private CustomerRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = Mockery::mock('wpdb');
        $this->wpdb->prefix = 'wp_';
        $this->wpdb->shouldReceive('prepare')
            ->andReturnUsing(fn($q, ...$a) => $q)
            ->byDefault();
        $this->wpdb->last_error = '';

        $this->repo = new CustomerRepository($this->wpdb);
    }

    // ── get_all ──────────────────────────────────────────────────────────

    /** @test */
    public function get_all_returns_rows_excluding_system_customers(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 1, 'Name' => 'Alice']]);

        $result = $this->repo->get_all();

        $this->assertCount(1, $result);
        $this->assertSame('Alice', $result[0]['Name']);
    }

    /** @test */
    public function get_all_applies_orderby_and_limit(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->with(\Mockery::on(fn($sql) =>
                str_contains($sql, 'ORDER BY') && str_contains($sql, 'LIMIT')
            ), ARRAY_A)
            ->andReturn([]);

        $this->repo->get_all(['orderby' => 'Name', 'order' => 'ASC', 'limit' => 10]);
        $this->assertTrue(true);
    }

    // ── get_by_email ─────────────────────────────────────────────────────

    /** @test */
    public function get_by_email_returns_customer_row(): void
    {
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn(['Id' => 2, 'Email' => 'bob@example.com']);

        $result = $this->repo->get_by_email('bob@example.com');

        $this->assertSame(2, $result['Id']);
    }

    /** @test */
    public function get_by_email_returns_null_when_not_found(): void
    {
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn(null);

        $result = $this->repo->get_by_email('nobody@example.com');

        $this->assertNull($result);
    }

    // ── get_by_user_id ───────────────────────────────────────────────────

    /** @test */
    public function get_by_user_id_returns_customer_row(): void
    {
        $this->wpdb->shouldReceive('get_row')
            ->once()
            ->andReturn(['Id' => 3, 'WPUserId' => 7]);

        $result = $this->repo->get_by_user_id(7);

        $this->assertSame(3, $result['Id']);
    }

    // ── search ───────────────────────────────────────────────────────────

    /** @test */
    public function search_returns_matching_rows(): void
    {
        $this->wpdb->shouldReceive('esc_like')
            ->once()
            ->with('ali')
            ->andReturn('ali');

        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 1, 'Name' => 'Alice']]);

        $result = $this->repo->search('ali');

        $this->assertCount(1, $result);
    }

    // ── get_organisations_for_customer ───────────────────────────────────

    /** @test */
    public function get_organisations_for_customer_returns_empty_array_when_none(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn(null);

        $result = $this->repo->get_organisations_for_customer(99);

        $this->assertSame([], $result);
    }

    // ── get_organisations_for_user_id ────────────────────────────────────

    /** @test */
    public function get_organisations_for_user_id_returns_rows(): void
    {
        $this->wpdb->shouldReceive('get_results')
            ->once()
            ->andReturn([['Id' => 10, 'Name' => 'Village FC']]);

        $result = $this->repo->get_organisations_for_user_id(5);

        $this->assertCount(1, $result);
    }
}

} // end namespace
