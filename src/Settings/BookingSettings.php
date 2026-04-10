<?php
namespace MyVH\Settings;

class BookingSettings extends SettingsBase {

    protected $option_name = 'myvh_booking_settings';
    protected $hide_from_client_admin = false;

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
                    'sanitize' => 'boolval',
                    'description' => 'If enabled, new bookings will require admin approval before they are confirmed.'
                ],

                'cancellation_allowed_days_before' => [
                    'label' => 'Cancellations allowed up to (days before booking start)',
                    'type' => 'number',
                    'default' => 7,
                    'sanitize' => 'intval',
                    'description' => 'How many days before the booking start time users are allowed to cancel their booking.'
                ],

            ]
        ],

        'buffers' => [
            'title' => 'Buffers',

            'fields' => [

                'set_up_minutes' => [
                    'label' => 'Minutes allowed for set up before a booking',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'How many free minutes are allowed for set up before a booking.'
                ],
                'tidy_up_minutes' => [
                    'label' => 'Minutes allowed for tidy up after a booking',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'How many free minutes are allowed for tidy up after a booking.'
                ],
                'show_buffer_times_separately' => [
                    'label' => 'Show buffer times in calendar as separate events',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval'
                ],
                'buffer_text' => [
                    'label' => 'Buffer event text',
                    'type' => 'text',
                    'default' => 'Buffer',
                    'sanitize' => 'sanitize_text_field'
                ]
            ]
        ]

    ];

}