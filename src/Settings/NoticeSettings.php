<?php
namespace MYVH\Settings;

class NoticeSettings extends SettingsBase {

    protected $option_name = 'myvh_notice_settings';
    protected $required_capability = 'manage_options';
    protected $hide_from_client_admin = true;

    public function tab(): array {
        return [
            'key'   => 'notices',
            'label' => 'Hall Notices',
        ];
    }

    protected $schema = [

        'notices' => [

            'title' => 'Hall Notices',

            'fields' => [

                'notices' => [
                    'type'    => 'notices',
                    'label'   => 'Notices',
                    'default' => [],
                    'description' => 'Add notices to display in the portal. Leave Start Date empty to show from now; leave End Date empty to show indefinitely.',
                ],

            ],

        ],

    ];

}
