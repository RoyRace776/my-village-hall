<?php

namespace MYVH\Tests\Unit\Settings;

use MYVH\Settings\GeneralSettings;
use PHPUnit\Framework\TestCase;

class GeneralSettingsTest extends TestCase {

    /** @test */
    public function portal_bookings_date_format_field_is_defined_with_independent_options(): void {
        $schema = (new GeneralSettings())->schema();

        $this->assertArrayHasKey('general', $schema);
        $this->assertArrayHasKey('fields', $schema['general']);
        $this->assertArrayHasKey('portal_bookings_date_format', $schema['general']['fields']);

        $field = $schema['general']['fields']['portal_bookings_date_format'];

        $this->assertSame('select', $field['type']);
        $this->assertSame('Portal Bookings Date Format', $field['label']);
        $this->assertSame('d MMM', $field['default']);
        $this->assertArrayHasKey('options', $field);
        $this->assertSame('Mon 15 Jan', $field['options']['ddd d MMM']);
        $this->assertSame('2024-01-15', $field['options']['yyyy-MM-dd']);
    }

    /** @test */
    public function daypilot_pattern_helper_maps_tokens_to_php_format_tokens(): void {
        require_once MYVH_PLUGIN_DIR . 'src/Settings/settings-helper.php';

        $this->assertSame('D j M', myvh_daypilot_to_php_date_format('ddd d MMM'));
        $this->assertSame('j/n/Y', myvh_daypilot_to_php_date_format('d/M/yyyy'));
        $this->assertSame('Y-m-d', myvh_daypilot_to_php_date_format('yyyy-MM-dd'));
    }
}
