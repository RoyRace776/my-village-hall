<?php

namespace MYVH\Tests\Unit\Bookings;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingService;
use MYVH\Tests\Unit\UnitTestCase;
use Tests\Support\Factories\BookingFactory;

class BookingServiceEntityAccessTest extends UnitTestCase
{
    private BookingService $service;
    private $booking_repo;
    private $booking_query_service;
    private $booking_access_control;
    private $addon_service;
    private $invoice_item_repo;
    private $booking_charge_repo;
    private $deposit_service;

    protected function setUp(): void
    {
        parent::setUp();

        $room_service = $this->mock(\MYVH\Rooms\RoomService::class);
        $this->booking_repo = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $booking_addon_repo = $this->mock(\MYVH\Bookings\BookingAddonRepository::class);
        $this->addon_service = $this->mock(\MYVH\Addons\AddonService::class);
        $validator = $this->mock(\MYVH\Bookings\BookingValidator::class);
        $booking_addon_sync_service = $this->mock(\MYVH\Bookings\Services\BookingAddonSyncService::class);
        $booking_charge_service = $this->mock(\MYVH\Bookings\Services\BookingChargeService::class);
        $booking_chargeable_hours_calculator = $this->mock(\MYVH\Bookings\Services\BookingChargeableHoursCalculator::class);
        $booking_creation_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingCreationEventDispatcher::class);
        $booking_deletion_service = $this->mock(\MYVH\Bookings\Services\BookingDeletionService::class);
        $booking_lifecycle_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingLifecycleEventDispatcher::class);
        $this->booking_access_control = $this->mock(\MYVH\Bookings\Services\BookingAccessControl::class);
        $booking_movement_service = $this->mock(\MYVH\Bookings\Services\BookingMovementService::class);
        $this->booking_query_service = $this->mock(\MYVH\Bookings\Services\BookingQueryService::class);
        $booking_status_transition_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingStatusTransitionDispatcher::class);
        $booking_update_event_dispatcher = $this->mock(\MYVH\Bookings\Services\BookingUpdateEventDispatcher::class);
        $recurring_pattern_service = $this->mock(\MYVH\Bookings\RecurringPatternService::class);
        $recurring_booking_creator = $this->mock(\MYVH\Bookings\Services\RecurringBookingCreator::class);
        $recurring_booking_updater = $this->mock(\MYVH\Bookings\Services\RecurringBookingUpdater::class);
        $invoice_service = $this->mock(\MYVH\Invoices\InvoiceService::class);
        $this->invoice_item_repo = $this->mock(\MYVH\Invoices\InvoiceItemRepository::class);
        $this->booking_charge_repo = $this->mock(\MYVH\Bookings\BookingChargeRepository::class);
        $this->deposit_service = $this->mock(\MYVH\Deposits\DepositService::class);

        $this->service = new BookingService(
            $room_service,
            $this->booking_repo,
            $booking_addon_repo,
            $this->addon_service,
            $validator,
            $booking_addon_sync_service,
            $booking_charge_service,
            $booking_chargeable_hours_calculator,
            $booking_creation_event_dispatcher,
            $booking_deletion_service,
            $booking_lifecycle_event_dispatcher,
            $this->booking_access_control,
            $booking_movement_service,
            $this->booking_query_service,
            $booking_status_transition_dispatcher,
            $booking_update_event_dispatcher,
            $recurring_pattern_service,
            $recurring_booking_creator,
            $recurring_booking_updater,
            $invoice_service,
            $this->invoice_item_repo,
            $this->booking_charge_repo,
            $this->deposit_service
        );
    }

    public function test_get_by_id_returns_booking_entity(): void
    {
        $booking = $this->createBooking();

        $this->booking_repo->shouldReceive('get')
            ->once()
            ->with(10)
            ->andReturn($booking);

        $result = $this->service->get_by_id(10);

        $this->assertSame($booking, $result);
    }

    public function test_get_by_id_returns_null_when_repository_throws_not_found(): void
    {
        $this->booking_repo->shouldReceive('get')
            ->once()
            ->with(999)
            ->andThrow(new \RuntimeException('not found'));

        $this->assertNull($this->service->get_by_id(999));
    }

    public function test_get_between_returns_booking_entities(): void
    {
        $booking = $this->createBooking();

        $this->booking_query_service->shouldReceive('get_between')
            ->once()
            ->with('2026-06-01', '2026-06-30', 'public', ['room_id' => 5])
            ->andReturn([$booking]);

        $result = $this->service->get_between('2026-06-01', '2026-06-30', 'public', ['room_id' => 5]);

        $this->assertCount(1, $result);
        $this->assertSame($booking, $result[0]);
    }

    public function test_get_all_with_details_delegates_to_query_service(): void
    {
        $rows = [['Id' => 10, 'Status' => 'confirmed']];

        $this->booking_query_service->shouldReceive('get_all_with_details')
            ->once()
            ->with(['status' => 'confirmed'])
            ->andReturnUsing(static fn() => $rows);

        $result = $this->service->get_all_with_details(['status' => 'confirmed']);

        $this->assertSame($rows, $result);
    }

    public function test_get_booking_detail_accessors_delegate_to_query_service(): void
    {
        $detail = ['Id' => 44, 'CustomerId' => 8];

        $this->booking_query_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(44)
            ->andReturnUsing(static fn() => $detail);

        $this->booking_query_service->shouldReceive('get_by_id_with_details_for_customer')
            ->once()
            ->with(44, 8)
            ->andReturnUsing(static fn() => $detail);

        $this->assertSame($detail, $this->service->get_by_id_with_details(44));
        $this->assertSame($detail, $this->service->get_by_id_with_details_for_customer(44, 8));
    }

    public function test_get_booking_list_and_upcoming_delegate_to_query_service(): void
    {
        $list = [['Id' => 1], ['Id' => 2]];
        $upcoming = [['Id' => 3]];

        $this->booking_query_service->shouldReceive('get_booking_list')
            ->once()
            ->with(['status' => 'pending'])
            ->andReturnUsing(static fn() => $list);

        $this->booking_query_service->shouldReceive('get_upcoming_bookings')
            ->once()
            ->with(99)
            ->andReturnUsing(static fn() => $upcoming);

        $this->assertSame($list, $this->service->get_booking_list(['status' => 'pending']));
        $this->assertSame($upcoming, $this->service->get_upcoming_bookings(99));
    }

    public function test_can_edit_and_can_delete_delegate_to_access_control(): void
    {
        $booking = ['Id' => 77];
        $canDelete = ['allowed' => true, 'reason' => null];
        $canEdit = ['allowed' => false, 'reason' => 'forbidden'];

        $this->booking_access_control->shouldReceive('can_delete')
            ->once()
            ->with($booking)
            ->andReturnUsing(static fn() => $canDelete);

        $this->booking_access_control->shouldReceive('can_edit')
            ->once()
            ->with($booking)
            ->andReturnUsing(static fn() => $canEdit);

        $this->assertSame($canDelete, $this->service->can_delete($booking));
        $this->assertSame($canEdit, $this->service->can_edit($booking));
    }

    public function test_uninvoiced_queries_delegate_to_booking_repository(): void
    {
        $rows = [['Id' => 5]];
        $countsByOrg = [['OrganisationId' => 3, 'UninvoicedCount' => 2]];
        $countsByCustomer = [['CustomerId' => 9, 'UninvoicedCount' => 1]];

        $this->booking_repo->shouldReceive('get_uninvoiced_bookings')
            ->once()
            ->with(['organisation_id' => 3])
            ->andReturnUsing(static fn() => $rows);
        $this->booking_repo->shouldReceive('get_uninvoiced_single_bookings')
            ->once()
            ->with(['limit' => 10])
            ->andReturnUsing(static fn() => $rows);
        $this->booking_repo->shouldReceive('get_uninvoiced_recurring_bookings')
            ->once()
            ->with([])
            ->andReturnUsing(static fn() => $rows);
        $this->booking_repo->shouldReceive('count_uninvoiced_by_organisation')
            ->once()
            ->andReturnUsing(static fn() => $countsByOrg);
        $this->booking_repo->shouldReceive('count_uninvoiced_by_customer')
            ->once()
            ->andReturnUsing(static fn() => $countsByCustomer);

        $this->assertSame($rows, $this->service->get_uninvoiced_bookings(['organisation_id' => 3]));
        $this->assertSame($rows, $this->service->get_uninvoiced_single_bookings(['limit' => 10]));
        $this->assertSame($rows, $this->service->get_uninvoiced_recurring_bookings([]));
        $this->assertSame($countsByOrg, $this->service->get_uninvoiced_by_organisation());
        $this->assertSame($countsByCustomer, $this->service->get_uninvoiced_by_customer());
    }

    public function test_invoice_flags_delegate_to_repository(): void
    {
        $this->booking_repo->shouldReceive('is_no_invoice_required')
            ->once()
            ->with(22)
            ->andReturn(true);
        $this->booking_repo->shouldReceive('has_invoiced_items')
            ->once()
            ->with(22)
            ->andReturn(false);

        $this->assertTrue($this->service->booking_requires_no_invoice(22));
        $this->assertFalse($this->service->booking_has_invoices(22));
    }

    public function test_get_charges_and_deposit_items_delegate_to_repositories(): void
    {
        $charges = [['Type' => 'base', 'Amount' => 20.0]];
        $depositItems = [['Type' => 'deposit', 'Amount' => 15.0]];

        $this->booking_charge_repo->shouldReceive('get_by_booking_id')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn() => $charges);
        $this->invoice_item_repo->shouldReceive('get_deposit_items_for_booking')
            ->once()
            ->with(12)
            ->andReturnUsing(static fn() => $depositItems);

        $this->assertSame($charges, $this->service->get_charges_for_booking(12));
        $this->assertSame($depositItems, $this->service->get_deposit_items_for_booking(12));
    }

    public function test_save_addons_and_get_addons_delegate_to_addon_service(): void
    {
        $addons = [['addon_id' => 4, 'quantity' => 2]];
        $stored = [['addon_id' => 4, 'description' => 'Tea urn']];

        $this->addon_service->shouldReceive('save_booking_addons')
            ->once()
            ->with(14, $addons, true)
            ->andReturnNull();

        $this->addon_service->shouldReceive('get_addons_for_booking')
            ->once()
            ->with(14)
            ->andReturnUsing(static fn() => $stored);

        $this->service->save_addons(14, $addons, true);
        $this->assertSame($stored, $this->service->get_addons_for_booking(14));
    }

    public function test_get_expected_deposit_for_booking_uses_deposit_service(): void
    {
        $expected = ['action' => 'auto_add', 'amount' => 25.0];

        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(51)
            ->andReturnUsing(static fn() => [
                'RoomId' => 9,
                'EndDate' => '2026-06-03',
                'EndTime' => '18:30:00',
            ]);

        $this->deposit_service->shouldReceive('evaluate')
            ->once()
            ->with(9, \Mockery::on(static fn($dt): bool =>
                $dt instanceof \DateTime
                && $dt->format('Y-m-d H:i:s') === '2026-06-03 18:30:00'
            ))
            ->andReturnUsing(static fn() => $expected);

        $this->assertSame($expected, $this->service->get_expected_deposit_for_booking(51));
    }

    public function test_get_expected_deposit_for_booking_returns_null_when_booking_not_found(): void
    {
        $this->booking_repo->shouldReceive('get_by_id')
            ->once()
            ->with(999)
            ->andReturn(null);

        $this->deposit_service->shouldReceive('evaluate')->never();

        $this->assertNull($this->service->get_expected_deposit_for_booking(999));
    }

    private function createBooking(): Booking
    {
        return BookingFactory::make(['Id' => 10, 'RoomId' => 5]);
    }
}