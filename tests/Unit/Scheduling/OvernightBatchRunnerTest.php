<?php

namespace MYVH\Tests\Unit\Scheduling;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Core\Scheduling\OvernightBatchRunner;
use MYVH\Core\Scheduling\OvernightJobInterface;
use MYVH\Core\Scheduling\OvernightJobResult;
use MYVH\Email\EmailService;
use MYVH\Tests\Unit\UnitTestCase;

class OvernightBatchRunnerTest extends UnitTestCase
{
    private function makeJob(bool $enabled, ?OvernightJobResult $result = null): OvernightJobInterface
    {
        $job = Mockery::mock(OvernightJobInterface::class);
        $job->allows('is_enabled')->andReturn($enabled);
        if ($result !== null) {
            $job->allows('run')->andReturn($result);
        }
        return $job;
    }

    private function makeEmailService(): \Mockery\MockInterface
    {
        return Mockery::mock(EmailService::class);
    }

    // -------------------------------------------------------------------------
    // register()
    // -------------------------------------------------------------------------

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubs([
            'esc_html' => fn($v) => (string) $v,
            'esc_url'  => fn($v) => (string) $v,
        ]);
    }

    /** @test */
    public function register_hooks_reconcile_schedule_and_run_batch(): void
    {
        Functions\expect('add_action')
            ->with('init', Mockery::type('array'))
            ->once();

        Functions\expect('add_action')
            ->with(OvernightBatchRunner::HOOK, Mockery::type('array'))
            ->once();

        $runner = new OvernightBatchRunner([], $this->makeEmailService());
        $runner->register();
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // reconcile_schedule()
    // -------------------------------------------------------------------------

    /** @test */
    public function reconcile_schedule_registers_cron_when_any_job_enabled(): void
    {
        Functions\expect('wp_next_scheduled')
            ->with(OvernightBatchRunner::HOOK)
            ->andReturn(false);

        Functions\expect('wp_timezone')
            ->andReturn(new \DateTimeZone('UTC'));

        Functions\expect('wp_schedule_event')
            ->once()
            ->with(Mockery::type('int'), 'daily', OvernightBatchRunner::HOOK);

        $runner = new OvernightBatchRunner(
            [ $this->makeJob(true) ],
            $this->makeEmailService()
        );
        $runner->reconcile_schedule();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function reconcile_schedule_clears_cron_when_all_jobs_disabled(): void
    {
        Functions\expect('wp_clear_scheduled_hook')
            ->once()
            ->with(OvernightBatchRunner::HOOK);

        $runner = new OvernightBatchRunner(
            [ $this->makeJob(false) ],
            $this->makeEmailService()
        );
        $runner->reconcile_schedule();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function reconcile_schedule_clears_cron_when_no_jobs_registered(): void
    {
        Functions\expect('wp_clear_scheduled_hook')
            ->once()
            ->with(OvernightBatchRunner::HOOK);

        $runner = new OvernightBatchRunner([], $this->makeEmailService());
        $runner->reconcile_schedule();
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // run_batch()
    // -------------------------------------------------------------------------

    /** @test */
    public function run_batch_skips_disabled_jobs(): void
    {
        $job = Mockery::mock(OvernightJobInterface::class);
        $job->expects('is_enabled')->once()->andReturn(false);
        $job->expects('run')->never();

        $email = $this->makeEmailService();
        $email->allows('send');

        Functions\expect('get_option')->with('admin_email')->andReturn('');

        $runner = new OvernightBatchRunner([$job], $email);
        $runner->run_batch();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function run_batch_collects_results_from_enabled_jobs(): void
    {
        $result = new OvernightJobResult('Auto-Invoicing', 3, true, '3 invoice(s) generated');
        $job    = $this->makeJob(true, $result);

        $email = Mockery::mock(EmailService::class);
        $email->expects('send')->once()->with(Mockery::on(function ($args) {
            return $args['template'] === 'overnight-batch-summary'
                && str_contains($args['template_vars']['summary_rows'] ?? '', 'Auto-Invoicing');
        }));

        Functions\expect('get_option')->with('admin_email')->andReturn('admin@example.com');
        Functions\expect('current_time')->andReturn('14 May 2026 04:00');

        $runner = new OvernightBatchRunner([$job], $email);
        $runner->run_batch();
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function run_batch_catches_job_exceptions_and_marks_as_failed(): void
    {
        $job = Mockery::mock(OvernightJobInterface::class);
        $job->allows('is_enabled')->andReturn(true);
        $job->allows('run')->andThrow(new \RuntimeException('DB error'));

        $email = Mockery::mock(EmailService::class);
        $email->expects('send')->once()->with(Mockery::on(function ($args) {
            return str_contains($args['template_vars']['summary_rows'] ?? '', 'Failed');
        }));

        Functions\expect('get_option')->with('admin_email')->andReturn('admin@example.com');
        Functions\expect('current_time')->andReturn('14 May 2026 04:00');

        $runner = new OvernightBatchRunner([$job], $email);
        $runner->run_batch();
        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // send_summary_email()
    // -------------------------------------------------------------------------

    /** @test */
    public function send_summary_email_is_no_op_when_results_empty(): void
    {
        $email = Mockery::mock(EmailService::class);
        $email->expects('send')->never();

        $runner = new OvernightBatchRunner([], $email);
        $runner->send_summary_email([]);
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function send_summary_email_is_no_op_when_admin_email_empty(): void
    {
        Functions\expect('get_option')->with('admin_email')->andReturn('');

        $email = Mockery::mock(EmailService::class);
        $email->expects('send')->never();

        $result = new OvernightJobResult('Auto-Invoicing', 2, true);
        $runner = new OvernightBatchRunner([], $email);
        $runner->send_summary_email([$result]);
        $this->addToAssertionCount(1);
    }
}
