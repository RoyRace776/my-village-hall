<?php

namespace MYVH\Tests\Unit\Admin;

use Brain\Monkey\Functions;
use MYVH\Invoices\InvoiceService;
use MYVH\Tests\Unit\UnitTestCase;

class AdminInvoicesPageTest extends UnitTestCase {
    protected function setUp(): void {
        parent::setUp();

        Functions\stubs([
            'esc_html' => static fn($value) => (string) $value,
            'esc_attr' => static fn($value) => (string) $value,
            'esc_url' => static fn($value) => (string) $value,
            'admin_url' => static fn($path = '') => '/wp-admin/' . ltrim((string) $path, '/'),
            'wp_nonce_url' => static fn($url, $action = -1, $name = '_wpnonce') => (string) $url,
            'wp_unslash' => static fn($value) => $value,
            'sanitize_text_field' => static fn($value) => (string) $value,
            'sanitize_key' => static fn($value) => strtolower((string) $value),
            'selected' => static function ($selected, $current = true, $display = true) {
                return $selected == $current ? 'selected="selected"' : '';
            },
            'current_user_can' => static fn($capability) => true,
            'wp_die' => static function ($message = ''): void {
                throw new \RuntimeException((string) $message);
            },
        ]);

        Functions\when('esc_html_e')->alias(static function (string $text): void {
            echo $text;
        });
        Functions\when('esc_attr_e')->alias(static function (string $text): void {
            echo $text;
        });
    }

    /** @test */
    public function renders_admin_invoice_filters_and_filters_results(): void {
        global $myvh_container;

        $invoice_service = $this->mock(InvoiceService::class);
        $invoice_service->shouldReceive('get_with_customers')
            ->once()
            ->andReturn([
                [
                    'Id' => 1,
                    'InvoiceNumber' => 'INV-000123',
                    'CustomerName' => 'Acme Community Group',
                    'InvoiceDate' => '2026-05-01',
                    'DueDate' => '2026-05-31',
                    'BillingOrganisationName' => 'Acme Community Group',
                    'BillingName' => '',
                    'TotalAmount' => 100,
                    'AmountPaid' => 0,
                    'Status' => 'sent',
                ],
                [
                    'Id' => 2,
                    'InvoiceNumber' => 'INV-000987',
                    'CustomerName' => 'Beta Group',
                    'InvoiceDate' => '2026-05-02',
                    'DueDate' => '2026-06-01',
                    'BillingOrganisationName' => 'Beta Group',
                    'BillingName' => '',
                    'TotalAmount' => 75,
                    'AmountPaid' => 0,
                    'Status' => 'draft',
                ],
            ]);

        $invoice_service->shouldReceive('get_status_label')
            ->once()
            ->andReturnUsing(static fn(string $status) => ucwords(str_replace('-', ' ', $status)));

        $myvh_container = new class($invoice_service) {
            public function __construct(private $invoice_service) {}
            public function get(string $class) {
                return $this->invoice_service;
            }
        };

        $_GET = [
            'page' => 'myvh-invoices',
            'client_name' => 'Acme',
            'client_name_match' => 'begins_with',
            'invoice_number' => '123',
            'invoice_number_match' => 'contains',
        ];

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Admin/invoices-page.php';
        $html = (string) ob_get_clean();

        $this->assertStringContainsString('name="client_name"', $html);
        $this->assertStringContainsString('Begins with', $html);
        $this->assertStringContainsString('Contains', $html);
        $this->assertStringContainsString('INV-000123', $html);
        $this->assertStringNotContainsString('INV-000987', $html);
        $this->assertStringContainsString('Showing invoices matching the selected filters.', $html);
        $this->assertStringContainsString('value="paid_with_deposit"', $html);
    }
}