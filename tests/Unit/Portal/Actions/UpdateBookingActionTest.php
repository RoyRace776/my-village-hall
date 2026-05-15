<?php

namespace MYVH\Tests\Unit\Portal\Actions;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Portal\Actions\UpdateBookingAction;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class UpdateBookingActionTest extends UnitTestCase {
    private $booking_service;
    private $customer_service;
    private $organisation_member_repo;
    private $client_admin_service;
    private UpdateBookingAction $action;

    protected function setUp(): void {
        parent::setUp();

        $this->booking_service = $this->mock(BookingService::class);
        $this->customer_service = $this->mock(CustomerService::class);
        $this->organisation_member_repo = $this->mock(OrganisationMemberRepository::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);

        $this->action = new UpdateBookingAction(
            $this->booking_service,
            $this->customer_service,
            $this->organisation_member_repo,
            $this->client_admin_service
        );

        Functions\stubs([
            'get_current_user_id' => 21,
            'get_current_blog_id' => 7,
            'is_wp_error' => false,
        ]);
    }

    /** @test */
    public function execute_blocks_when_can_edit_denies_the_booking(): void {
        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 9]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);

        $this->booking_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(42)
            ->andReturn([
                'Id' => 42,
                'CustomerId' => 9,
                'OrganisationId' => 0,
                'RoomId' => 5,
                'StartDate' => '2026-05-01',
                'EndDate' => '2026-05-01',
                'StartTime' => '10:00:00',
                'EndTime' => '12:00:00',
                'Description' => 'Existing booking',
                'Status' => 'pending',
                'Public' => 1,
                'NoInvoiceRequired' => 0,
            ]);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => false, 'reason' => 'Invoiced bookings cannot be edited.']);

        $this->booking_service->shouldReceive('save')->never();

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invoiced bookings cannot be edited.');

        $this->action->execute([
            'booking_id' => 42,
            'start_date' => '2026-05-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
        ]);
    }

    /** @test */
    public function execute_saves_when_can_edit_allows_the_booking(): void {
        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 9]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);

        $this->booking_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(43)
            ->andReturn([
                'Id' => 43,
                'CustomerId' => 9,
                'OrganisationId' => 0,
                'RoomId' => 5,
                'StartDate' => '2026-05-01',
                'EndDate' => '2026-05-01',
                'StartTime' => '10:00:00',
                'EndTime' => '12:00:00',
                'Description' => 'Existing booking',
                'Status' => 'pending',
                'Public' => 1,
                'NoInvoiceRequired' => 0,
            ]);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => true, 'reason' => '']);

        $this->booking_service->shouldReceive('save')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return \intval($payload['booking_id'] ?? 0) === 43
                    && ($payload['description'] ?? '') === 'Changed booking';
            }))
            ->andReturn(43);

        $this->action->execute([
            'booking_id' => 43,
            'start_date' => '2026-05-01',
            'start_time' => '10:00',
            'end_time' => '12:00',
            'description' => 'Changed booking',
        ]);

        $this->addToAssertionCount(1);
    }
}