<?php

namespace MYVH\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use MYVH\Settings\NoticeSettings;
use MYVH\Settings\SettingsBase;
use MYVH\Settings\SettingsRegistry;
use MYVH\Tests\Unit\UnitTestCase;

/**
 * Tests for myvh_get_active_notices() helper.
 *
 * The helper filters stored notices by comparing the current timestamp against
 * each notice's start_date / end_date.  Rules:
 *   - start_date empty → show from now
 *   - end_date   empty → show forever
 */
class ActiveNoticesHelperTest extends UnitTestCase
{
    /** Fixed "now" used across every test: 2026-05-02 12:00:00 UTC */
    private const NOW = 1746187200;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure the helper file is loaded.
        require_once MYVH_PLUGIN_DIR . 'src/Settings/settings-helper.php';

        // Reset the registry so each test is isolated.
        SettingsRegistry::reset();

        // current_time() is a WordPress function – stub it to return our fixed NOW.
        Functions\stubs([
            'current_time' => fn() => self::NOW,
            'is_multisite'  => false,
        ]);
    }

    protected function tearDown(): void
    {
        SettingsRegistry::reset();
        parent::tearDown();
    }

    // ── helper: register notices with preset rows ─────────────────────────

    private function registerNoticesWith(array $rows): void
    {
        Functions\expect('get_option')
            ->zeroOrMoreTimes()
            ->andReturn(['notices' => $rows]);

        SettingsRegistry::register('notices', 'Hall Notices', NoticeSettings::class);
    }

    // ── tests ─────────────────────────────────────────────────────────────

    /** @test */
    public function returns_empty_array_when_no_notices_group_registered(): void
    {
        // No 'notices' group in registry.
        $this->assertSame([], myvh_get_active_notices());
    }

    /** @test */
    public function returns_empty_array_when_notices_option_is_empty(): void
    {
        $this->registerNoticesWith([]);

        $this->assertSame([], myvh_get_active_notices());
    }

    /** @test */
    public function notice_with_no_dates_is_always_active(): void
    {
        $this->registerNoticesWith([
            ['message' => 'Always visible', 'start_date' => '', 'end_date' => ''],
        ]);

        $result = myvh_get_active_notices();

        $this->assertCount(1, $result);
        $this->assertSame('Always visible', $result[0]['message']);
    }

    /** @test */
    public function notice_not_yet_started_is_excluded(): void
    {
        // start_date is tomorrow relative to NOW
        $future = date('Y-m-d', self::NOW + 86400);

        $this->registerNoticesWith([
            ['message' => 'Future notice', 'start_date' => $future, 'end_date' => ''],
        ]);

        $this->assertSame([], myvh_get_active_notices());
    }

    /** @test */
    public function notice_whose_start_date_is_today_is_active(): void
    {
        // start_date equals NOW date
        $today = date('Y-m-d', self::NOW);

        $this->registerNoticesWith([
            ['message' => 'Starts today', 'start_date' => $today, 'end_date' => ''],
        ]);

        $result = myvh_get_active_notices();

        $this->assertCount(1, $result);
    }

    /** @test */
    public function notice_past_end_date_is_excluded(): void
    {
        // end_date was yesterday
        $yesterday = date('Y-m-d', self::NOW - 86400);

        $this->registerNoticesWith([
            ['message' => 'Expired notice', 'start_date' => '', 'end_date' => $yesterday],
        ]);

        $this->assertSame([], myvh_get_active_notices());
    }

    /** @test */
    public function notice_within_start_and_end_dates_is_active(): void
    {
        $yesterday = date('Y-m-d', self::NOW - 86400);
        $tomorrow  = date('Y-m-d', self::NOW + 86400);

        $this->registerNoticesWith([
            ['message' => 'In range', 'start_date' => $yesterday, 'end_date' => $tomorrow],
        ]);

        $result = myvh_get_active_notices();

        $this->assertCount(1, $result);
        $this->assertSame('In range', $result[0]['message']);
    }

    /** @test */
    public function only_active_notices_are_returned_from_a_mixed_set(): void
    {
        $yesterday = date('Y-m-d', self::NOW - 86400);
        $tomorrow  = date('Y-m-d', self::NOW + 86400);
        $future    = date('Y-m-d', self::NOW + 2 * 86400);

        $this->registerNoticesWith([
            ['message' => 'Active – no dates',  'start_date' => '',        'end_date' => ''],
            ['message' => 'Active – in window', 'start_date' => $yesterday,'end_date' => $tomorrow],
            ['message' => 'Expired',            'start_date' => '',        'end_date' => $yesterday],
            ['message' => 'Future',             'start_date' => $future,   'end_date' => ''],
        ]);

        $result = myvh_get_active_notices();

        $this->assertCount(2, $result);
        $this->assertSame('Active – no dates',  $result[0]['message']);
        $this->assertSame('Active – in window', $result[1]['message']);
    }

    /** @test */
    public function result_is_re_indexed_sequentially(): void
    {
        $yesterday = date('Y-m-d', self::NOW - 86400);
        $tomorrow  = date('Y-m-d', self::NOW + 86400);
        $future    = date('Y-m-d', self::NOW + 2 * 86400);

        $this->registerNoticesWith([
            ['message' => 'Future',  'start_date' => $future,    'end_date' => ''],
            ['message' => 'Current', 'start_date' => $yesterday, 'end_date' => $tomorrow],
        ]);

        $result = myvh_get_active_notices();

        $this->assertCount(1, $result);
        $this->assertArrayHasKey(0, $result);
        $this->assertSame('Current', $result[0]['message']);
    }
}
