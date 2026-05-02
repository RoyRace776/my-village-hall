<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Invoices\InvoiceService;
use RuntimeException;
use WP_Error;

class InvoiceFactory
{
    public static function make(array $overrides = []): array
    {
        $faker        = Factory::create();
        $total_amount = $faker->randomFloat(2, 10, 500);

        return array_merge([
            'Id'                      => 0,
            'InvoiceNumber'           => 'INV-' . str_pad((string) $faker->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'CustomerId'              => 1,
            'BillingName'             => $faker->name,
            'BillingOrganisationName' => null,
            'BillingEmail'            => $faker->safeEmail,
            'BillingAddressLine1'     => $faker->streetAddress,
            'BillingAddressLine2'     => null,
            'BillingTownCity'         => $faker->city,
            'BillingPostcode'         => $faker->postcode,
            'BillingReference'        => null,
            'InvoiceDate'             => date('Y-m-d'),
            'DueDate'                 => date('Y-m-d', strtotime('+14 days')),
            'SubTotal'                => number_format($total_amount, 2, '.', ''),
            'TaxAmount'               => '0.00',
            'TotalAmount'             => number_format($total_amount, 2, '.', ''),
            'AmountPaid'              => '0.00',
            'AmountDue'               => number_format($total_amount, 2, '.', ''),
            'Status'                  => 'draft',
            'Notes'                   => null,
            'PdfPath'                 => null,
            'Created'                 => date('Y-m-d H:i:s'),
            'Updated'                 => date('Y-m-d H:i:s'),
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker    = Factory::create();
        $customer = isset($overrides['customer_id']) ? null : CustomerFactory::create();
        $service  = app(InvoiceService::class);

        $total_amount = $faker->randomFloat(2, 10, 500);

        $invoice_id = $service->save(array_merge([
            'customer_id'  => $overrides['customer_id'] ?? $customer['Id'],
            'total_amount' => $total_amount,
            'sub_total'    => $total_amount,
            'tax_amount'   => 0,
            'amount_paid'  => 0,
            'status'       => 'draft',
            'invoice_date' => date('Y-m-d'),
            'due_date'     => date('Y-m-d', strtotime('+14 days')),
            'notes'        => '',
        ], $overrides));

        if ($invoice_id instanceof WP_Error) {
            throw new RuntimeException('InvoiceFactory create failed: ' . $invoice_id->get_error_message());
        }

        if (!is_int($invoice_id) || $invoice_id <= 0) {
            throw new RuntimeException('InvoiceFactory create failed: invalid invoice id returned.');
        }

        $invoice = $service->get($invoice_id);
        if (!$invoice) {
            throw new RuntimeException('InvoiceFactory create failed: invoice could not be loaded.');
        }

        return $invoice;
    }
}
