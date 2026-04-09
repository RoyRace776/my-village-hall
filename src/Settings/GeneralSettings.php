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

                'calendar_date_format' => [
                    'type'     => 'select',
                    'label'    => 'Calendar Date Format',
                    'default'  => 'd MMM',
                    'sanitize' => 'sanitize_text_field',
                    'options'  => [
                        'd MMM'      => '15 Jan',
                        'ddd d MMM'  => 'Mon 15 Jan',
                        'd MMMM'     => '15 January',
                        'ddd d MMMM' => 'Mon 15 January',
                        'd/M/yyyy'   => '15/1/2024',
                        'M/d/yyyy'   => '1/15/2024',
                        'yyyy-MM-dd' => '2024-01-15',
                    ],
                ],

                'public_calendar_booking_label' => [
                    'type' => 'text',
                    'label' => 'Public Calendar Private Label',
                    'default' => 'Private booking',
                    'sanitize' => 'sanitize_text_field',
                    'description' => 'Label shown for bookings that are not marked public.',
                ],

                'scheduler_orientation' => [
                    'type'        => 'select',
                    'label'       => 'Scheduler Orientation',
                    'default'     => 'horizontal',
                    'sanitize'    => 'sanitize_key',
                    'options'     => [
                        'horizontal' => 'Horizontal – rooms as rows, time across the top',
                        'vertical'   => 'Vertical – date/time down the side, rooms across the top',
                    ],
                    'description' => 'Applies to Scheduler Day, Week and Month views.',
                ],

            ]

        ]

    ];

}