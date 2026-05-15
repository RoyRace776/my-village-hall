<?php

namespace MYVH\Tests\Unit\Portal\Actions;

use Brain\Monkey\Functions;
use MYVH\Portal\Actions\TestRoomRateScheduleAction;
use MYVH\Pricing\PricingService;
use MYVH\Tests\Unit\UnitTestCase;

class TestRoomRateScheduleActionTest extends UnitTestCase {
    private $pricing_service;
    private TestRoomRateScheduleAction $action;

    protected function setUp(): void {
        parent::setUp();

        $this->pricing_service = $this->mock(PricingService::class);
        $this->action = new TestRoomRateScheduleAction($this->pricing_service);

        Functions\stubs([
            'is_wp_error' => static fn($value): bool => $value instanceof \WP_Error,
        ]);
    }

    /** @test */
    public function execute_returns_validation_error_when_room_is_missing(): void {
        $result = $this->action->execute([
            'start_datetime' => '2026-06-01T09:00',
            'end_datetime' => '2026-06-01T10:00',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('Room is required', $result->get_error_message());
    }

    /** @test */
    public function execute_calls_pricing_service_with_parsed_inputs(): void {
        $this->pricing_service->shouldReceive('test_room_rate_schedule')
            ->once()
            ->with(5, 2, '2026-06-01', '09:00:00', '2026-06-01', '11:30:00')
            ->andReturn([
                'room_rate_id' => 10,
                'charge_type' => 'per_hour',
                'quantity' => 2.5,
                'unit_price' => 12.0,
                'total' => 30.0,
                'segments' => [],
                'validity_reference_date' => '2026-05-15',
            ]);

        $result = $this->action->execute([
            'room_id' => 5,
            'organisation_type_id' => 2,
            'start_datetime' => '2026-06-01T09:00',
            'end_datetime' => '2026-06-01T11:30',
        ]);

        $this->assertIsArray($result);
        $this->assertSame(5, $result['room_id']);
        $this->assertSame(2, $result['organisation_type_id']);
        $this->assertSame(30.0, $result['charge']['total']);
    }

    /** @test */
    public function execute_rejects_non_quarter_hour_start_time(): void {
        $result = $this->action->execute([
            'room_id' => 5,
            'organisation_type_id' => 2,
            'start_datetime' => '2026-06-01T09:10',
            'end_datetime' => '2026-06-01T10:00',
        ]);

        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('Start time must use 15 minute intervals', $result->get_error_message());
    }
}
