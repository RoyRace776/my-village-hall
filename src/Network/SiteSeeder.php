<?php

namespace MYVH\Network;

use MYVH\Bootstrap\Installer;

class SiteSeeder {

    public function seed(int $blog_id, array $context = []): void {

        global $wpdb;
        switch_to_blog($blog_id);

        Installer::add_system_customer();
        $org_type = Installer::add_personal_organisation_type($wpdb);
        Installer::add_personal_organisation($wpdb, $org_type);
        Installer::add_default_organisation_type($wpdb);

        //TODO: more seeding - e.g. default rooms, settings, etc. You can also trigger custom hooks here for your system.

        // // Example: insert default rooms
        // $wpdb->insert(
        //     $wpdb->prefix . 'myvh_rooms',
        //     [
        //         'name' => 'Main Hall',
        //         'capacity' => 100,
        //     ]
        // );

        // $wpdb->insert(
        //     $wpdb->prefix . 'myvh_rooms',
        //     [
        //         'name' => 'Meeting Room',
        //         'capacity' => 20,
        //     ]
        // );

        // // Example: settings
        // update_option('myvh_settings', [
        //     'currency' => 'GBP',
        //     'timezone' => 'Europe/London',
        // ]);

        restore_current_blog();

    }
}