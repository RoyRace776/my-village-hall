<?php
namespace MYVH\Settings;

/**
 * Auto-Invoicing Settings
 *
 * Configurable rules for automatic invoice generation
 * Two global rule sets: Single Bookings and Recurring Bookings
 */
class AutoInvoicingSettings extends SettingsBase {

    protected $option_name = 'myvh_invoicing_settings';
    protected $hide_from_client_admin = false;

    public function tab(): array {
        return [
            'key' => 'invoicing',
            'label' => 'Invoicing'
        ];
    }

    protected $schema = [
        'general' => [
            'title' => 'General Invoicing Settings',
            'description' => 'Prefix for invoice numbering',
            'fields' => [
                'prefix' => [
                    'label' => 'Invoice prefix',
                    'type' => 'string',
                    'default' => 'INV-',
                    'sanitize' => 'sanitize_text_field',
                    'description' =>
                    'Prefix for invoice numbering.'
                ],
                'auto_send' => [
                    'label' => 'Automatically send invoices to customers after generation',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval',
                    'description' => 'When enabled, invoices are automatically emailed to customers after they are generated. Otherwise, they will need to be sent manually from the invoice detail view.'
                ],
            ],
        ],
    ];
}
