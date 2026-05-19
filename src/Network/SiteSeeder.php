<?php

namespace MYVH\Network;

use MYVH\Bootstrap\Installer;
use MYVH\Rooms\RoomService;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomHoursRepository;
use MYVH\Rooms\RoomDepositRepository;
use MYVH\Venues\VenueService;
use MYVH\Venues\VenueRepository;
use MYVH\Venues\VenueHoursRepository;
use MYVH\Pricing\RoomRateService;
use MYVH\Pricing\RoomRateRepository;
use MYVH\Settings\GeneralSettings;
use MYVH\Settings\NoticeSettings;
use MYVH\Customers\CustomerService;
use MYVH\Customers\CustomerRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Availability\AvailabilityService;
use MYVH\Bookings\BookingRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Organisations\OrganisationMemberRepository;
use Psr\Log\NullLogger;

class SiteSeeder {

    private function null_logger(): NullLogger {
        return new NullLogger();
    }

    public function seed(int $blog_id, array $context = []): void {

        global $wpdb;
        switch_to_blog($blog_id);

        $org_type = $this->add_personal_organisation_type($wpdb);
        $personal_org_type = $this->add_personal_organisation($wpdb, $org_type);
        $this->add_default_organisation_type($wpdb);
        $this->add_system_customer($personal_org_type);


        // Add in the admin user as a customer too, so they can manage their own bookings, etc.
        $customer_service = $this->make_customer_service();
        $admin_user = get_user_by('email', get_bloginfo('admin_email'));
        if ($admin_user) {
            $customer_service->save(
                [
                'user_id' => $admin_user->ID,
                'email' => $admin_user->user_email,
                'name' => $admin_user->first_name . ' ' . $admin_user->last_name,
                'email_verified' => 1,
                ]);

            $this->make_client_admin_service()->add_assignment($blog_id, (int) $admin_user->ID);
            }

        //TODO: change to use context to determine what to seed, rather than hardcoding this seeding of a default venue and room for every new site.  E.g. we may want to allow some sites to start with a completely blank slate, and others to have some demo data.
        $venue_service = $this->make_venue_service();
        $venue_id = $venue_service->save([
            'name' => 'Our Venue',
            'short_name' => 'Default',
            'post_code' => 'AB1 2CD',
            'address_line1' => '123 Main Rd, Anytown',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        //TODO: change to use context to determine what to seed, rather than hardcoding this seeding of a default venue and room for every new site.  E.g. we may want to allow some sites to start with a completely blank slate, and others to have some demo data.
        if ($venue_id && !is_wp_error($venue_id)) {

            $room_service = $this->make_room_service();
            $room_id = $room_service->save([
                'name' => 'Main Hall',
                'venue_id' => $venue_id,
                'capacity' => 100,
                'description' => 'A large hall suitable for events and gatherings.',
                'opening_time' => '09:00',
                'closing_time' => '17:00',
                'allow-multi-day-bookings' => false,
                'calc-closed-hours' => false,
                'is_public' => true,
            ]);

            //Now add in room rates for the default room
            if ($room_id && !is_wp_error($room_id)) {
                $room_rate_service = $this->make_room_rate_service();
                $room_rate_service->save([
                    'room_id' => $room_id,
                    'name' => 'Standard Rate',
                    'charge_type' => 'per_hour',
                    'rate' => 20.00,
                    'description' => 'Standard hourly rate for the Main Hall.',
                    'is_active' => true,
                ]);
            }
        }

        // Settings
        $this->make_general_settings()->save([
            'portal_logo_url' => $context['logo_url'] ?? '',
            'site_label' => $context['site_label'] ?? 'My Booking System',
        ]);

        $notice_settings = $this->make_notice_settings();
        $existing_notices = $notice_settings->get('notices');

        if (!is_array($existing_notices) || empty($existing_notices)) {
            $now = function_exists('current_time') ? (int) current_time('timestamp') : time();

            $notice_settings->save([
                'notices' => [[
                    'message' => 'Welcome to the hall booking system',
                    'start_date' => '',
                    'end_date' => date('Y-m-d', $now + (14 * 86400)),
                ]],
            ]);
        }

        restore_current_blog();
    }

    protected function add_personal_organisation_type($wpdb): int {
        return Installer::add_personal_organisation_type($wpdb);
    }

    protected function add_personal_organisation($wpdb, int $org_type): int {
        return Installer::add_personal_organisation($wpdb, $org_type);
    }

    protected function add_default_organisation_type($wpdb): void {
        Installer::add_default_organisation_type($wpdb);
    }

    protected function add_system_customer(int $personal_org_type): void {
        Installer::add_system_customer($personal_org_type);
    }

    protected function make_venue_service(): VenueService {
        global $wpdb;
        return new VenueService(
            new VenueRepository($wpdb),
            new VenueHoursRepository($wpdb),
            new RoomRepository($wpdb, $this->null_logger())
        );
    }

    protected function make_room_service(): RoomService {
        global $wpdb;
        return new RoomService(
            new RoomRepository($wpdb, $this->null_logger()),
            new RoomHoursRepository($wpdb),
            $this->make_availability_service(),
            new RoomDepositRepository()
        );
    }

    // This is needed just so we can create a RoomService instance.  No functionality is used in this seeder.
    protected function make_availability_service(): AvailabilityService {
        global $wpdb;
        return new AvailabilityService(
            new BookingRepository($wpdb, $this->null_logger()),
            new RoomRepository($wpdb, $this->null_logger()),
            new RoomHoursRepository($wpdb),
            new VenueRepository($wpdb),
            new VenueHoursRepository($wpdb)
        );

    }

    protected function make_room_rate_service(): RoomRateService {
        global $wpdb;
        return new RoomRateService(
            new RoomRateRepository($wpdb),
        );
    }

    protected function make_customer_service(): CustomerService {
        global $wpdb;
        return new CustomerService(
            new CustomerRepository($wpdb, $this->null_logger()),
            new BookingRepository($wpdb, $this->null_logger()),
            new OrganisationRepository($wpdb),
            new OrganisationMemberRepository($wpdb, $this->null_logger())
        );
    }

    protected function make_client_admin_service(): ClientAdminService {
        return new ClientAdminService();
    }

    protected function make_general_settings(): GeneralSettings {
        return new GeneralSettings();
    }

    protected function make_notice_settings(): NoticeSettings {
        return new NoticeSettings();
    }
}