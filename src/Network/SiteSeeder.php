<?php

namespace MYVH\Network;

use MYVH\Addons\AddonRepository;
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
use WP_Error;
use Psr\Log\NullLogger;

class SiteSeeder {

    private function null_logger(): NullLogger {
        return new NullLogger();
    }

    private function logger(): NullLogger {
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
        $admin_user = null;

        $context_user_id = isset($context['user_id']) ? (int) $context['user_id'] : 0;
        if ($context_user_id > 0) {
            $admin_user = get_user_by('id', $context_user_id);
        }

        if (!$admin_user) {
            $admin_email = (string) get_bloginfo('admin_email');
            if ($admin_email !== '') {
                $admin_user = get_user_by('email', $admin_email);
            }
        }

        if ($admin_user) {
            $customer_payload = [
                'user_id' => $admin_user->ID,
                'email' => $admin_user->user_email,
                'name' => $admin_user->first_name . ' ' . $admin_user->last_name,
                'email_verified' => 1,
            ];

            $customer_result = $customer_service->save($customer_payload);

            if (is_wp_error($customer_result)) {
                $this->logger()->warning('Admin customer save failed during provisioning; attempting existing-customer fallback.', [
                    'blog_id' => $blog_id,
                    'user_id' => (int) $admin_user->ID,
                    'email' => (string) $admin_user->user_email,
                    'error' => $customer_result instanceof WP_Error ? $customer_result->get_error_message() : '',
                ]);

                $existing_customer = $customer_service->get_by_email((string) $admin_user->user_email);
                $existing_customer_id = isset($existing_customer['Id']) ? (int) $existing_customer['Id'] : 0;

                if ($existing_customer_id > 0) {
                    $customer_payload['customer_id'] = $existing_customer_id;
                    $fallback_result = $customer_service->save($customer_payload);

                    if (is_wp_error($fallback_result)) {
                        $this->logger()->error('Admin customer fallback update failed during provisioning.', [
                            'blog_id' => $blog_id,
                            'customer_id' => $existing_customer_id,
                            'user_id' => (int) $admin_user->ID,
                            'email' => (string) $admin_user->user_email,
                            'error' => $fallback_result instanceof WP_Error ? $fallback_result->get_error_message() : '',
                        ]);
                    }
                }
            }

            $this->make_client_admin_service()->add_assignment($blog_id, (int) $admin_user->ID);
            }

        $this->seed_booking_setup($context);

        // Settings
        $site_label = sanitize_text_field((string) ($context['site_label'] ?? $context['site_name'] ?? ''));
        if ($site_label === '') {
            $site_label = 'My Booking System';
        }

        $this->make_general_settings()->save([
            'portal_logo_url' => $context['logo_url'] ?? '',
            'site_label' => $site_label,
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

    protected function make_addon_repository(): AddonRepository {
        global $wpdb;
        return new AddonRepository($wpdb, $this->null_logger());
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

    private function seed_booking_setup(array $context): void {
        $setup = is_array($context['setup'] ?? null) ? $context['setup'] : [];

        if (empty($setup['venue']) || empty($setup['rooms']) || empty($setup['pricing'])) {
            $this->seed_default_booking_setup();
            return;
        }

        $venue = is_array($setup['venue']) ? $setup['venue'] : [];
        $venue_opening_time = (string) ($venue['opening_time'] ?? '08:00');
        $venue_closing_time = (string) ($venue['closing_time'] ?? '22:00');

        $venue_id = $this->make_venue_service()->save([
            'name' => (string) ($venue['name'] ?? 'Our Venue'),
            'short_name' => (string) ($venue['short_name'] ?? 'Default'),
            'post_code' => (string) ($venue['post_code'] ?? ''),
            'address_line1' => (string) ($venue['address_line1'] ?? ''),
            'contact_email' => (string) ($venue['email'] ?? $venue['contact_email'] ?? ''),
            'opening_time' => $venue_opening_time,
            'closing_time' => $venue_closing_time,
        ]);

        if (!$venue_id || is_wp_error($venue_id)) {
            return;
        }

        $room_map = $this->seed_rooms_from_setup((int) $venue_id, $setup['rooms'], $venue_opening_time, $venue_closing_time);
        if (empty($room_map)) {
            return;
        }

        $this->seed_pricing_from_setup($room_map, $setup['pricing']);
        $this->seed_addons_from_setup((int) $venue_id, $room_map, $setup['addons'] ?? []);
    }

    private function seed_default_booking_setup(): void {
        $venue_id = $this->make_venue_service()->save([
            'name' => 'Our Venue',
            'short_name' => 'Default',
            'post_code' => 'AB1 2CD',
            'address_line1' => '123 Main Rd, Anytown',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        if (!$venue_id || is_wp_error($venue_id)) {
            return;
        }

        $room_id = $this->make_room_service()->save([
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

        if (!$room_id || is_wp_error($room_id)) {
            return;
        }

        $this->make_room_rate_service()->save([
            'room_id' => $room_id,
            'name' => 'Standard Rate',
            'charge_type' => 'per_hour',
            'rate' => 20.00,
            'minimum_hours' => 1,
            'description' => 'Standard hourly rate for the Main Hall.',
            'is_active' => true,
        ]);
    }

    /**
     * @param array<int, array<string, mixed>>|mixed $rooms
     * @return array<string|int, int>
     */
    private function seed_rooms_from_setup(int $venue_id, mixed $rooms, string $venue_opening_time, string $venue_closing_time): array {
        if (!is_array($rooms)) {
            return [];
        }

        $room_service = $this->make_room_service();
        $room_map = [];

        foreach (array_slice(array_values($rooms), 0, 3) as $index => $room) {
            if (!is_array($room) || trim((string) ($room['name'] ?? '')) === '') {
                continue;
            }

            $room_id = $room_service->save([
                'name' => (string) $room['name'],
                'venue_id' => $venue_id,
                'capacity' => isset($room['capacity']) ? (int) $room['capacity'] : 0,
                'description' => (string) ($room['description'] ?? ''),
                'opening_time' => (string) ($room['opening_time'] ?? $venue_opening_time),
                'closing_time' => (string) ($room['closing_time'] ?? $venue_closing_time),
                'allow-multi-day-bookings' => !empty($room['allow_multi_day_bookings']),
                'calc-closed-hours' => !empty($room['calc_closed_hours']),
                'is-public' => array_key_exists('is_public', $room) ? !empty($room['is_public']) : true,
            ]);

            if (!$room_id || is_wp_error($room_id)) {
                continue;
            }

            $room_key = $room['key'] ?? $room['id'] ?? $index;
            $room_map[$room_key] = (int) $room_id;
            $room_map[$index] = (int) $room_id;
        }

        return $room_map;
    }

    /**
     * @param array<string|int, int> $room_map
     * @param array<int, array<string, mixed>>|mixed $pricing_rows
     */
    private function seed_pricing_from_setup(array $room_map, mixed $pricing_rows): void {
        if (!is_array($pricing_rows)) {
            return;
        }

        $room_rate_service = $this->make_room_rate_service();

        foreach ($pricing_rows as $index => $pricing) {
            if (!is_array($pricing)) {
                continue;
            }

            $room_reference = $pricing['room_key'] ?? $pricing['room_id'] ?? $pricing['room_index'] ?? $index;
            $room_id = isset($room_map[$room_reference]) ? (int) $room_map[$room_reference] : 0;
            $hourly_rate = isset($pricing['hourly_rate']) ? (float) $pricing['hourly_rate'] : (float) ($pricing['rate'] ?? 0);

            if ($room_id <= 0 || $hourly_rate <= 0) {
                continue;
            }

            $rate_name = trim((string) ($pricing['name'] ?? ''));
            if ($rate_name === '') {
                $rate_name = 'Standard Rate';
            }

            $room_rate_service->save([
                'room_id' => $room_id,
                'name' => $rate_name,
                'charge_type' => 'per_hour',
                'rate' => $hourly_rate,
                'minimum_hours' => isset($pricing['minimum_hours']) ? (float) $pricing['minimum_hours'] : 1,
                'description' => (string) ($pricing['description'] ?? ''),
                'is_active' => true,
            ]);
        }
    }

    /**
     * @param array<string|int, int> $room_map
     * @param array<int, array<string, mixed>>|mixed $addons
     */
    private function seed_addons_from_setup(int $venue_id, array $room_map, mixed $addons): void {
        if (!is_array($addons) || $addons === []) {
            return;
        }

        $addon_repository = $this->make_addon_repository();

        foreach ($addons as $index => $addon) {
            if (!is_array($addon) || trim((string) ($addon['name'] ?? '')) === '') {
                continue;
            }

            $price = isset($addon['price']) ? (float) $addon['price'] : 0.0;
            if ($price < 0) {
                continue;
            }

            $room_reference = $addon['room_key'] ?? $addon['room_id'] ?? $addon['room_index'] ?? null;
            $room_id = $room_reference !== null && isset($room_map[$room_reference])
                ? (int) $room_map[$room_reference]
                : null;

            $addon_repository->create([
                'Name' => (string) $addon['name'],
                'Description' => (string) ($addon['description'] ?? ''),
                'Price' => $price,
                'ChargeType' => (string) ($addon['charge_type'] ?? 'fixed'),
                'RoomId' => $room_id,
                'VenueId' => $venue_id,
                'IsActive' => 1,
                'DisplayOrder' => isset($addon['display_order']) ? (int) $addon['display_order'] : $index,
            ]);
        }
    }
}