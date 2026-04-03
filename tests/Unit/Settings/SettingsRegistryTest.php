<?php

namespace MYVH\Tests\Unit\Settings;

use Brain\Monkey;
use Brain\Monkey\Functions;
use MYVH\Settings\SettingsBase;
use MYVH\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

class SettingsRegistryTest extends TestCase {

    protected function setUp(): void {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void {
        SettingsRegistry::reset();
        Monkey\tearDown();
        parent::tearDown();
    }

    /** @test */
    public function register_stores_group_access_metadata(): void {
        SettingsRegistry::register(
            'restricted',
            'Restricted',
            RestrictedSettingsStub::class,
            [
                'required_capability' => 'manage_options',
                'hide_from_client_admin' => true,
            ]
        );

        $groups = SettingsRegistry::groups();

        $this->assertArrayHasKey('restricted', $groups);
        $this->assertSame('manage_options', $groups['restricted']['required_capability']);
        $this->assertTrue($groups['restricted']['hide_from_client_admin']);
    }

    /** @test */
    public function restricted_group_visibility_blocks_client_admin_without_capability(): void {
        Functions\expect('user_can')
            ->twice()
            ->with(27, 'manage_options')
            ->andReturn(false);

        SettingsRegistry::register(
            'restricted',
            'Restricted',
            RestrictedSettingsStub::class,
            [
                'required_capability' => 'manage_options',
                'hide_from_client_admin' => true,
            ]
        );

        $this->assertFalse(SettingsRegistry::group_visible_to_client_admin('restricted', 27));
        $this->assertFalse(SettingsRegistry::user_can_access_group('restricted', 27));
    }

    /** @test */
    public function unrestricted_group_stays_visible_without_extra_capability(): void {
        SettingsRegistry::register('open', 'Open', OpenSettingsStub::class);

        $this->assertTrue(SettingsRegistry::group_visible_to_client_admin('open', 27));
        $this->assertTrue(SettingsRegistry::user_can_access_group('open', 27));
    }
}

class RestrictedSettingsStub extends SettingsBase {
    protected $option_name = 'myvh_test_restricted';
    protected $required_capability = 'manage_options';
    protected $hide_from_client_admin = true;

    protected $schema = [
        'general' => [
            'fields' => [
                'enabled' => [
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'boolval',
                ],
            ],
        ],
    ];

    public function tab(): array {
        return [
            'key' => 'restricted',
            'label' => 'Restricted',
        ];
    }
}

class OpenSettingsStub extends SettingsBase {
    protected $option_name = 'myvh_test_open';

    protected $schema = [
        'general' => [
            'fields' => [
                'name' => [
                    'type' => 'text',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
            ],
        ],
    ];

    public function tab(): array {
        return [
            'key' => 'open',
            'label' => 'Open',
        ];
    }
}
