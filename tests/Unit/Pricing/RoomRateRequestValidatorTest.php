<?php

namespace MYVH\Tests\Unit\Pricing;

use MYVH\Pricing\RoomRateRequestValidator;
use MYVH\Tests\Unit\UnitTestCase;
use WP_Error;

class RoomRateRequestValidatorTest extends UnitTestCase
{
    private RoomRateRequestValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new RoomRateRequestValidator();
    }

    /** @test */
    public function validate_returns_error_when_minimum_hours_missing(): void
    {
        $result = $this->validator->validate([
            'room_id' => 1,
            'name' => 'Standard Rate',
            'charge_type' => 'per_hour',
            'rate' => 12.5,
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Minimum hours must be greater than zero', $result->get_error_message());
    }

    /** @test */
    public function validate_accepts_positive_minimum_hours(): void
    {
        $result = $this->validator->validate([
            'room_id' => 1,
            'name' => 'Standard Rate',
            'charge_type' => 'per_hour',
            'rate' => 12.5,
            'minimum_hours' => 1.5,
        ]);

        $this->assertTrue($result);
    }

    /** @test */
    public function validate_rejects_fixed_rate_with_schedule_fields(): void
    {
        $result = $this->validator->validate([
            'room_id' => 1,
            'name' => 'Flat rate',
            'charge_type' => 'fixed',
            'rate' => 50,
            'minimum_hours' => 1,
            'day_of_week' => '1',
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Fixed rates cannot be limited to a day/time schedule', $result->get_error_message());
    }

    /** @test */
    public function validate_rejects_end_time_not_later_than_start_time(): void
    {
        $result = $this->validator->validate([
            'room_id' => 1,
            'name' => 'Morning schedule',
            'charge_type' => 'per_hour',
            'rate' => 15,
            'minimum_hours' => 1,
            'day_of_week' => '2',
            'start_time' => '12:00',
            'end_time' => '11:00',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('End time must be later than start time', $result->get_error_message());
    }

    /** @test */
    public function validate_rejects_non_quarter_hour_start_time(): void
    {
        $result = $this->validator->validate([
            'room_id' => 1,
            'name' => 'Midday slot',
            'charge_type' => 'per_hour',
            'rate' => 12.5,
            'minimum_hours' => 1,
            'day_of_week' => '2',
            'start_time' => '09:10',
            'end_time' => '10:00',
        ]);

        $this->assertInstanceOf(WP_Error::class, $result);
        $this->assertSame('Start time must use 15 minute intervals', $result->get_error_message());
    }
}