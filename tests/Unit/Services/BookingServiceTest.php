<?php

namespace MYVH\Tests\Unit\Services;

use Mockery;
use Tests\Support\Factories\RoomFactory;
use MYVH\Tests\Unit\Unit_Test_Case;
use WP_Error;
use \MYVH\Tests\Unit\UnitTestCase;

/**
 * Unit tests for Booking_Service.
 *
 * Every dependency is mocked so these tests exercise the service's
 * own logic in pure isolation — no database, no WordPress.
 *
 * Test groups / methods
 * ─────────────────────
 * save()
 *   ✓ validation failure returns WP_Error and rolls back
 *   ✓ create path returns new booking id on success
 *   ✓ update path returns existing booking id on success
 *   ✓ repo->create() failure returns WP_Error and rolls back
 *   ✓ status defaults to pending when require_approval setting is true
 *   ✓ status defaults to confirmed when require_approval setting is false
 *
 * recalculate_booking_charges()
 *   ✓ invalid id returns WP_Error
 *   ✓ pricing snapshot WP_Error propagates
 *   ✓ charge repo delete failure returns WP_Error
 *   ✓ charge repo create failure returns WP_Error
 *   ✓ happy path returns true
 *
 * cancel()
 *   ✓ delegates to repo with CANCELLED status
 *
 * delete()
 *   ✓ invalid id returns WP_Error
 *   ✓ addon delete failure returns WP_Error and rolls back
 *   ✓ booking delete failure returns WP_Error and rolls back
 *   ✓ happy path returns true and commits
 *
 * move_booking()
 *   ✓ invalid room id returns WP_Error
 *   ✓ room not found returns WP_Error
 *   ✓ room not available returns WP_Error
 *   ✓ happy path returns 1
 *
 * get_last_warnings()
 *   ✓ returns empty array before any save
 *
 * calculate_chargeable_hours (via save())
 *   ✓ same-day booking calculates correct hours
 */
class BookingServiceTest extends UnitTestCase {

    // ── Dependency mocks ────────────────────────────────────────────────────

    private $room_service;
    private $booking_repo;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_service;
    private $validator;
    private $booking_addon_sync_service;
    private $availability;
    private $pricing;
    private $recurring_pattern_service;
    private $booking_charge_service;
    private $booking_chargeable_hours_calculator;
    private $booking_creation_event_dispatcher;
    private $booking_deletion_service;
    private $booking_lifecycle_event_dispatcher;
    private $booking_access_control;
    private $customer_repo;
    private $organisation_repo;
    private $organisation_member_repo;
    private $client_admin_service;
    private $booking_list_grouping_service;
    private $booking_movement_service;
    private $booking_query_service;
    private $booking_status_transition_dispatcher;
    private $booking_update_event_dispatcher;
    private $recurring_booking_creator;
    private $recurring_booking_updater;

    /** @var \MYVH\Bookings\BookingService */
    private $service;

    // ── Setup ────────────────────────────────────────────────────────────────

    protected function setUp(): void {
        parent::setUp();

        $this->room_service              = $this->mock(\MYVH\Rooms\RoomService::class);
        $this->booking_repo              = $this->mock(\MYVH\Bookings\BookingRepository::class);
        $this->booking_charge_repo       = $this->mock(\MYVH\Bookings\BookingChargeRepository::class);
        $this->booking_addon_repo        = $this->mock(\MYVH\Bookings\BookingAddonRepository::class);
        $this->addon_service             = $this->mock(\MYVH\Addons\AddonService::class);
        $this->validator                 = $this->mock(\MYVH\Bookings\BookingValidator::class);
        $this->booking_addon_sync_service = new \MYVH\Bookings\Services\BookingAddonSyncService($this->addon_service);
        $this->availability              = $this->mock(\MYVH\Availability\AvailabilityService::class);
        $this->pricing                   = $this->mock(\MYVH\Pricing\PricingService::class);
        $this->recurring_pattern_service = $this->mock(\MYVH\Bookings\RecurringPatternService::class);
        $this->booking_charge_service    = new \MYVH\Bookings\Services\BookingChargeService(
            $this->pricing,
            $this->booking_charge_repo
        );
        $this->customer_repo = $this->mock(\MYVH\Customers\CustomerRepository::class);
        $this->organisation_repo = $this->mock(\MYVH\Organisations\OrganisationRepository::class);
        $this->organisation_member_repo = $this->mock(\MYVH\Organisations\OrganisationMemberRepository::class);
        $this->client_admin_service = $this->mock(\MYVH\Portal\ClientAdminService::class);
        $this->booking_chargeable_hours_calculator = new \MYVH\Bookings\Services\BookingChargeableHoursCalculator();
        $this->booking_creation_event_dispatcher = new \MYVH\Bookings\Services\BookingCreationEventDispatcher();
        $this->booking_deletion_service  = new \MYVH\Bookings\Services\BookingDeletionService(
            $this->booking_repo,
            $this->booking_addon_repo,
            $this->booking_charge_repo
        );
        $this->booking_lifecycle_event_dispatcher = new \MYVH\Bookings\Services\BookingLifecycleEventDispatcher();
        $this->booking_access_control    = new \MYVH\Bookings\Services\BookingAccessControl(
            $this->booking_repo,
            $this->organisation_repo,
            $this->customer_repo,
            $this->organisation_member_repo,
            $this->client_admin_service
        );
        $this->booking_list_grouping_service = new \MYVH\Bookings\Services\BookingListGroupingService();
        $this->booking_movement_service  = new \MYVH\Bookings\Services\BookingMovementService(
            $this->room_service,
            $this->availability,
            $this->booking_repo
        );
        $this->booking_query_service     = new \MYVH\Bookings\Services\BookingQueryService(
            $this->booking_repo,
            $this->customer_repo,
            $this->booking_list_grouping_service
        );
        $this->booking_status_transition_dispatcher = new \MYVH\Bookings\Services\BookingStatusTransitionDispatcher();
        $this->booking_update_event_dispatcher = new \MYVH\Bookings\Services\BookingUpdateEventDispatcher();
        $this->recurring_booking_creator = new \MYVH\Bookings\Services\RecurringBookingCreator(
            $this->recurring_pattern_service
        );
        $this->recurring_booking_updater = new \MYVH\Bookings\Services\RecurringBookingUpdater(
            $this->booking_repo,
            $this->recurring_pattern_service
        );

        $this->service = new \MYVH\Bookings\BookingService(
            $this->room_service,
            $this->booking_repo,
            $this->booking_addon_repo,
            $this->addon_service,
            $this->validator,
            $this->booking_addon_sync_service,
            $this->booking_charge_service,
            $this->booking_chargeable_hours_calculator,
            $this->booking_creation_event_dispatcher,
            $this->booking_deletion_service,
            $this->booking_lifecycle_event_dispatcher,
            $this->booking_access_control,
            $this->booking_movement_service,
            $this->booking_query_service,
            $this->booking_status_transition_dispatcher,
            $this->booking_update_event_dispatcher,
            $this->recurring_pattern_service,
            $this->recurring_booking_creator,
            $this->recurring_booking_updater
        );
    }

    public function tearDown(): void {
        unset(
            $this->service,
            $this->room_service,
            $this->booking_repo,
            $this->booking_charge_repo,
            $this->booking_addon_repo,
            $this->addon_service,
            $this->validator,
            $this->booking_addon_sync_service,
            $this->availability,
            $this->pricing,
            $this->recurring_pattern_service,
            $this->booking_charge_service,
            $this->booking_chargeable_hours_calculator,
            $this->booking_creation_event_dispatcher,
            $this->booking_deletion_service,
            $this->booking_lifecycle_event_dispatcher,
            $this->booking_access_control,
            $this->customer_repo,
            $this->organisation_repo,
            $this->organisation_member_repo,
            $this->client_admin_service,
            $this->booking_list_grouping_service,
            $this->booking_movement_service,
            $this->booking_query_service,
            $this->booking_status_transition_dispatcher,
            $this->booking_update_event_dispatcher,
            $this->recurring_booking_creator,
            $this->recurring_booking_updater
        );
        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Returns a minimal valid $data array suitable for a CREATE save() call.
     */
    private function minimal_create_data(array $overrides = []): array {
        return array_merge([
            'booking_id'      => 0,
            'customer_id'     => 1,
            'organisation_id' => 0,
            'room_id'         => 5,
            'status'          => 'confirmed',
            'start_date'      => '2026-06-01',
            'end_date'        => '2026-06-01',
            'start_time'      => '09:00:00',
            'end_time'        => '11:00:00',
            'description'     => '',
            'public'          => 0,
            'no_invoice_required' => 0,
            'is_recurring'    => 0,
            'addons'          => [],
        ], $overrides);
    }

    /**
     * Returns a minimal room record array.
     */
    private function minimal_room(array $overrides = []): array {
        return RoomFactory::make(array_merge([
            'Id'              => 5,
            'CalcClosedHours' => 1,
            'OpeningTime'     => '08:00:00',
            'ClosingTime'     => '22:00:00',
        ], $overrides));
    }

    private function recurring_booking_record(int $booking_id, array $overrides = []): array {
        return array_merge([
            'Id' => $booking_id,
            'RecurringPatternId' => 200,
            'CustomerId' => 1,
            'OrganisationId' => 0,
            'RoomId' => 5,
            'Status' => 'confirmed',
            'StartDate' => '2026-06-01',
            'EndDate' => '2026-06-01',
            'StartTime' => '09:00:00',
            'EndTime' => '11:00:00',
            'Description' => 'Yoga class',
            'Public' => 0,
            'NoInvoiceRequired' => 0,
            'ChargeableHours' => 2,
        ], $overrides);
    }

    /**
     * Wires up the mocks for the standard "happy path" CREATE scenario.
     * Returns the booking id that repo->create() will return.
     */
    private function wire_happy_create(int $new_id = 42): int {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();

        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->booking_repo->shouldReceive('create')
            ->once()
            ->andReturn($new_id);

        $this->addon_service->shouldReceive('save_booking_addons')
            ->once()
            ->withAnyArgs();

        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with($new_id)
            ->once()
            ->andReturnUsing(static fn () => []);

        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')
            ->with($new_id)
            ->once()
            ->andReturn(1);

        $this->booking_charge_repo->shouldReceive('create')
            ->once()
            ->andReturn(1);

        return $new_id;
    }

    // ── Tests: save() ────────────────────────────────────────────────────────

    /** @test */
    public function save_returns_wp_error_and_rolls_back_when_validation_fails(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();

        $this->validator->shouldReceive('validate')
            ->once()
            ->andReturn(new WP_Error('validation', 'Room is required'));

        $result = $this->service->save($this->minimal_create_data());
        //printf('Validation error: %s', is_wp_error($result) ? $result->get_error_message() : 'none');

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_create_returns_new_booking_id_on_success(): void {
        $expected_id = $this->wire_happy_create(42);

        $result = $this->service->save($this->minimal_create_data());

        $this->assertSame($expected_id, $result);
    }

    /** @test */
    public function save_create_persists_no_invoice_required_flag(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();

        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->booking_repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return intval($record['NoInvoiceRequired'] ?? 0) === 1;
            }))
            ->andReturn(42);

        $this->addon_service->shouldReceive('save_booking_addons')->once()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with(42)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(42)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(1);

        $result = $this->service->save($this->minimal_create_data([
            'no_invoice_required' => 1,
        ]));

        $this->assertSame(42, $result);
    }

    /** @test */
    public function save_create_rolls_back_and_returns_wp_error_when_repo_create_fails(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();

        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->booking_repo->shouldReceive('create')
            ->once()
            ->andReturn(false);  // simulate DB failure

        $result = $this->service->save($this->minimal_create_data());

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function save_update_returns_booking_id_on_success(): void {
        $booking_id = 99;
        $data = $this->minimal_create_data(['booking_id' => $booking_id]);

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();

        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        // update_booking() fetches the current record first
        $this->booking_repo->shouldReceive('get_by_id')
            ->with($booking_id)
            ->andReturnUsing(static fn () => ['Id' => $booking_id, 'Status' => 'confirmed', 'Public' => 0]);

        $this->booking_repo->shouldReceive('update')
            ->once()
            ->andReturn(1);

        $this->addon_service->shouldReceive('save_booking_addons')
            ->once()
            ->withAnyArgs();

        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with($booking_id)
            ->once()
            ->andReturnUsing(static fn () => []);

        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')
            ->with($booking_id)
            ->once()
            ->andReturn(1);

        $this->booking_charge_repo->shouldReceive('create')
            ->once()
            ->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_does_not_replace_addons_when_payload_does_not_include_them(): void {
        $booking_id = 99;
        $data = $this->minimal_create_data(['booking_id' => $booking_id]);
        unset($data['addons']);

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();

        $this->validator->shouldReceive('validate')->once()->andReturn(true);

        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->booking_repo->shouldReceive('get_by_id')
            ->with($booking_id)
            ->once()
            ->andReturn($this->recurring_booking_record($booking_id, ['RecurringPatternId' => null]));

        $this->booking_repo->shouldReceive('update')
            ->once()
            ->andReturn(true);

        $this->addon_service->shouldReceive('save_booking_addons')->never();

        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with($booking_id)
            ->once()
            ->andReturnUsing(static fn () => []);

        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')
            ->with($booking_id)
            ->once()
            ->andReturn(1);

        $this->booking_charge_repo->shouldReceive('create')
            ->once()
            ->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_all_bookings_scope_updates_each_booking_in_the_pattern(): void {
        $booking_id = 99;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'description' => 'Updated series description',
            'edit_scope' => 'all_bookings',
            'addons' => [
                [
                    'addon_id' => 10,
                    'quantity' => 1,
                    'unit_price' => 5,
                    'description' => '',
                ],
            ],
        ]);

        $bookings = [
            $this->recurring_booking_record(99),
            $this->recurring_booking_record(100, [
                'StartDate' => '2026-06-08',
                'EndDate' => '2026-06-08',
            ]),
        ];

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($this->recurring_booking_record($booking_id));
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($bookings);
        $this->booking_repo->shouldReceive('update')->twice()->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->twice()->withAnyArgs();

        $this->pricing->shouldReceive('get_charge_snapshot')->with(99)->once()->andReturnUsing(static fn () => []);
        $this->pricing->shouldReceive('get_charge_snapshot')->with(100)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(99)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(100)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->twice()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_all_bookings_scope_propagates_no_invoice_required_flag(): void {
        $booking_id = 99;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'edit_scope' => 'all_bookings',
            'no_invoice_required' => 1,
        ]);

        $bookings = [
            $this->recurring_booking_record(99),
            $this->recurring_booking_record(100, [
                'StartDate' => '2026-06-08',
                'EndDate' => '2026-06-08',
            ]),
        ];

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($this->recurring_booking_record($booking_id));
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($bookings);
        $this->booking_repo->shouldReceive('update')
            ->twice()
            ->with(Mockery::on(static function (array $record): bool {
                return intval($record['NoInvoiceRequired'] ?? 0) === 1;
            }), Mockery::any())
            ->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->twice()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with(99)->once()->andReturnUsing(static fn () => []);
        $this->pricing->shouldReceive('get_charge_snapshot')->with(100)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(99)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(100)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->twice()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_this_and_future_scope_updates_only_split_future_bookings(): void {
        $booking_id = 100;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-08',
            'description' => 'Future series description',
            'edit_scope' => 'this_and_future',
            'addons' => [
                [
                    'addon_id' => 11,
                    'quantity' => 2,
                    'unit_price' => 7,
                    'description' => '',
                ],
            ],
        ]);

        $all_bookings = [
            $this->recurring_booking_record(99),
            $this->recurring_booking_record(100, ['StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']),
            $this->recurring_booking_record(101, ['StartDate' => '2026-06-15', 'EndDate' => '2026-06-15']),
        ];
        $future_bookings = [
            $this->recurring_booking_record(100, ['RecurringPatternId' => 201, 'StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']),
            $this->recurring_booking_record(101, ['RecurringPatternId' => 201, 'StartDate' => '2026-06-15', 'EndDate' => '2026-06-15']),
        ];

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($this->recurring_booking_record($booking_id, ['StartDate' => '2026-06-08', 'EndDate' => '2026-06-08']));
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($all_bookings);
        $this->recurring_pattern_service->shouldReceive('split_pattern_from_booking')
            ->with($pattern_id, $booking_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'new_pattern_id' => 201,
                'future_bookings' => $future_bookings,
                'previous_bookings' => [$all_bookings[0]],
            ]);
        $this->booking_repo->shouldReceive('update')->twice()->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->twice()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with(100)->once()->andReturnUsing(static fn () => []);
        $this->pricing->shouldReceive('get_charge_snapshot')->with(101)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(100)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(101)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->twice()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_this_only_scope_detaches_non_parent_booking_from_series(): void {
        $booking_id = 100;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'start_date' => '2026-06-08',
            'end_date' => '2026-06-08',
            'description' => 'Edited single occurrence',
            'edit_scope' => 'this_only',
        ]);

        $current = $this->recurring_booking_record($booking_id, [
            'StartDate' => '2026-06-08',
            'EndDate' => '2026-06-08',
        ]);

        $remaining = [
            $this->recurring_booking_record(99, [
                'StartDate' => '2026-06-01',
                'EndDate' => '2026-06-01',
            ]),
            $this->recurring_booking_record(101, [
                'StartDate' => '2026-06-15',
                'EndDate' => '2026-06-15',
            ]),
        ];

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($current);
        $this->recurring_pattern_service->shouldReceive('get_by_id')
            ->with($pattern_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'Id' => $pattern_id,
                'ParentBookingId' => 99,
                'OccurrenceCount' => 3,
                'IsActive' => 1,
            ]);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(['RecurringPatternId' => null], ['Id' => $booking_id])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($remaining);
        $this->recurring_pattern_service->shouldReceive('update_pattern')
            ->once()
            ->with($pattern_id, ['OccurrenceCount' => 2])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return ($record['Description'] ?? '') === 'Edited single occurrence';
            }), ['Id' => $booking_id])
            ->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->once()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with($booking_id)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with($booking_id)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_this_only_scope_promotes_next_booking_when_parent_is_detached(): void {
        $booking_id = 99;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'description' => 'Edited former parent occurrence',
            'edit_scope' => 'this_only',
        ]);

        $current = $this->recurring_booking_record($booking_id, [
            'StartDate' => '2026-06-01',
            'EndDate' => '2026-06-01',
        ]);

        $remaining = [
            $this->recurring_booking_record(100, [
                'StartDate' => '2026-06-08',
                'EndDate' => '2026-06-08',
            ]),
            $this->recurring_booking_record(101, [
                'StartDate' => '2026-06-15',
                'EndDate' => '2026-06-15',
            ]),
        ];

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($current);
        $this->recurring_pattern_service->shouldReceive('get_by_id')
            ->with($pattern_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'Id' => $pattern_id,
                'ParentBookingId' => $booking_id,
                'OccurrenceCount' => 3,
                'IsActive' => 1,
            ]);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(['RecurringPatternId' => null], ['Id' => $booking_id])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn($remaining);
        $this->recurring_pattern_service->shouldReceive('update_pattern')
            ->once()
            ->with($pattern_id, [
                'OccurrenceCount' => 2,
                'ParentBookingId' => 100,
                'StartDate' => '2026-06-08',
            ])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return ($record['Description'] ?? '') === 'Edited former parent occurrence';
            }), ['Id' => $booking_id])
            ->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->once()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with($booking_id)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with($booking_id)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_this_only_scope_deactivates_pattern_when_no_members_remain(): void {
        $booking_id = 99;
        $pattern_id = 200;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'description' => 'Edited last recurring booking',
            'edit_scope' => 'this_only',
        ]);

        $current = $this->recurring_booking_record($booking_id, [
            'StartDate' => '2026-06-01',
            'EndDate' => '2026-06-01',
        ]);

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($current);
        $this->recurring_pattern_service->shouldReceive('get_by_id')
            ->with($pattern_id)
            ->once()
            ->andReturnUsing(static fn () => [
                'Id' => $pattern_id,
                'ParentBookingId' => $booking_id,
                'OccurrenceCount' => 1,
                'IsActive' => 1,
            ]);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(['RecurringPatternId' => null], ['Id' => $booking_id])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('get_by_pattern_id')->with($pattern_id)->once()->andReturn([]);
        $this->recurring_pattern_service->shouldReceive('update_pattern')
            ->once()
            ->with($pattern_id, [
                'OccurrenceCount' => 0,
                'IsActive' => 0,
            ])
            ->andReturn(true);
        $this->booking_repo->shouldReceive('update')
            ->once()
            ->with(Mockery::on(static function (array $record): bool {
                return ($record['Description'] ?? '') === 'Edited last recurring booking';
            }), ['Id' => $booking_id])
            ->andReturn(true);
        $this->addon_service->shouldReceive('save_booking_addons')->once()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->with($booking_id)->once()->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with($booking_id)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->once()->andReturn(1);

        $result = $this->service->save($data);

        $this->assertSame($booking_id, $result);
    }

    /** @test */
    public function save_update_with_series_scope_rejects_schedule_changes(): void {
        $booking_id = 99;
        $data = $this->minimal_create_data([
            'booking_id' => $booking_id,
            'start_time' => '10:00:00',
            'edit_scope' => 'all_bookings',
        ]);

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($this->minimal_room());
        $this->booking_repo->shouldReceive('get_by_id')->with($booking_id)->once()->andReturn($this->recurring_booking_record($booking_id));

        $result = $this->service->save($data);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function save_uses_pending_status_when_require_approval_setting_is_true(): void {

    \Brain\Monkey\Functions\when('myvh_setting')
        ->alias(function ($key, $default = null) {
            return $key === 'booking.require_approval' ? true : $default;
        });

    $this->booking_repo->shouldReceive('begin')->once();
    $this->booking_repo->shouldReceive('commit')->once();

    $this->validator->shouldReceive('validate')->once()->andReturn(true);

    $this->room_service->shouldReceive('get')
        ->with(5)
        ->once()
        ->andReturn($this->minimal_room());

    $this->booking_repo->shouldReceive('create')
        ->once()
        ->with(Mockery::on(function ($record) {
            return ($record['Status'] ?? null) === 'pending';
        }))
        ->andReturn(10);

    $this->addon_service->shouldReceive('save_booking_addons')->once();
    $this->pricing->shouldReceive('get_charge_snapshot')->andReturnUsing(static fn () => []);
    $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
    $this->booking_charge_repo->shouldReceive('create')->andReturn(1);

    $data = $this->minimal_create_data();
    unset($data['status']);

    $result = $this->service->save($data);

    $this->assertSame(10, $result);
}

    // ── Tests: recalculate_booking_charges() ─────────────────────────────────

    /** @test */
    public function recalculate_booking_charges_returns_wp_error_for_invalid_id(): void {
        $result = $this->service->recalculate_booking_charges(0);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function recalculate_booking_charges_propagates_pricing_wp_error(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with(7)
            ->once()
            ->andReturn(new WP_Error('pricing', 'No rates found'));

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('pricing', $result->get_error_code());
    }

    /** @test */
    public function recalculate_booking_charges_returns_wp_error_when_delete_fails(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturnUsing(static fn () => []);

        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')
            ->with(7)
            ->once()
            ->andReturn(false);

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function recalculate_booking_charges_returns_wp_error_when_create_fails(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->andReturn(false);

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function recalculate_booking_charges_returns_true_on_success(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->andReturn(1);

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertTrue($result);
    }

    // ── Tests: cancel() ──────────────────────────────────────────────────────

    /** @test */
    public function cancel_delegates_to_repo_with_cancelled_status(): void {
        $this->booking_repo->shouldReceive('get_by_id')
            ->with(15)
            ->once()
            ->andReturn($this->recurring_booking_record(15));

        $this->booking_repo->shouldReceive('update')
            ->with(
                Mockery::on(fn($d) => ($d['Status'] ?? '') === 'cancelled'),
                ['Id' => 15]
            )
            ->once()
            ->andReturn(1);

        $result = $this->service->cancel(15);

        $this->assertSame(1, $result);
    }

    // ── Tests: delete() ──────────────────────────────────────────────────────

    /** @test */
    public function delete_returns_wp_error_for_invalid_id(): void {
        $result = $this->service->delete(0);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function delete_returns_wp_error_and_rolls_back_when_addon_delete_fails(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();

        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')
            ->with(5)
            ->once()
            ->andReturn(false);

        $result = $this->service->delete(5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function delete_returns_wp_error_and_rolls_back_when_booking_delete_fails(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();

        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_repo->shouldReceive('delete')->with(5)->andReturn(false);

        $result = $this->service->delete(5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function delete_returns_true_and_commits_on_success(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();

        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_repo->shouldReceive('delete')->with(5)->andReturn(true);

        $result = $this->service->delete(5);

        $this->assertTrue($result);
    }

    // ── Tests: move_booking() ────────────────────────────────────────────────

    /** @test */
    public function move_booking_returns_wp_error_when_room_not_found(): void {
        $this->room_service->shouldReceive('get')
            ->with(99)
            ->once()
            ->andReturn(null);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 99);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function move_booking_returns_wp_error_when_room_is_not_available(): void {
        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->availability->shouldReceive('booking_within_opening_hours')
            ->once()
            ->andReturn(true);

        $this->availability->shouldReceive('room_is_available')
            ->once()
            ->andReturn(false);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 5);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('validation', $result->get_error_code());
    }

    /** @test */
    public function move_booking_returns_1_on_success(): void {
        $this->room_service->shouldReceive('get')
            ->with(5)
            ->once()
            ->andReturn($this->minimal_room());

        $this->availability->shouldReceive('booking_within_opening_hours')
            ->once()
            ->andReturn(true);

        $this->availability->shouldReceive('room_is_available')
            ->once()
            ->andReturn(true);

        $this->booking_repo->shouldReceive('move_booking')
            ->once()
            ->andReturn(1);

        $result = $this->service->move_booking(1, '2026-06-01T09:00:00', '2026-06-01T11:00:00', 5);

        $this->assertSame(1, $result);
    }

    // ── Tests: get_last_warnings() ───────────────────────────────────────────

    /** @test */
    public function get_last_warnings_returns_empty_array_initially(): void {
        $this->assertSame([], $this->service->get_last_warnings());
    }

    // ── Tests: calculate_chargeable_hours (via save()) ───────────────────────

    /** @test */
    public function save_calculates_correct_chargeable_hours_for_two_hour_booking(): void {
        // Use CalcClosedHours = 1 so the service uses raw clock hours
        $room = $this->minimal_room(['CalcClosedHours' => 1]);

        $new_id = 55;

        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->validator->shouldReceive('validate')->once()->andReturn(true);
        $this->room_service->shouldReceive('get')->with(5)->once()->andReturn($room);
        $this->booking_repo->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function ($record) {
                // ChargeableHours should be 2.0 for a 09:00–11:00 booking
                return isset($record['ChargeableHours']) && abs($record['ChargeableHours'] - 2.0) < 0.001;
            }))
            ->andReturn($new_id);
        $this->addon_service->shouldReceive('save_booking_addons')->once()->withAnyArgs();
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturnUsing(static fn () => []);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->andReturn(1);

        $result = $this->service->save($this->minimal_create_data([
            'start_date' => '2026-06-01',
            'end_date'   => '2026-06-01',
            'start_time' => '09:00:00',
            'end_time'   => '11:00:00',
        ]));

        $this->assertSame($new_id, $result);
    }

    // ── Data Integrity Tests: delete/cancel ───────────────────────────────

    /** @test */
    public function delete_removes_all_related_charges_and_addons(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->with(77)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(77)->once()->andReturn(1);
        $this->booking_repo->shouldReceive('delete')->with(77)->once()->andReturn(true);

        $result = $this->service->delete(77);
        $this->assertTrue($result);
    }

    /** @test */
    public function delete_does_not_affect_unrelated_data(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('commit')->once();
        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->with(88)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(88)->once()->andReturn(1);
        $this->booking_repo->shouldReceive('delete')->with(88)->once()->andReturn(true);
        // Should NOT call delete for unrelated booking id 99
        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->with(99)->never();
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(99)->never();
        $this->booking_repo->shouldReceive('delete')->with(99)->never();

        $result = $this->service->delete(88);
        $this->assertTrue($result);
    }

    /** @test */
    public function delete_rolls_back_on_partial_failure(): void {
        $this->booking_repo->shouldReceive('begin')->once();
        $this->booking_repo->shouldReceive('rollback')->once();
        $this->booking_addon_repo->shouldReceive('delete_by_booking_id')->with(66)->once()->andReturn(1);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->with(66)->once()->andReturn(false); // Simulate failure
        $this->booking_repo->shouldReceive('delete')->with(66)->never();

        $result = $this->service->delete(66);
        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }
}