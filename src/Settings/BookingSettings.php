<?php
namespace MyVH\Settings;

class BookingSettings extends SettingsBase {

    protected $option_name = 'myvh_booking_settings';

    public function tab(): array {
        return
            ['key' => 'booking',
            'label' => 'Booking'];
    }

    protected $schema = [

        'general' => [
            'title' => 'General Booking Rules',

            'fields' => [

                'max_booking_days' => [
                    'label' => 'Maximum booking days ahead',
                    'type' => 'number',
                    'default' => 365,
                    'sanitize' => 'intval',
                    'description' => 'How far ahead users can book.'
                ],

                'min_notice_hours' => [
                    'label' => 'Minimum notice',
                    'type' => 'number',
                    'default' => 24,
                    'sanitize' => 'intval'
                ],

                'require_approval' => [
                    'label' => 'Require approval',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval'
                ],

            ]
        ],

        'advanced' => [
            'title' => 'Advanced',

            'fields' => [

                'allow_weekend_bookings' => [
                    'label' => 'Allow weekend bookings',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval'
                ],

                'booking_notes' => [
                    'label' => 'Booking notes',
                    'type' => 'textarea',
                    'default' => '',
                    'sanitize' => 'sanitize_textarea_field'
                ]

            ]
        ]

    ];

}