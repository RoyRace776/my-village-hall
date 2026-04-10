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

            ]

        ]

    ];

}