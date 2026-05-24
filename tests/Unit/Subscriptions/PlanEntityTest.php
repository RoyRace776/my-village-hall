<?php

declare(strict_types=1);

namespace MYVH\Tests\Unit\Subscriptions;

use MYVH\Subscriptions\Entities\Plan;
use MYVH\Tests\Unit\UnitTestCase;

class PlanEntityTest extends UnitTestCase {
    /** @test */
    public function get_booking_limit_prefers_explicit_nullable_column_over_legacy_features(): void {
        $plan = Plan::fromArray([
            'id' => 7,
            'plan_key' => 'standard',
            'booking_limit' => null,
            'features' => wp_json_encode(['booking_limit' => 40]),
        ]);

        $this->assertNull($plan->getBookingLimit());
    }

    /** @test */
    public function get_booking_limit_falls_back_to_legacy_features_when_column_is_missing(): void {
        $plan = Plan::fromArray([
            'id' => 8,
            'plan_key' => 'legacy',
            'features' => wp_json_encode(['booking_limit' => 25]),
        ]);

        $this->assertSame(25, $plan->getBookingLimit());
    }
}
