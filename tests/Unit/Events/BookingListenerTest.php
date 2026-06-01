<?php

namespace MYVH\Tests\Unit\Events;

use Brain\Monkey\Functions;
use Mockery\MockInterface;
use Mockery;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
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

        Functions\stubs([
            'date_i18n' => static fn($format, $timestamp) => date($format, (int) $timestamp),
            'get_option' => static fn($option) => $option === 'date_format' ? 'j M Y' : '',
        ]);
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

    /** @test */
    public function handle_booking_confirmed_skips_email_when_send_confirmation_email_is_zero(): void
    {
        $this->email_service->shouldReceive('send')->never();

        $this->listener->handle_booking_confirmed([
            'booking_id' => 22,
            'send_confirmation_email' => 0,
        ]);

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

    /** @test */
    public function handle_booking_cancelled_skips_email_when_send_confirmation_email_is_zero(): void
    {
        $this->email_service->shouldReceive('send')->never();

        $this->listener->handle_booking_cancelled([
            'booking_id' => 6,
            'send_confirmation_email' => 0,
        ]);

        $this->addToAssertionCount(1);
    }

    // ── handle_booking_updated ────────────────────────────────────────────

    /** @test */
    public function handle_booking_updated_does_not_throw(): void
    {
        $this->listener->handle_booking_updated(['booking_id' => 5]);
        $this->assertTrue(true); // no exception = pass
    }

    /** @test */
    public function get_booking_template_vars_uses_charge_and_addon_totals_for_booking_amount(): void
    {
        $booking_service = Mockery::mock(BookingService::class);
        $customer_service = Mockery::mock(CustomerService::class);

        $booking_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): array => [
                'Id' => 10,
                'CustomerId' => 7,
                'CustomerName' => 'Alice',
                'Description' => 'Village hall booking',
                'StartDate' => '2026-06-01',
                'StartTime' => '10:00',
                'EndTime' => '12:00',
                'VenueName' => 'Main Hall',
                'RoomName' => 'Room A',
                'Rate' => '15',
            ]);
        $booking_service->shouldReceive('get_charges_for_booking')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): array => [
                ['TotalAmount' => '12.50'],
                ['TotalAmount' => '7.25'],
            ]);
        $booking_service->shouldReceive('get_addons_for_booking')
            ->once()
            ->with(10)
            ->andReturnUsing(static fn(): array => [
                ['TotalAmount' => '3.50'],
                ['TotalAmount' => '1.25'],
            ]);

        $customer_service->shouldReceive('get_by_id')
            ->once()
            ->with(7)
            ->andReturnUsing(static fn(): array => [
                'AddressLine1' => '1 High Street',
                'PostCode' => 'AB1 2CD',
            ]);

        $this->email_service->shouldReceive('get_branding')
            ->once()
            ->andReturnUsing(static fn(): array => [
                'logo_url' => 'https://example.com/logo.png',
                'site_name' => 'My Village Hall',
                'site_url' => 'https://example.com',
            ]);

        $GLOBALS['myvh_container'] = new class($booking_service, $customer_service) {
            public function __construct(
                private MockInterface $booking_service,
                private MockInterface $customer_service
            ) {
            }

            public function get(string $class): MockInterface
            {
                return match ($class) {
                    BookingService::class => $this->booking_service,
                    CustomerService::class => $this->customer_service,
                    default => throw new \RuntimeException('Unexpected service request: ' . $class),
                };
            }
        };

        $reflection = new \ReflectionMethod(BookingListener::class, 'get_booking_template_vars');
        $reflection->setAccessible(true);

        /** @var array<string, mixed> $template_vars */
        $template_vars = $reflection->invoke($this->listener, 10);

        $this->assertSame('24.50', $template_vars['booking_amount']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['myvh_container']);
        parent::tearDown();
    }
}
