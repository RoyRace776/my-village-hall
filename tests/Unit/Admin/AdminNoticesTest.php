<?php

namespace MYVH\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use MYVH\Admin\AdminNotices;
use MYVH\Tests\Unit\UnitTestCase;

class AdminNoticesTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // esc helpers are safe to stub globally as they are never asserted with expect()
        Functions\stubs([
            'esc_attr' => fn($v) => (string) $v,
            'esc_html' => fn($v) => (string) $v,
        ]);
    }

    // ── add / success / error / warning ─────────────────────────────────

    /** @test */
    public function it_calls_set_transient_when_adding_a_notice(): void
    {
        Functions\expect('get_transient')->once()->andReturn([]);
        Functions\expect('set_transient')
            ->once()
            ->with('myvh_admin_notices', \Mockery::on(fn($v) =>
                count($v) === 1 && $v[0]['type'] === 'success' && $v[0]['message'] === 'Done'
            ), 30)
            ->andReturn(true);

        AdminNotices::add('Done', 'success');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function success_helper_stores_success_type(): void
    {
        Functions\expect('get_transient')->once()->andReturn([]);
        Functions\expect('set_transient')
            ->once()
            ->with('myvh_admin_notices', \Mockery::on(fn($v) =>
                $v[0]['type'] === 'success'
            ), 30);

        AdminNotices::success('All good');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function error_helper_stores_error_type(): void
    {
        Functions\expect('get_transient')->once()->andReturn([]);
        Functions\expect('set_transient')
            ->once()
            ->with('myvh_admin_notices', \Mockery::on(fn($v) =>
                $v[0]['type'] === 'error'
            ), 30);

        AdminNotices::error('Something went wrong');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function warning_helper_stores_warning_type(): void
    {
        Functions\expect('get_transient')->once()->andReturn([]);
        Functions\expect('set_transient')
            ->once()
            ->with('myvh_admin_notices', \Mockery::on(fn($v) =>
                $v[0]['type'] === 'warning'
            ), 30);

        AdminNotices::warning('Watch out');
        $this->addToAssertionCount(1);
    }

    /** @test */
    public function it_appends_to_existing_notices(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn([['type' => 'success', 'message' => 'First']]);

        Functions\expect('set_transient')
            ->once()
            ->with('myvh_admin_notices', \Mockery::on(fn($v) => count($v) === 2), 30);

        AdminNotices::add('Second', 'error');
        $this->addToAssertionCount(1);
    }

    // ── render ───────────────────────────────────────────────────────────

    /** @test */
    public function render_outputs_nothing_when_no_notices(): void
    {
        Functions\expect('get_transient')->once()->andReturn(false);

        ob_start();
        AdminNotices::render();
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }

    /** @test */
    public function render_outputs_notice_divs_and_clears_transient(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn([
                ['type' => 'success', 'message' => 'Saved'],
                ['type' => 'error',   'message' => 'Failed'],
            ]);

        Functions\expect('delete_transient')->once()->with('myvh_admin_notices');

        ob_start();
        AdminNotices::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-success', $output);
        $this->assertStringContainsString('notice-error', $output);
        $this->assertStringContainsString('Saved', $output);
        $this->assertStringContainsString('Failed', $output);
    }

    /** @test */
    public function render_uses_notice_info_class_for_unknown_type(): void
    {
        Functions\expect('get_transient')
            ->once()
            ->andReturn([['type' => 'custom', 'message' => 'Info msg']]);

        Functions\expect('delete_transient')->once();

        ob_start();
        AdminNotices::render();
        $output = ob_get_clean();

        $this->assertStringContainsString('notice-info', $output);
    }
}
