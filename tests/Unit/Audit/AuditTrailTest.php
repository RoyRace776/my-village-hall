<?php

namespace {
    if (!class_exists('wpdb')) {
        class wpdb {
        }
    }
}

namespace MYVH\Tests\Unit\Audit {

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Audit\AuditTrail;
use MYVH\Bookings\BookingAddonRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Tests\Unit\UnitTestCase;

class AuditTrailTest extends UnitTestCase {
    public function test_records_create_for_main_entity_repository(): void {
        Functions\stubs([
            'myvh_setting' => true,
            'sanitize_key' => fn($v) => (string) $v,
            'wp_json_encode' => fn($v) => json_encode($v),
            'current_time' => '2026-04-11 12:00:00',
            'get_current_user_id' => 99,
            'wp_doing_ajax' => false,
            'is_admin' => true,
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('insert')
            ->once()
            ->with(
                'wp_myvh_audit_log',
                Mockery::on(function (array $data): bool {
                    return ($data['Action'] ?? '') === 'create'
                        && ($data['EntityType'] ?? '') === 'booking'
                        && intval($data['EntityId'] ?? 0) === 42
                        && intval($data['ActorUserId'] ?? 0) === 99
                        && ($data['Origin'] ?? '') === 'dashboard';
                }),
                Mockery::type('array')
            )
            ->andReturn(1);

        $repository = new BookingRepository($wpdb);

        AuditTrail::record_repository_event($repository, 'create', [
            'Id' => 42,
            'Name' => 'Hall booking',
        ]);
    }

    public function test_skips_non_main_entity_repository(): void {
        Functions\stubs([
            'myvh_setting' => true,
            'sanitize_key' => fn($v) => (string) $v,
        ]);

        $wpdb = Mockery::mock('wpdb');
        $wpdb->prefix = 'wp_';

        $wpdb->shouldReceive('insert')->never();

        $repository = new BookingAddonRepository($wpdb);

        AuditTrail::record_repository_event($repository, 'create', [
            'Id' => 7,
            'Name' => 'Tea Urn',
        ]);
    }
}

}
