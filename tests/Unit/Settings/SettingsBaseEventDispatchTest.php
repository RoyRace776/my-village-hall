<?php

namespace MYVH\Tests\Unit\Settings;

use Brain\Monkey\Functions;
use MYVH\Events\SettingsEvents;
use MYVH\Settings\SettingsBase;
use MYVH\Tests\Unit\UnitTestCase;

class SettingsBaseEventDispatchTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\stubs([
            'sanitize_key' => static fn($v) => preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $v)),
        ]);
    }

    /** @test */
    public function save_dispatches_general_settings_saved_event(): void
    {
        Functions\expect('is_multisite')
            ->once()
            ->andReturn(false);

        Functions\expect('update_option')
            ->once()
            ->with('myvh_test_settings', ['site_label' => 'Village Hall'])
            ->andReturn(true);

        Functions\expect('get_current_user_id')
            ->once()
            ->andReturn(23);

        Functions\expect('do_action')
            ->once()
            ->with(
                'myvh_event_' . SettingsEvents::GENERAL_SAVED,
                [
                    'group' => 'general',
                    'option_name' => 'myvh_test_settings',
                    'settings' => ['site_label' => 'Village Hall'],
                    'user_id' => 23,
                ]
            );

        $settings = new GeneralSettingsDispatchStub();
        $settings->save(['site_label' => 'Village Hall']);

        $this->addToAssertionCount(1);
    }
}

class GeneralSettingsDispatchStub extends SettingsBase
{
    protected $option_name = 'myvh_test_settings';

    protected $schema = [
        'general' => [
            'fields' => [
                'site_label' => [
                    'type' => 'text',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
            ],
        ],
    ];

    public function tab(): array
    {
        return [
            'key' => 'general',
            'label' => 'General',
        ];
    }
}