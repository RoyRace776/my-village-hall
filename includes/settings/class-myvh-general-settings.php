<?php

class MYVH_General_Settings extends MYVH_Settings_Base {

    protected $option_name = 'myvh_general_settings';

    public function tab() {
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

                'enable_system' => [
                    'type' => 'boolean',
                    'label' => 'Enable System',
                    'default' => true,
                    'sanitize' => 'boolval'
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
                ]

            ]

        ]

    ];

}