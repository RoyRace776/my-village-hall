<?php

namespace Tests\Support\Factories;

use Faker\Factory;
use MYVH\Payments\PaymentService;
use RuntimeException;
use WP_Error;

class PaymentFactory
{
    public static function make(array $overrides = []): array
    {
        $faker = Factory::create();

        return array_merge([
            'Id'                   => 0,
            'InvoiceId'            => 1,
            'PaymentDate'          => date('Y-m-d'),
            'Amount'               => $faker->randomFloat(2, 5, 200),
            'PaymentMethod'        => 'cash',
            'TransactionReference' => null,
            'Notes'                => null,
            'Created'              => '2026-01-01 00:00:00',
        ], $overrides);
    }

    public static function create(array $overrides = []): array
    {
        $faker   = Factory::create();
        $invoice = isset($overrides['invoice_id']) ? null : InvoiceFactory::create();

        $invoice_id = (int) ($overrides['invoice_id'] ?? $invoice['Id']);

        // Default to half the invoice total so the invoice remains part-paid,
        // avoiding the "already fully paid" validation error in PaymentService.
        $amount = $overrides['payment_amount']
            ?? (isset($invoice['TotalAmount'])
                ? round((float) $invoice['TotalAmount'] / 2, 2)
                : $faker->randomFloat(2, 5, 50));

        $service    = app(PaymentService::class);
        $payment_id = $service->create(array_merge([
            'invoice_id'        => $invoice_id,
            'payment_amount'    => $amount,
            'payment_method'    => 'cash',
            'payment_date'      => date('Y-m-d'),
            'payment_reference' => '',
            'payment_comment'   => '',
        ], $overrides));

        if ($payment_id instanceof WP_Error) {
            throw new RuntimeException('PaymentFactory create failed: ' . $payment_id->get_error_message());
        }

        if (!is_int($payment_id) || $payment_id <= 0) {
            throw new RuntimeException('PaymentFactory create failed: invalid payment id returned.');
        }

        $payments = $service->get_for_invoice($invoice_id);
        foreach ($payments as $payment) {
            if ((int) ($payment['Id'] ?? 0) === $payment_id) {
                return $payment;
            }
        }

        throw new RuntimeException('PaymentFactory create failed: payment could not be loaded.');
    }
}
