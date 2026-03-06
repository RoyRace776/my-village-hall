<?php

class MYVH_Booking_Settings extends MYVH_Settings_Base {

    protected $option_name = 'myvh_booking_settings';

    protected $schema = [

        'max_booking_days' => [
            'type' => 'integer',
            'default' => 365,
            'label' => 'Maximum booking days ahead',
            'sanitize' => 'intval'
        ],

        'min_notice_hours' => [
            'type' => 'integer',
            'default' => 24,
            'label' => 'Minimum notice hours',
            'sanitize' => 'intval'
        ],

        'require_approval' => [
            'type' => 'boolean',
            'default' => true,
            'label' => 'Require approval',
            'sanitize' => 'boolval'
        ]

    ];

}