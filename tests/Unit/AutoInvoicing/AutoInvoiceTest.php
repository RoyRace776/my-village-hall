<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Mockery;
use MYVH\AutoInvoicing\AutoInvoice;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoicing;
use MYVH\AutoInvoicing\SingleBookingAutoInvoicing;
use MYVH\Tests\Unit\UnitTestCase;

class AutoInvoiceTest extends UnitTestCase
{
    /** @var SingleBookingAutoInvoicing&\Mockery\MockInterface */
    private $single;
    /** @var RecurringBookingAutoInvoicing&\Mockery\MockInterface */
    private $recurring;
    private AutoInvoice $auto_invoice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->single    = Mockery::mock(SingleBookingAutoInvoicing::class);
        $this->recurring = Mockery::mock(RecurringBookingAutoInvoicing::class);

        $this->auto_invoice = new AutoInvoice($this->single, $this->recurring);
    }

    /** @test */
    public function generate_returns_sum_of_single_and_recurring_invoice_counts(): void
    {
        $this->recurring->shouldReceive('process_with_result')->once()->andReturn([
            'created_invoice_ids' => [101, 102],
            'treat_as_single_bookings' => [
                ['Id' => 77, 'Status' => 'confirmed'],
            ],
        ]);
        $this->single->shouldReceive('process_with_result')
            ->once()
            ->with([
                ['Id' => 77, 'Status' => 'confirmed'],
            ])
            ->andReturn([
                'created_invoice_ids' => [201, 202, 203],
            ]);

        $result = $this->auto_invoice->generate();

        $this->assertSame(5, $result);
    }

    /** @test */
    public function generate_returns_zero_when_no_invoices_generated(): void
    {
        $recurring_ran = false;

        $this->recurring->shouldReceive('process_with_result')->once()->andReturnUsing(function () use (&$recurring_ran) {
            $recurring_ran = true;

            return [
                'created_invoice_ids' => [],
                'treat_as_single_bookings' => [],
            ];
        });

        $this->single->shouldReceive('process_with_result')->once()->andReturnUsing(function () use (&$recurring_ran) {
            $this->assertTrue($recurring_ran);

            return [
                'created_invoice_ids' => [],
            ];
        });

        $result = $this->auto_invoice->generate();

        $this->assertSame(0, $result);
    }
}
