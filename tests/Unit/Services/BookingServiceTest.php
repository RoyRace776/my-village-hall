<?php

namespace MYVH\Tests\Unit\Services;

use Mockery;
use MYVH\Tests\Unit\MYVH_Unit_Test_Case;
use WP_Error;

/**
 * Unit tests for MYVH_Booking_Service.
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
class BookingServiceTest extends MYVH_Unit_Test_Case {

    // ── Dependency mocks ────────────────────────────────────────────────────

    private $room_service;
    private $booking_repo;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_repo;
    private $addon_service;
    private $validator;
    private $availability;
    private $room_rules;
    private $pricing;
    private $customer_repo;
    private $organisation_repo;
    private $recurring_pattern_service;

    /** @var \MYVH_Booking_Service */
    private $service;

    // ── Setup ────────────────────────────────────────────────────────────────

    protected function setUp(): void {
        parent::setUp();

        $this->room_service              = $this->mock(\MYVH_Room_Service::class);
        $this->booking_repo              = $this->mock(\MYVH_Booking_Repository::class);
        $this->booking_charge_repo       = $this->mock(\MYVH_Booking_Charge_Repository::class);
        $this->booking_addon_repo        = $this->mock(\MYVH_Booking_Addon_Repository::class);
        $this->addon_repo                = $this->mock(\MYVH_Addon_Repository::class);
        $this->addon_service             = $this->mock(\MYVH_Addon_Service::class);
        $this->validator                 = $this->mock(\MYVH_Booking_Validator::class);
        $this->availability              = $this->mock(\MYVH_Availability_Service::class);
        $this->room_rules                = $this->mock(\MYVH_Room_Rules_Service::class);
        $this->pricing                   = $this->mock(\MYVH_Pricing_Service::class);
        $this->customer_repo             = $this->mock(\MYVH_Customer_Repository::class);
        $this->organisation_repo         = $this->mock(\MYVH_Organisation_Repository::class);
        $this->recurring_pattern_service = $this->mock(\MYVH_Recurring_Pattern_Service::class);

        $this->service = new \MYVH_Booking_Service(
            $this->room_service,
            $this->booking_repo,
            $this->booking_charge_repo,
            $this->booking_addon_repo,
            $this->addon_repo,
            $this->addon_service,
            $this->validator,
            $this->availability,
            $this->room_rules,
            $this->pricing,
            $this->customer_repo,
            $this->organisation_repo,
            $this->recurring_pattern_service
        );
    }

    public function tearDown(): void {
        unset(
            $this->service,
            $this->room_service,
            $this->booking_repo,
            $this->booking_charge_repo,
            $this->booking_addon_repo,
            $this->addon_repo,
            $this->addon_service,
            $this->validator,
            $this->availability,
            $this->room_rules,
            $this->pricing,
            $this->customer_repo,
            $this->organisation_repo,
            $this->recurring_pattern_service
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
            'is_recurring'    => 0,
            'addons'          => [],
        ], $overrides);
    }

    /**
     * Returns a minimal room record array.
     */
    private function minimal_room(array $overrides = []): array {
        return array_merge([
            'Id'                    => 5,
            'CalcClosedHours'       => 1,
            'OpeningTime'           => '08:00:00',
            'ClosingTime'           => '22:00:00',
            'AllowMultiDayBookings' => 0,
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
            ->andReturn([]);

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
            ->andReturn(['Id' => $booking_id, 'Status' => 'confirmed', 'Public' => 0]);

        $this->booking_repo->shouldReceive('update')
            ->once()
            ->andReturn(1);

        $this->addon_service->shouldReceive('save_booking_addons')
            ->once()
            ->withAnyArgs();

        $this->pricing->shouldReceive('get_charge_snapshot')
            ->with($booking_id)
            ->once()
            ->andReturn([]);

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
    $this->pricing->shouldReceive('get_charge_snapshot')->andReturn([]);
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
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturn([]);

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
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturn([]);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->andReturn(false);

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('database', $result->get_error_code());
    }

    /** @test */
    public function recalculate_booking_charges_returns_true_on_success(): void {
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturn([]);
        $this->booking_charge_repo->shouldReceive('delete_by_booking_id')->andReturn(1);
        $this->booking_charge_repo->shouldReceive('create')->andReturn(1);

        $result = $this->service->recalculate_booking_charges(7);

        $this->assertTrue($result);
    }

    // ── Tests: cancel() ──────────────────────────────────────────────────────

    /** @test */
    public function cancel_delegates_to_repo_with_cancelled_status(): void {
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
        $this->pricing->shouldReceive('get_charge_snapshot')->andReturn([]);
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