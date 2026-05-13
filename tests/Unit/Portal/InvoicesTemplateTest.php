<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Tests\Unit\UnitTestCase;

class InvoicesTemplateTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_url' => static fn($value) => (string) $value,
            'checked' => static function ($checked, $current = true, $display = true) {
                return $checked == $current ? 'checked="checked"' : '';
            },
            'wp_create_nonce' => static fn($action = '') => 'nonce-token',
            'admin_url' => static fn($path = '') => '/wp-admin/' . ltrim((string) $path, '/'),
            'add_query_arg' => static fn($args, $url = '') => (string) $url,
        ]);
    }

    /** @test */
    public function billing_column_shows_personal_booking_without_org_badge(): void {
        $html = $this->render_invoices_table([
            [
                'Id' => 1,
                'InvoiceNumber' => 'INV-000001',
                'InvoiceDate' => '2026-05-01',
                'DueDate' => '2026-05-31',
                'Status' => 'sent',
                'TotalAmount' => '50.00',
                'AmountPaid' => '0.00',
                'IsPersonalInvoice' => 1,
                'OrganisationName' => 'Community Group',
                'BillingName' => 'Alex Booker',
            ],
        ]);

        $this->assertStringContainsString('Personal booking', $html);
        $this->assertStringNotContainsString('myvh-badge myvh-badge-org', $html);
        $this->assertStringNotContainsString('Community Group <span class="myvh-badge myvh-badge-org">Org</span>', $html);
    }

    /** @test */
    public function billing_column_shows_org_name_and_badge_for_non_personal_invoice(): void {
        $html = $this->render_invoices_table([
            [
                'Id' => 2,
                'InvoiceNumber' => 'INV-000002',
                'InvoiceDate' => '2026-05-01',
                'DueDate' => '2026-05-31',
                'Status' => 'sent',
                'TotalAmount' => '75.00',
                'AmountPaid' => '0.00',
                'IsPersonalInvoice' => 0,
                'OrganisationName' => 'Community Group',
            ],
        ]);

        $this->assertStringContainsString('Community Group <span class="myvh-badge myvh-badge-org">Org</span>', $html);
    }

    /** @test */
    public function billing_column_does_not_show_org_badge_for_personal_booking_org_name(): void {
        $html = $this->render_invoices_table([
            [
                'Id' => 4,
                'InvoiceNumber' => 'INV-000004',
                'InvoiceDate' => '2026-05-01',
                'DueDate' => '2026-05-31',
                'Status' => 'sent',
                'TotalAmount' => '42.00',
                'AmountPaid' => '0.00',
                'IsPersonalInvoice' => 0,
                'OrganisationName' => 'Personal booking',
            ],
        ]);

        $this->assertStringContainsString('Personal booking', $html);
        $this->assertStringNotContainsString('Personal booking <span class="myvh-badge myvh-badge-org">Org</span>', $html);
    }

    /** @test */
    public function billing_column_falls_back_to_plain_organisation_label_without_badge(): void {
        $html = $this->render_invoices_table([
            [
                'Id' => 3,
                'InvoiceNumber' => 'INV-000003',
                'InvoiceDate' => '2026-05-01',
                'DueDate' => '2026-05-31',
                'Status' => 'draft',
                'TotalAmount' => '0.00',
                'AmountPaid' => '0.00',
                'IsPersonalInvoice' => 0,
                'OrganisationName' => '',
            ],
        ]);

        $this->assertStringContainsString('<td class="myvh-invoice-billing">', $html);
        $this->assertStringContainsString('Organisation', $html);
        $this->assertStringNotContainsString('myvh-badge myvh-badge-org', $html);
    }

    /** @test */
    public function filter_form_renders_date_inputs_and_quick_choices(): void {
        $html = $this->render_invoices_table([], [
            'selected_start_date' => '2026-04-13',
            'selected_end_date' => '2026-05-13',
            'quick_date_ranges' => [
                'last_month' => [
                    'label' => 'Last month',
                    'start_date' => '2026-04-13',
                    'end_date' => '2026-05-13',
                ],
                'last_6_months' => [
                    'label' => 'Last 6 months',
                    'start_date' => '2025-11-13',
                    'end_date' => '2026-05-13',
                ],
            ],
        ]);

        $this->assertStringContainsString('name="start_date"', $html);
        $this->assertStringContainsString('name="end_date"', $html);
        $this->assertStringContainsString('data-invoice-date-range="last_month"', $html);
        $this->assertStringContainsString('data-invoice-date-range="last_6_months"', $html);
        $this->assertStringContainsString('value="2026-04-13"', $html);
        $this->assertStringContainsString('value="2026-05-13"', $html);
    }

    private function render_invoices_table(array $invoices, array $extra_vars = []): string {
        $is_client_admin = false;
        $available_statuses = [];
        $selected_statuses = [];
        $selected_start_date = $extra_vars['selected_start_date'] ?? '';
        $selected_end_date = $extra_vars['selected_end_date'] ?? '';
        $quick_date_ranges = $extra_vars['quick_date_ranges'] ?? [];

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Portal/invoices.php';

        return (string) ob_get_clean();
    }
}
