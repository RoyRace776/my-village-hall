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
}