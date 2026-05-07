<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;

class DashboardTemplateTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'wp_get_current_user' => static function () {
                return (object) ['display_name' => 'Test User'];
            },
            'myvh_setting' => static fn($key, $default = null) => $default,
            'myvh_format_date_with_pattern' => static fn($date_value, $pattern, $fallback = 'M j') => (string) $date_value,
            'sanitize_html_class' => static fn($value) => preg_replace('/[^A-Za-z0-9_-]/', '', (string) $value),
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_html_e' => static function ($value, $domain = null) {
                echo (string) $value;
            },
            'myvh_get_active_notices' => static fn() => [],
            'wp_trim_words' => static function ($text, $num_words = 55, $more = '...') {
                $text = trim((string) $text);
                if ($text === '') {
                    return '';
                }

                $words = preg_split('/\s+/', $text) ?: [];
                if (count($words) <= (int) $num_words) {
                    return $text;
                }

                return implode(' ', array_slice($words, 0, (int) $num_words)) . $more;
            },
        ]);
    }

    /** @test */
    public function member_dashboard_hides_system_organisations(): void {
        $html = $this->render_member_dashboard([
            'member_organisations' => [
                ['Name' => 'Personal Booking Org', 'IsSystem' => 1],
                ['Name' => 'Community Group', 'IsSystem' => 0],
            ],
        ]);

        $this->assertStringContainsString('My Organisations', $html);
        $this->assertStringContainsString('Community Group', $html);
        $this->assertStringNotContainsString('Personal Booking Org', $html);
    }

    /** @test */
    public function previous_booking_only_considers_last_month_window(): void {
        $html = $this->render_member_dashboard([
            'groups' => [
                [
                    'bookings' => [
                        $this->make_booking(101, '-40 days', 'Old Hall'),
                        $this->make_booking(102, '+5 days', 'Future Hall'),
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('No previous booking found.', $html);
        $this->assertStringContainsString('Future Hall', $html);
        $this->assertStringNotContainsString('Old Hall', $html);
    }

    private function render_member_dashboard(array $overrides = []): string {
        $groups = $overrides['groups'] ?? [
            [
                'bookings' => [
                    $this->make_booking(1, '+2 days', 'Main Hall'),
                ],
            ],
        ];
        $customer = $overrides['customer'] ?? ['Id' => 33];
        $is_client_admin = false;
        $member_organisations = $overrides['member_organisations'] ?? [];
        $dashboard_unpaid_invoices = $overrides['dashboard_unpaid_invoices'] ?? [];
        $can_delete_booking = static fn(array $booking): bool => false;

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/dashboard.php';
        return (string) ob_get_clean();
    }

    private function make_booking(int $id, string $date_offset, string $room_name): array {
        $date = date('Y-m-d', strtotime($date_offset));

        return [
            'Id' => $id,
            'StartDate' => $date,
            'StartTime' => '09:00:00',
            'EndTime' => '10:00:00',
            'Status' => 'confirmed',
            'RoomName' => $room_name,
            'Description' => 'Sample booking description for dashboard tests',
        ];
    }
}
