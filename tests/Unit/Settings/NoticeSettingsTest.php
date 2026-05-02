<?php

namespace MYVH\Tests\Unit\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MYVH\Settings\NoticeSettings;
use MYVH\Tests\Unit\UnitTestCase;

class NoticeSettingsTest extends UnitTestCase
{
    // ── schema structure ──────────────────────────────────────────────────

    protected function setUp(): void
    {
        parent::setUp();
        Functions\stubs([
            'is_multisite' => false,
        ]);
    }

    /** @test */
    public function schema_exposes_notices_section_with_notices_field(): void
    {
        $schema = (new NoticeSettings())->schema();

        $this->assertArrayHasKey('notices', $schema);
        $this->assertArrayHasKey('fields', $schema['notices']);
        $this->assertArrayHasKey('notices', $schema['notices']['fields']);

        $field = $schema['notices']['fields']['notices'];

        $this->assertSame('notices', $field['type']);
        $this->assertSame([], $field['default']);
    }

    /** @test */
    public function tab_returns_notices_key_and_label(): void
    {
        $tab = (new NoticeSettings())->tab();

        $this->assertSame('notices', $tab['key']);
        $this->assertSame('Hall Notices', $tab['label']);
    }

    /** @test */
    public function settings_class_requires_manage_options_capability(): void
    {
        $settings = new NoticeSettings();

        $this->assertSame('manage_options', $settings->get_required_capability());
    }

    /** @test */
    public function settings_class_is_hidden_from_client_admin(): void
    {
        $settings = new NoticeSettings();

        $this->assertTrue($settings->should_hide_from_client_admin());
    }

    // ── save: notices repeater sanitisation ───────────────────────────────

    /** @test */
    public function save_sanitises_and_stores_valid_notice_rows(): void
    {
        Functions\expect('get_option')->zeroOrMoreTimes()->andReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('myvh_notice_settings', \Mockery::on(function (array $saved): bool {
                $notices = $saved['notices'];
                return count($notices) === 2
                    && $notices[0]['message']    === 'Kitchen works'
                    && $notices[0]['start_date'] === '2026-05-01'
                    && $notices[0]['end_date']   === '2026-06-01'
                    && $notices[1]['message']    === 'AGM tonight'
                    && $notices[1]['start_date'] === ''
                    && $notices[1]['end_date']   === '';
            }))
            ->andReturn(true);

        $settings = new NoticeSettings();
        $settings->save([
            'notices' => [
                ['message' => 'Kitchen works', 'start_date' => '2026-05-01', 'end_date' => '2026-06-01'],
                ['message' => 'AGM tonight',   'start_date' => '',           'end_date' => ''],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function save_skips_rows_with_empty_message(): void
    {
        Functions\expect('get_option')->zeroOrMoreTimes()->andReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('myvh_notice_settings', \Mockery::on(function (array $saved): bool {
                return count($saved['notices']) === 1
                    && $saved['notices'][0]['message'] === 'Valid notice';
            }))
            ->andReturn(true);

        $settings = new NoticeSettings();
        $settings->save([
            'notices' => [
                ['message' => '',             'start_date' => '', 'end_date' => ''],
                ['message' => 'Valid notice', 'start_date' => '', 'end_date' => ''],
            ],
        ]);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function save_stores_empty_array_when_no_notices_submitted(): void
    {
        Functions\expect('get_option')->zeroOrMoreTimes()->andReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('myvh_notice_settings', \Mockery::on(function (array $saved): bool {
                return isset($saved['notices']) && $saved['notices'] === [];
            }))
            ->andReturn(true);

        $settings = new NoticeSettings();
        $settings->save([]);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function save_ignores_non_array_notices_input(): void
    {
        Functions\expect('get_option')->zeroOrMoreTimes()->andReturn([]);
        Functions\expect('update_option')
            ->once()
            ->with('myvh_notice_settings', \Mockery::on(function (array $saved): bool {
                return $saved['notices'] === [];
            }))
            ->andReturn(true);

        $settings = new NoticeSettings();
        $settings->save(['notices' => 'not-an-array']);

        $this->addToAssertionCount(1);
    }
}
