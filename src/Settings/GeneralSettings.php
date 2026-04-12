<?php
namespace MYVH\Settings;

class GeneralSettings extends SettingsBase {

    protected $option_name = 'myvh_general_settings';
    protected $required_capability = 'manage_options';
    protected $hide_from_client_admin = false;

    public function tab(): array {
        return [
            'key' => 'general',
            'label' => 'General'
        ];
    }

    protected $schema = [

        'general' => [

            'title' => 'General Settings',

            'fields' => [

                'site_label' => [
                    'type' => 'text',
                    'label' => 'Site Label',
                    'default' => 'My Booking System',
                    'sanitize' => 'sanitize_text_field'
                ],

                'items_per_page' => [
                    'type' => 'integer',
                    'label' => 'Items Per Page',
                    'default' => 10,
                    'sanitize' => 'intval'
                ],

                'admin_email' => [
                    'type' => 'text',
                    'label' => 'Admin Email',
                    'default' => '',
                    'sanitize' => 'sanitize_email'
                ],

                'portal_bookings_date_format' => [
                    'type' => 'select',
                    'label' => 'Portal Bookings Date Format',
                    'default' => 'd MMM',
                    'sanitize' => 'sanitize_text_field',
                    'options' => [
                        'd MMM'      => '15 Jan',
                        'ddd d MMM'  => 'Mon 15 Jan',
                        'd MMMM'     => '15 January',
                        'ddd d MMMM' => 'Mon 15 January',
                        'd/M/yyyy'   => '15/1/2024',
                        'M/d/yyyy'   => '1/15/2024',
                        'yyyy-MM-dd' => '2024-01-15',
                    ],
                ],

            ]

        ]

    ];

}