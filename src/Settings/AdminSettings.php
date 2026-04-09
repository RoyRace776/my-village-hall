<?php
namespace MYVH\Settings;

class AdminSettings extends SettingsBase {

    protected $option_name = 'myvh_admin_settings';
    protected $required_capability = 'manage_options';
    protected $hide_from_client_admin = false;

    public function tab(): array {
        return [
            'key' => 'admin',
            'label' => 'Admin'
        ];
    }

    protected $schema = [

        'admin' => [

            'title' => 'Admin Settings',

            'fields' => [

                'enable_system' => [
                    'type' => 'boolean',
                    'label' => 'Enable System',
                    'default' => true,
                    'sanitize' => 'boolval'
                ],

                'delete_on_deactivate' => [
                    'type' => 'boolean',
                    'label' => 'Delete on deactivation',
                    'default' => false,
                    'sanitize' => 'boolval',
                    'description' => 'WARNING: When set, ALL data will be removed when the plugin is deactivated. USE WITH CARE'
                ],

                'enable_auditing' => [
                    'type' => 'boolean',
                    'label' => 'Enable auditing',
                    'default' => false,
                    'sanitize' => 'boolval',
                    'description' => 'When enabled, auditing features will be active.'
                ],

                'enable_caching' => [
                    'type' => 'boolean',
                    'label' => 'Enable caching',
                    'default' => false,
                    'sanitize' => 'boolval',
                    'description' => 'When enabled, caching features will be active.'
                ],
            ]

        ]

    ];

}