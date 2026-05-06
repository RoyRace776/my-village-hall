<?php

namespace MYVH\Tests\Unit\Network;

use Brain\Monkey\Functions;
use MYVH\Customers\CustomerService;
use MYVH\Network\SiteSeeder;
use MYVH\Portal\ClientAdminService;
use MYVH\Pricing\RoomRateService;
use MYVH\Rooms\RoomService;
use MYVH\Settings\GeneralSettings;
use MYVH\Settings\NoticeSettings;
use MYVH\Tests\Unit\UnitTestCase;
use MYVH\Venues\VenueService;
use WP_Error;

class SiteSeederTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        global $wpdb;
        $wpdb = new \wpdb('', '', '', '');

        Functions\stubs([
            'is_wp_error' => static fn($value) => $value instanceof WP_Error,
            'get_bloginfo' => static fn($key) => $key === 'admin_email' ? 'admin@example.com' : '',
            'current_time' => static fn() => 1700000000,
        ]);
    }

    /** @test */
    public function seed_runs_happy_path_and_saves_default_notice_when_none_exist(): void {
        $customer_service = \Mockery::mock(CustomerService::class);
        $customer_service->shouldReceive('save')->once()->with(\Mockery::on(static function (array $data): bool {
            return $data['user_id'] === 77
                && $data['email'] === 'admin@example.com'
                && $data['name'] === 'Site Admin'
                && $data['email_verified'] === 1;
        }));

        $venue_service = \Mockery::mock(VenueService::class);
        $venue_service->shouldReceive('save')->once()->with(\Mockery::on(static function (array $data): bool {
            return $data['name'] === 'Our Venue' && $data['short_name'] === 'Default';
        }))->andReturn(31);

        $room_service = \Mockery::mock(RoomService::class);
        $room_service->shouldReceive('save')->once()->with(\Mockery::on(static function (array $data): bool {
            return $data['name'] === 'Main Hall' && $data['venue_id'] === 31;
        }))->andReturn(99);

        $room_rate_service = \Mockery::mock(RoomRateService::class);
        $room_rate_service->shouldReceive('save')->once()->with(\Mockery::on(static function (array $data): bool {
            return $data['room_id'] === 99
                && $data['name'] === 'Standard Rate'
                && $data['charge_type'] === 'per_hour';
        }));

        $client_admin_service = \Mockery::mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('add_assignment')->once()->with(123, 77);

        $general_settings = \Mockery::mock(GeneralSettings::class);
        $general_settings->shouldReceive('save')->once()->with([
            'portal_logo_url' => 'https://example.test/logo.png',
            'site_label' => 'Village Hall Portal',
        ]);

        $notice_settings = \Mockery::mock(NoticeSettings::class);
        $notice_settings->shouldReceive('get')->once()->with('notices')->andReturn([]);
        $notice_settings->shouldReceive('save')->once()->with(\Mockery::on(static function (array $payload): bool {
            if (!isset($payload['notices'][0])) {
                return false;
            }

            $notice = $payload['notices'][0];
            return $notice['message'] === 'Welcome to the hall booking system'
                && $notice['start_date'] === ''
                && is_string($notice['end_date'])
                && $notice['end_date'] !== '';
        }));

        $seeder = new TestableSiteSeeder(
            $customer_service,
            $venue_service,
            $room_service,
            $room_rate_service,
            $client_admin_service,
            $general_settings,
            $notice_settings
        );

        Functions\expect('switch_to_blog')->once()->with(123);
        Functions\expect('restore_current_blog')->once();
        Functions\expect('get_user_by')->once()->with('email', 'admin@example.com')->andReturn((object) [
            'ID' => 77,
            'user_email' => 'admin@example.com',
            'first_name' => 'Site',
            'last_name' => 'Admin',
        ]);

        $seeder->seed(123, [
            'logo_url' => 'https://example.test/logo.png',
            'site_label' => 'Village Hall Portal',
        ]);

        $this->assertSame(1, $seeder->addPersonalOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addPersonalOrganisationCalls);
        $this->assertSame(1, $seeder->addDefaultOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addSystemCustomerCalls);
    }

    /** @test */
    public function seed_skips_admin_customer_and_assignment_when_no_admin_user_exists(): void {
        $customer_service = \Mockery::mock(CustomerService::class);
        $customer_service->shouldReceive('save')->never();

        $venue_service = \Mockery::mock(VenueService::class);
        $venue_service->shouldReceive('save')->once()->andReturn(55);

        $room_service = \Mockery::mock(RoomService::class);
        $room_service->shouldReceive('save')->once()->andReturn(56);

        $room_rate_service = \Mockery::mock(RoomRateService::class);
        $room_rate_service->shouldReceive('save')->once();

        $client_admin_service = \Mockery::mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('add_assignment')->never();

        $general_settings = \Mockery::mock(GeneralSettings::class);
        $general_settings->shouldReceive('save')->once();

        $notice_settings = \Mockery::mock(NoticeSettings::class);
        $notice_settings->shouldReceive('get')->once()->with('notices')->andReturn([
            ['message' => 'Existing notice'],
        ]);
        $notice_settings->shouldReceive('save')->never();

        $seeder = new TestableSiteSeeder(
            $customer_service,
            $venue_service,
            $room_service,
            $room_rate_service,
            $client_admin_service,
            $general_settings,
            $notice_settings
        );

        Functions\expect('switch_to_blog')->once()->with(9);
        Functions\expect('restore_current_blog')->once();
        Functions\expect('get_user_by')->once()->with('email', 'admin@example.com')->andReturn(false);

        $seeder->seed(9);

        $this->assertSame(1, $seeder->addPersonalOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addPersonalOrganisationCalls);
        $this->assertSame(1, $seeder->addDefaultOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addSystemCustomerCalls);
    }

    /** @test */
    public function seed_does_not_create_room_or_rate_when_venue_creation_fails(): void {
        $customer_service = \Mockery::mock(CustomerService::class);
        $customer_service->shouldReceive('save')->never();

        $venue_service = \Mockery::mock(VenueService::class);
        $venue_service->shouldReceive('save')->once()->andReturn(new WP_Error('venue_error', 'venue failed'));

        $room_service = \Mockery::mock(RoomService::class);
        $room_service->shouldReceive('save')->never();

        $room_rate_service = \Mockery::mock(RoomRateService::class);
        $room_rate_service->shouldReceive('save')->never();

        $client_admin_service = \Mockery::mock(ClientAdminService::class);
        $client_admin_service->shouldReceive('add_assignment')->never();

        $general_settings = \Mockery::mock(GeneralSettings::class);
        $general_settings->shouldReceive('save')->once();

        $notice_settings = \Mockery::mock(NoticeSettings::class);
        $notice_settings->shouldReceive('get')->once()->with('notices')->andReturn([]);
        $notice_settings->shouldReceive('save')->once();

        $seeder = new TestableSiteSeeder(
            $customer_service,
            $venue_service,
            $room_service,
            $room_rate_service,
            $client_admin_service,
            $general_settings,
            $notice_settings
        );

        Functions\expect('switch_to_blog')->once()->with(22);
        Functions\expect('restore_current_blog')->once();
        Functions\expect('get_user_by')->once()->with('email', 'admin@example.com')->andReturn(false);

        $seeder->seed(22);

        $this->assertSame(1, $seeder->addPersonalOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addPersonalOrganisationCalls);
        $this->assertSame(1, $seeder->addDefaultOrganisationTypeCalls);
        $this->assertSame(1, $seeder->addSystemCustomerCalls);
    }
}

class TestableSiteSeeder extends SiteSeeder {
    public int $addPersonalOrganisationTypeCalls = 0;
    public int $addPersonalOrganisationCalls = 0;
    public int $addDefaultOrganisationTypeCalls = 0;
    public int $addSystemCustomerCalls = 0;

    public function __construct(
        private CustomerService $customerService,
        private VenueService $venueService,
        private RoomService $roomService,
        private RoomRateService $roomRateService,
        private ClientAdminService $clientAdminService,
        private GeneralSettings $generalSettings,
        private NoticeSettings $noticeSettings
    ) {
    }

    protected function add_personal_organisation_type($wpdb): int {
        $this->addPersonalOrganisationTypeCalls++;
        return 7;
    }

    protected function add_personal_organisation($wpdb, int $org_type): int {
        $this->addPersonalOrganisationCalls++;
        return 8;
    }

    protected function add_default_organisation_type($wpdb): void {
        $this->addDefaultOrganisationTypeCalls++;
    }

    protected function add_system_customer(int $personal_org_type): void {
        $this->addSystemCustomerCalls++;
    }

    protected function make_customer_service(): CustomerService {
        return $this->customerService;
    }

    protected function make_venue_service(): VenueService {
        return $this->venueService;
    }

    protected function make_room_service(): RoomService {
        return $this->roomService;
    }

    protected function make_room_rate_service(): RoomRateService {
        return $this->roomRateService;
    }

    protected function make_client_admin_service(): ClientAdminService {
        return $this->clientAdminService;
    }

    protected function make_general_settings(): GeneralSettings {
        return $this->generalSettings;
    }

    protected function make_notice_settings(): NoticeSettings {
        return $this->noticeSettings;
    }
}
