<?php

namespace MYVH\Tests\Unit\Bookings;

use MYVH\Bookings\Booking;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\Services\BookingRecipientResolver;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Tests\Unit\UnitTestCase;

class BookingRecipientResolverTest extends UnitTestCase {
    protected function tearDown(): void {
        unset($GLOBALS['myvh_container']);
        parent::tearDown();
    }

    /** @test */
    public function resolve_for_booking_uses_organisation_contact_email_when_flag_enabled(): void {
        \Brain\Monkey\Functions\stubs([
            'sanitize_email' => fn($v) => (string) $v,
            'is_email' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            'get_option' => fn($k) => $k === 'admin_email' ? 'admin@example.org' : null,
        ]);

        $booking = $this->mock(Booking::class);
        $booking->shouldReceive('organisationId')->andReturn(50);
        $booking->shouldReceive('customerId')->never();

        $booking_repo = $this->mock(BookingRepository::class);
        $booking_repo->shouldReceive('get')->once()->with(1001)->andReturn($booking);

        $customer_repo = $this->mock(CustomerRepository::class);

        $organisation_repo = $this->mock(OrganisationRepository::class);
        $organisation_repo->shouldReceive('get_by_id')
            ->once()
            ->with(50)
            ->andReturn([
                'Id' => 50,
                'SendBookingEmailsToOrganisation' => 1,
                'ContactEmail' => 'org@example.org',
            ]);

        $GLOBALS['myvh_container'] = new class($booking_repo, $customer_repo, $organisation_repo) {
            public function __construct(
                private $booking_repo,
                private $customer_repo,
                private $organisation_repo
            ) {
            }

            public function get(string $class) {
                if ($class === BookingRepository::class) {
                    return $this->booking_repo;
                }
                if ($class === CustomerRepository::class) {
                    return $this->customer_repo;
                }
                if ($class === OrganisationRepository::class) {
                    return $this->organisation_repo;
                }

                return null;
            }
        };

        $resolver = new BookingRecipientResolver();

        $this->assertSame('org@example.org', $resolver->resolve_for_booking(1001));
    }

    /** @test */
    public function resolve_for_booking_uses_customer_email_when_organisation_flag_disabled(): void {
        \Brain\Monkey\Functions\stubs([
            'sanitize_email' => fn($v) => (string) $v,
            'is_email' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) !== false,
            'get_option' => fn($k) => $k === 'admin_email' ? 'admin@example.org' : null,
        ]);

        $booking = $this->mock(Booking::class);
        $booking->shouldReceive('organisationId')->andReturn(51);
        $booking->shouldReceive('customerId')->andReturn(77);

        $booking_repo = $this->mock(BookingRepository::class);
        $booking_repo->shouldReceive('get')->once()->with(1002)->andReturn($booking);

        $customer_repo = $this->mock(CustomerRepository::class);
        $customer_repo->shouldReceive('get_by_id')
            ->once()
            ->with(77)
            ->andReturn(['Id' => 77, 'Email' => 'booker@example.org']);

        $organisation_repo = $this->mock(OrganisationRepository::class);
        $organisation_repo->shouldReceive('get_by_id')
            ->once()
            ->with(51)
            ->andReturn([
                'Id' => 51,
                'SendBookingEmailsToOrganisation' => 0,
                'ContactEmail' => 'org@example.org',
            ]);

        $GLOBALS['myvh_container'] = new class($booking_repo, $customer_repo, $organisation_repo) {
            public function __construct(
                private $booking_repo,
                private $customer_repo,
                private $organisation_repo
            ) {
            }

            public function get(string $class) {
                if ($class === BookingRepository::class) {
                    return $this->booking_repo;
                }
                if ($class === CustomerRepository::class) {
                    return $this->customer_repo;
                }
                if ($class === OrganisationRepository::class) {
                    return $this->organisation_repo;
                }

                return null;
            }
        };

        $resolver = new BookingRecipientResolver();

        $this->assertSame('booker@example.org', $resolver->resolve_for_booking(1002));
    }
}
