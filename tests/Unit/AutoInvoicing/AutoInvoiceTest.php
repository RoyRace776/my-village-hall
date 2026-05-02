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
        $this->single->shouldReceive('process')->once()->andReturn(3);
        $this->recurring->shouldReceive('process')->once()->andReturn(2);

        $result = $this->auto_invoice->generate();

        $this->assertSame(5, $result);
    }

    /** @test */
    public function generate_returns_zero_when_no_invoices_generated(): void
    {
        $this->single->shouldReceive('process')->once()->andReturn(0);
        $this->recurring->shouldReceive('process')->once()->andReturn(0);

        $result = $this->auto_invoice->generate();

        $this->assertSame(0, $result);
    }
}
