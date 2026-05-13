<?php

namespace MYVH\Tests\Unit\Invoices;

use Brain\Monkey\Functions;
use MYVH\Addons\AddonRepository;
use MYVH\AutoInvoicing\RecurringBookingAutoInvoiceRuleRepository;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;
use MYVH\Bookings\BookingAddonRepository;
use MYVH\Bookings\BookingChargeRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerRepository;
use MYVH\Deposits\DepositService;
use MYVH\Invoices\InvoiceGeneratorService;
use MYVH\Invoices\InvoiceItemRepository;
use MYVH\Invoices\InvoiceRepository;
use MYVH\Invoices\InvoiceService;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Pricing\PricingService;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class InvoiceGeneratorServiceTest extends UnitTestCase {
    private $invoice_service;
    private $invoice_repo;
    private $invoice_item_repo;
    private $booking_repo;
    private $booking_service;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_repo;
    private $customer_repo;
    private $organisation_repo;
    private $pricing_service;
    private $deposit_service;
    private $single_rule_repo;
    private $recurring_rule_repo;
    private InvoiceGeneratorService $service;

    protected function setUp(): void {
        parent::setUp();

        $this->invoice_service = $this->mock(InvoiceService::class);
        $this->invoice_repo = $this->mock(InvoiceRepository::class);
        $this->invoice_item_repo = $this->mock(InvoiceItemRepository::class);
        $this->booking_repo = $this->mock(BookingRepository::class);
        $this->booking_service = $this->mock(BookingService::class);
        $this->booking_charge_repo = $this->mock(BookingChargeRepository::class);
        $this->booking_addon_repo = $this->mock(BookingAddonRepository::class);
        $this->addon_repo = $this->mock(AddonRepository::class);
        $this->customer_repo = $this->mock(CustomerRepository::class);
        $this->organisation_repo = $this->mock(OrganisationRepository::class);
        $this->pricing_service = $this->mock(PricingService::class);
        $this->deposit_service = $this->mock(DepositService::class);
        $this->single_rule_repo = $this->mock(SingleBookingAutoInvoiceRuleRepository::class);
        $this->recurring_rule_repo = $this->mock(RecurringBookingAutoInvoiceRuleRepository::class);

        $this->service = new InvoiceGeneratorService(
            $this->invoice_service,
            $this->invoice_repo,
            $this->invoice_item_repo,
            $this->booking_repo,
            $this->booking_service,
            $this->booking_charge_repo,
            $this->booking_addon_repo,
            $this->addon_repo,
            $this->customer_repo,
            $this->organisation_repo,
            $this->pricing_service,
            $this->deposit_service,
            $this->single_rule_repo,
            $this->recurring_rule_repo
        );

        Functions\stubs([
            'is_wp_error' => static fn($value): bool => $value instanceof WP_Error,
            'apply_filters' => static fn($hook, $value) => $value,
            'sanitize_key' => static fn($value): string => strtolower((string) $value),
            'get_option' => static fn($key, $default = []) => [],
            'do_action' => static function (...$args): void {
            },
        ]);

        $this->single_rule_repo->shouldReceive('get_all_rules')->byDefault()->andReturn([]);
        $this->single_rule_repo->shouldReceive('get_active_rules')->byDefault()->andReturn([]);
        $this->recurring_rule_repo->shouldReceive('get_all_rules')->byDefault()->andReturn([]);
        $this->recurring_rule_repo->shouldReceive('get_active_rules')->byDefault()->andReturn([]);
    }

    /** @test */
    public function returns_error_when_booking_ids_are_empty(): void {
        $result = $this->service->generate_invoices_from_bookings([]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('No valid bookings provided', $result->get_error_message());
    }

    /** @test */
    public function blocks_generation_when_booking_is_marked_as_no_invoice_required(): void {
        $this->booking_service->shouldReceive('booking_requires_no_invoice')->once()->with(12)->andReturn(true);
        $this->booking_service->shouldReceive('booking_has_invoices')->never();

        $result = $this->service->generate_invoices_from_bookings([12]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Booking #12 is marked as not requiring an invoice', $result->get_error_message());
    }

    /** @test */
    public function blocks_generation_when_booking_has_existing_invoices(): void {
        $this->booking_service->shouldReceive('booking_requires_no_invoice')->once()->with(13)->andReturn(false);
        $this->booking_service->shouldReceive('booking_has_invoices')->once()->with(13)->andReturn(true);

        $result = $this->service->generate_invoices_from_bookings([13]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Booking #13 has already been invoiced', $result->get_error_message());
    }

    /** @test */
    public function returns_error_when_booking_details_cannot_be_found(): void {
        $this->booking_service->shouldReceive('booking_requires_no_invoice')->once()->with(14)->andReturn(false);
        $this->booking_service->shouldReceive('booking_has_invoices')->once()->with(14)->andReturn(false);
        $this->booking_repo->shouldReceive('get_all_with_details')->once()->with(['booking_id' => 14])->andReturn([]);

        $result = $this->service->generate_invoices_from_bookings([14]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Booking #14 not found', $result->get_error_message());
    }

    /** @test */
    public function per_booking_mode_creates_one_invoice_per_booking(): void {
        $this->arrange_invoiceable_booking(21, 7, 31.50, 'Customer One');
        $this->arrange_invoiceable_booking(22, 7, 20.00, 'Customer One');

        $this->invoice_service->shouldReceive('save')->twice()->andReturn(501, 502);
        $this->invoice_item_repo->shouldReceive('create')->twice()->andReturn(true);

        $result = $this->service->generate_invoices_from_bookings([21, 22], [
            'group_by' => 'per_booking',
        ]);

        $this->assertSame([501, 502], $result);
    }

    /** @test */
    public function by_customer_mode_groups_bookings_into_a_single_invoice(): void {
        $this->arrange_invoiceable_booking(41, 9, 10.00, 'Customer Grouped');
        $this->arrange_invoiceable_booking(42, 9, 15.50, 'Customer Grouped');

        $this->invoice_service->shouldReceive('save')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return ($payload['customer_id'] ?? 0) === 9
                    && ($payload['sub_total'] ?? 0) === 25.5
                    && ($payload['total_amount'] ?? 0) === 25.5
                    && ($payload['notes'] ?? '') === 'Generated from 2 booking(s)';
            }))
            ->andReturn(700);

        $this->invoice_item_repo->shouldReceive('create')->twice()->andReturn(true);

        $result = $this->service->generate_invoices_from_bookings([41, 42], [
            'group_by' => 'by_customer',
        ]);

        $this->assertSame([700], $result);
    }

    /** @test */
    public function by_organisation_mode_uses_organisation_billing_snapshot_when_enabled(): void {
        $this->arrange_invoiceable_booking(51, 11, 50.00, 'Fallback Customer', 77);

        $this->organisation_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturnUsing(static fn(): array => [
                'InvoiceOrganisationBookings' => 1,
                'Name' => 'Village Group',
                'BillingContactName' => 'Accounts Team',
                'BillingEmail' => 'accounts@village.test',
                'BillingAddressLine1' => '1 Main St',
                'BillingAddressLine2' => 'Suite B',
                'BillingTownCity' => 'Teston',
                'BillingPostcode' => 'TE57 1NG',
                'BillingReference' => 'VG-001',
            ]);

        $this->invoice_service->shouldReceive('save')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return ($payload['billing_name'] ?? '') === 'Accounts Team'
                    && ($payload['billing_organisation_name'] ?? '') === 'Village Group'
                    && ($payload['billing_email'] ?? '') === 'accounts@village.test'
                    && ($payload['billing_reference'] ?? '') === 'VG-001';
            }))
            ->andReturn(800);

        $this->invoice_item_repo->shouldReceive('create')->once()->andReturn(true);

        $result = $this->service->generate_invoices_from_bookings([51], [
            'group_by' => 'by_organisation',
        ]);

        $this->assertSame([800], $result);
    }

    private function arrange_invoiceable_booking(int $booking_id, int $customer_id, float $charge_total, string $customer_name, int $organisation_id = 0): void {
        $this->booking_service->shouldReceive('booking_requires_no_invoice')->once()->with($booking_id)->andReturn(false);
        $this->booking_service->shouldReceive('booking_has_invoices')->once()->with($booking_id)->andReturn(false);

        $this->booking_repo->shouldReceive('get_all_with_details')
            ->once()
            ->with(['booking_id' => $booking_id])
            ->andReturnUsing(static fn(): array => [[
                'Id' => $booking_id,
                'CustomerId' => $customer_id,
                'OrganisationId' => $organisation_id,
                'RoomId' => 3,
                'EndDate' => '2026-05-15',
                'EndTime' => '10:00:00',
            ]]);

        $this->booking_charge_repo->shouldReceive('get_by_booking_id')
            ->once()
            ->with($booking_id)
            ->andReturnUsing(static fn(): array => [[
                'Description' => 'Room charge',
                'Quantity' => 1,
                'UnitPrice' => $charge_total,
                'TaxRate' => 0,
                'TaxAmount' => 0,
                'TotalAmount' => $charge_total,
            ]]);

        $this->booking_addon_repo->shouldReceive('get_by_booking_id')->once()->with($booking_id)->andReturn([]);
        $this->deposit_service->shouldReceive('evaluate')->once()->andReturn(null);

        $this->customer_repo->shouldReceive('get_by_id')
            ->byDefault()
            ->with($customer_id)
            ->andReturnUsing(static fn(): array => [
                'Name' => $customer_name,
                'Email' => 'customer@example.test',
                'AddressLine1' => '10 High St',
                'PostCode' => 'TE1 2ST',
            ]);
    }
}
