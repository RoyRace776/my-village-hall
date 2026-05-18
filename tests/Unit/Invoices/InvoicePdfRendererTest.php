<?php

namespace MYVH\Tests\Unit\Invoices;

use MYVH\Invoices\InvoicePdfRenderer;
use MYVH\Tests\Unit\UnitTestCase;

class InvoicePdfRendererTest extends UnitTestCase {
    /** @test */
    public function render_html_includes_booking_date_for_booking_linked_room_and_addon_items(): void {
        $renderer = new InvoicePdfRenderer(MYVH_PLUGIN_DIR . 'templates/Invoices/');

        $html = $renderer->renderHtml([
            'InvoiceNumber' => 'INV-000101',
            'InvoiceDate' => '2026-06-01',
            'DueDate' => '2026-06-15',
            'Status' => 'sent',
            'Items' => [
                [
                    'Description' => 'Main Hall room charge',
                    'BookingId' => 55,
                    'StartDate' => '2026-06-10',
                    'ItemType' => 'charge',
                    'Quantity' => 1,
                    'UnitPrice' => 40,
                    'TaxRate' => 0,
                    'TotalAmount' => 40,
                ],
                [
                    'Description' => 'Projector add-on',
                    'BookingId' => 55,
                    'StartDate' => '2026-06-10',
                    'ItemType' => 'charge',
                    'Quantity' => 1,
                    'UnitPrice' => 10,
                    'TaxRate' => 0,
                    'TotalAmount' => 10,
                ],
            ],
            'SubTotal' => 50,
            'TaxAmount' => 0,
            'TotalAmount' => 50,
        ]);

        $this->assertStringContainsString('Main Hall room charge', $html);
        $this->assertStringContainsString('Projector add-on', $html);
        $this->assertSame(2, substr_count($html, 'Booking date: 10 Jun 2026'));
    }

    /** @test */
    public function render_html_does_not_show_booking_date_for_deposit_items(): void {
        $renderer = new InvoicePdfRenderer(MYVH_PLUGIN_DIR . 'templates/Invoices/');

        $html = $renderer->renderHtml([
            'InvoiceNumber' => 'INV-000102',
            'InvoiceDate' => '2026-06-01',
            'Status' => 'sent',
            'Items' => [
                [
                    'Description' => 'Booking deposit',
                    'BookingId' => 56,
                    'StartDate' => '2026-06-12',
                    'ItemType' => 'deposit',
                    'Quantity' => 1,
                    'UnitPrice' => 20,
                    'TaxRate' => 0,
                    'TotalAmount' => 20,
                ],
            ],
            'SubTotal' => 20,
            'TaxAmount' => 0,
            'TotalAmount' => 20,
        ]);

        $this->assertStringNotContainsString('Booking date:', $html);
    }
}
