<?php

namespace MYVH\Network;

use MYVH\Bootstrap\Installer;
use MYVH\Rooms\RoomService;
use MYVH\Venues\VenueService;
use MYVH\Pricing\RoomRateService;
use MYVH\Settings\GeneralSettings;
use MYVH\Customers\CustomerService;

class SiteSeeder {

    public function seed(int $blog_id, array $context = []): void {

        global $wpdb;
        switch_to_blog($blog_id);

        $org_type = Installer::add_personal_organisation_type($wpdb);
        $personal_org_type = Installer::add_personal_organisation($wpdb, $org_type);
        Installer::add_default_organisation_type($wpdb);
        Installer::add_system_customer($personal_org_type);


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
        (new GeneralSettings())->save([
            'portal_logo_url' => $context['logo_url'] ?? '',
        ]);

        restore_current_blog();
    }

    private function make_venue_service(): VenueService {
        global $wpdb;
        return new VenueService(
            new \MYVH\Venues\VenueRepository($wpdb),
            new \MYVH\Venues\VenueHoursRepository($wpdb),
            new \MYVH\Rooms\RoomRepository($wpdb)
        );
    }

    private function make_room_service(): RoomService {
        global $wpdb;
        return new RoomService(
            new \MYVH\Rooms\RoomRepository($wpdb),
            new \MYVH\Rooms\RoomHoursRepository($wpdb),
            $this->make_availability_service()
        );
    }

    // This is needed just so we can create a RoomService instance.  No functionality is used in this seeder.
    private function make_availability_service(): \MYVH\Availability\AvailabilityService {
        global $wpdb;
        return new \MYVH\Availability\AvailabilityService(
            new \MYVH\Bookings\BookingRepository($wpdb),
            new \MYVH\Rooms\RoomRepository($wpdb),
            new \MYVH\Rooms\RoomHoursRepository($wpdb),
            new \MYVH\Venues\VenueRepository($wpdb),
            new \MYVH\Venues\VenueHoursRepository($wpdb)
        );

    }

    private function make_room_rate_service(): RoomRateService {
        global $wpdb;
        return new RoomRateService(
            new \MYVH\Pricing\RoomRateRepository($wpdb),
            new \MYVH\Customers\CustomerRepository($wpdb)
        );
    }

    private function make_customer_service(): CustomerService {
        global $wpdb;
        return new CustomerService(
            new \MYVH\Customers\CustomerRepository($wpdb),
            new \MYVH\Bookings\BookingRepository($wpdb),
            new \MYVH\Organisations\OrganisationRepository($wpdb),
            new \MYVH\Organisations\OrganisationMemberRepository($wpdb)
        );
    }
}