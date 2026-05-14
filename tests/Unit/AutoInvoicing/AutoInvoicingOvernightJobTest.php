<?php

namespace MYVH\Tests\Unit\AutoInvoicing;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\AutoInvoicing\AutoInvoice;
use MYVH\AutoInvoicing\AutoInvoicingOvernightJob;
use MYVH\Core\Scheduling\OvernightJobResult;
use MYVH\Tests\Unit\UnitTestCase;

class AutoInvoicingOvernightJobTest extends UnitTestCase
{
    private function makeJob(): AutoInvoicingOvernightJob
    {
        $auto_invoice = Mockery::mock(AutoInvoice::class);
        return new AutoInvoicingOvernightJob($auto_invoice);
    }

    /** @test */
    public function is_enabled_returns_false_when_setting_is_false(): void
    {
        // UnitTestCase already stubs myvh_setting => false.
        $this->assertFalse($this->makeJob()->is_enabled());
    }

    /** @test */
    public function is_enabled_returns_true_when_setting_is_true(): void
    {
        Functions\when('myvh_setting')->justReturn(true);

        $this->assertTrue($this->makeJob()->is_enabled());
    }

    /** @test */
    public function run_delegates_to_auto_invoice_and_returns_result(): void
    {
        $auto_invoice = Mockery::mock(AutoInvoice::class);
        $auto_invoice->expects('generate')->once()->andReturn(5);

        $job    = new AutoInvoicingOvernightJob($auto_invoice);
        $result = $job->run();

        $this->assertInstanceOf(OvernightJobResult::class, $result);
        $this->assertEquals('Auto-Invoicing', $result->job_name);
        $this->assertEquals(5, $result->count);
        $this->assertTrue($result->success);
    }

    /** @test */
    public function run_returns_zero_count_when_no_invoices_generated(): void
    {
        $auto_invoice = Mockery::mock(AutoInvoice::class);
        $auto_invoice->expects('generate')->once()->andReturn(0);

        $job    = new AutoInvoicingOvernightJob($auto_invoice);
        $result = $job->run();

        $this->assertEquals(0, $result->count);
        $this->assertTrue($result->success);
    }
}
