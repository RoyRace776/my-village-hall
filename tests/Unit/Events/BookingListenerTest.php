<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery;
use MYVH\Email\EmailService;
use MYVH\Events\BookingListener;
use MYVH\Tests\Unit\UnitTestCase;

class BookingListenerTest extends UnitTestCase
{
    /** @var EmailService&\Mockery\MockInterface */
    private $email_service;
    private BookingListener $listener;

    protected function setUp(): void
    {
        parent::setUp();

        $this->email_service = Mockery::mock(EmailService::class);
        $this->listener      = new BookingListener($this->email_service);
    }

    // ── register ─────────────────────────────────────────────────────────

    /** @test */
    public function register_hooks_all_booking_events(): void
    {
        $expected_hooks = [
            'myvh_event_booking.created',
            'myvh_event_booking.confirmed',
            'myvh_event_booking.cancelled',
            'myvh_event_booking.updated',
        ];

        foreach ($expected_hooks as $hook) {
            Functions\expect('add_action')
                ->with($hook, Mockery::type('array'))
                ->once();
        }

        $this->listener->register();
        $this->addToAssertionCount(1);
    }

    // ── handle_booking_confirmed ─────────────────────────────────────────

    /** @test */
    public function handle_booking_confirmed_sends_email_to_resolved_recipient(): void
    {
        // Use a test double that overrides the protected helper methods
        $listener = new class($this->email_service) extends BookingListener {
            protected function resolve_email(int $booking_id): string
            {
                return 'alice@example.com';
            }

            protected function get_booking_template_vars(int $booking_id): array
            {
                return ['booking_ref' => '#10', 'customer_name' => 'Alice'];
            }
        };

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['to'] === 'alice@example.com' &&
                $args['template'] === 'booking-confirmed'
            ));

        $listener->handle_booking_confirmed(['booking_id' => 10]);
        $this->addToAssertionCount(1);
    }

    // ── handle_booking_cancelled ─────────────────────────────────────────

    /** @test */
    public function handle_booking_cancelled_sends_cancellation_email(): void
    {
        $listener = new class($this->email_service) extends BookingListener {
            protected function resolve_email(int $booking_id): string
            {
                return 'bob@example.com';
            }

            protected function get_booking_template_vars(int $booking_id): array
            {
                return ['booking_ref' => '#5'];
            }
        };

        $this->email_service->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn($args) =>
                $args['to'] === 'bob@example.com' &&
                $args['template'] === 'booking-cancelled'
            ));

        $listener->handle_booking_cancelled(['booking_id' => 5]);
        $this->addToAssertionCount(1);
    }

    // ── handle_booking_updated ────────────────────────────────────────────

    /** @test */
    public function handle_booking_updated_does_not_throw(): void
    {
        $this->listener->handle_booking_updated(['booking_id' => 5]);
        $this->assertTrue(true); // no exception = pass
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['myvh_container']);
        parent::tearDown();
    }
}
