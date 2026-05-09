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
        'recurring_bookings' => [
            'title' => 'Recurring Bookings Auto-Invoicing Rule',
            'description' => 'Configure automatic invoice generation for recurring bookings.',
            'fields' => [
                'recurring_enabled' => [
                    'label' => 'Enable auto-invoicing for recurring bookings',
                    'type' => 'boolean',
                    'default' => false,
                    'sanitize' => 'boolval',
                    'description' => 'When enabled, invoices are generated automatically for recurring booking instances.'
                ],
                'recurring_invoice_per_instance' => [
                    'label' => 'Invoice generation scope',
                    'type' => 'select',
                    'default' => 'per_instance',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'per_instance' => 'One invoice per recurrence instance',
                        'entire_pattern' => 'One invoice for entire recurring pattern'
                    ],
                    'description' => 'Should each recurrence generate its own invoice, or one for the entire series?'
                ],
                'recurring_trigger_timing' => [
                    'label' => 'Trigger event for each instance',
                    'type' => 'select',
                    'default' => 'completion',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'confirmation' => 'On booking confirmation',
                        'completion' => 'On booking completion/end date',
                        'days_after_confirmation' => 'N days after confirmation'
                    ],
                    'description' => 'When should invoices be automatically generated?'
                ],
                'recurring_trigger_offset_days' => [
                    'label' => 'Days after confirmation (if applicable)',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'Only used if trigger is "N days after confirmation". Set to 0 for immediate.'
                ],
                'recurring_delay_days' => [
                    'label' => 'Delay before invoice generation (days)',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'After the trigger event, wait this many days before creating the invoice.'
                ],
                'recurring_group_by' => [
                    'label' => 'Grouping strategy',
                    'type' => 'select',
                    'default' => 'per_instance',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'per_instance' => 'One invoice per recurrence instance',
                        'by_customer' => 'Group all instances by customer into one invoice',
                        'by_organisation' => 'Group all instances by organisation into one invoice'
                    ],
                    'description' => 'How should recurring instances be grouped into invoices?'
                ],
                'recurring_due_date_offset_days' => [
                    'label' => 'Payment due date (days after invoice)',
                    'type' => 'number',
                    'default' => 30,
                    'sanitize' => 'intval',
                    'description' => 'Number of days from invoice date when payment is due (payment terms).'
                ]
            ]
        ]
    ];
}
