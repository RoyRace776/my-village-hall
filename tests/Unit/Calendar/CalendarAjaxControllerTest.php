<?php

namespace MYVH\Tests\Unit\Calendar;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Calendar\CalendarAjaxController;
use MYVH\Calendar\CalendarService;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationMemberRepository;
use MYVH\Portal\ClientAdminService;
use MYVH\Pricing\RoomRateService;
use MYVH\Rooms\RoomRepository;
use MYVH\Rooms\RoomVisibilityService;
use MYVH\Tests\Unit\UnitTestCase;

class CalendarAjaxControllerTest extends UnitTestCase {
    private $calendar_service;
    private $booking_service;
    private $room_repository;
    private $customer_service;
    private $organisation_member_repo;
    private $client_admin_service;
    private $room_rate_service;
    private $room_visibility_service;
    private CalendarAjaxController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->calendar_service         = $this->mock(CalendarService::class);
        $this->booking_service          = $this->mock(BookingService::class);
        $this->room_repository          = $this->mock(RoomRepository::class);
        $this->customer_service         = $this->mock(CustomerService::class);
        $this->organisation_member_repo = $this->mock(OrganisationMemberRepository::class);
        $this->client_admin_service     = $this->mock(ClientAdminService::class);
        $this->room_rate_service        = $this->mock(RoomRateService::class);
        $this->room_visibility_service  = $this->mock(RoomVisibilityService::class);

        $this->controller = new CalendarAjaxController(
            $this->calendar_service,
            $this->booking_service,
            $this->room_repository,
            $this->customer_service,
            $this->organisation_member_repo,
            $this->client_admin_service,
            $this->room_rate_service,
            $this->room_visibility_service
        );

        Functions\stubs([
            'check_ajax_referer' => true,
            'current_user_can' => true,
            'is_user_logged_in' => true,
            'absint' => fn($value) => abs((int) $value),
            'wp_unslash' => static fn($value) => $value,
            'get_current_user_id' => 21,
            'get_current_blog_id' => 7,
        ]);

        Functions\when('wp_send_json')->alias(function ($data = null, $status_code = null) {
            throw new CalendarJsonResponseException(true, $data, (int) ($status_code ?? 200));
        });

        Functions\when('wp_send_json_success')->alias(function ($data = null, $status_code = null) {
            throw new CalendarJsonResponseException(true, $data, (int) ($status_code ?? 200));
        });

        Functions\when('wp_send_json_error')->alias(function ($data = null, $status_code = null) {
            throw new CalendarJsonResponseException(false, $data, (int) ($status_code ?? 400));
        });
    }

    protected function tearDown(): void {
        $_GET = [];
        $_POST = [];

        parent::tearDown();
    }

    /** @test */
    public function get_rooms_only_returns_rooms_with_active_rates_for_the_selected_venue(): void {
        $_GET = [
            'context' => 'admin',
            'venue_id' => 2,
        ];

        $this->room_repository->shouldReceive('get_all_with_venues')
            ->once()
            ->andReturn([
                [
                    'Id' => 10,
                    'Name' => 'Main Hall',
                    'VenueId' => 2,
                    'VenueName' => 'Village Hall',
                    'Colour' => '#123456',
                    'AllowMultiDayBookings' => 1,
                ],
                [
                    'Id' => 11,
                    'Name' => 'Kitchen',
                    'VenueId' => 2,
                    'VenueName' => 'Village Hall',
                    'Colour' => '#654321',
                    'AllowMultiDayBookings' => 0,
                ],
                [
                    'Id' => 12,
                    'Name' => 'Stage',
                    'VenueId' => 4,
                    'VenueName' => 'Annex',
                    'Colour' => '#222222',
                    'AllowMultiDayBookings' => 1,
                ],
            ]);

        $this->room_visibility_service->shouldReceive('filter_rooms_for_user')
            ->once()
            ->andReturnArg(0);

        $this->room_rate_service->shouldReceive('get_room_ids_with_active_rates')
            ->once()
            ->with([10, 11, 12])
            ->andReturnUsing(static fn(): array => [11, 12]);

        $response = $this->capture_json_response(function (): void {
            $this->controller->get_rooms();
        });

        $this->assertTrue($response->success);
        $this->assertCount(1, $response->data);
        $this->assertSame('Kitchen', $response->data[0]['name']);
        $this->assertSame(11, $response->data[0]['id']);
        $this->assertSame(2, $response->data[0]['venue_id']);
        $this->assertSame(0, $response->data[0]['allow_multiday']);
    }

    /** @test */
    public function get_rooms_by_venue_returns_legacy_admin_response_with_only_bookable_rooms(): void {
        $_POST = [
            'venue_id' => 2,
            'nonce' => 'example',
        ];

        $this->room_repository->shouldReceive('get_all_with_venues')
            ->once()
            ->andReturn([
                [
                    'Id' => 10,
                    'Name' => 'Main Hall',
                    'Description' => 'Large room',
                    'VenueId' => 2,
                    'VenueName' => 'Village Hall',
                ],
                [
                    'Id' => 11,
                    'Name' => 'Kitchen',
                    'Description' => 'Prep area',
                    'VenueId' => 2,
                    'VenueName' => 'Village Hall',
                ],
            ]);

        $this->room_visibility_service->shouldReceive('filter_rooms_for_user')
            ->once()
            ->andReturnArg(0);

        $this->room_rate_service->shouldReceive('get_room_ids_with_active_rates')
            ->once()
            ->with([10, 11])
            ->andReturnUsing(static fn(): array => [10]);

        $response = $this->capture_json_response(function (): void {
            $this->controller->get_rooms_by_venue();
        });

        $this->assertTrue($response->success);
        $this->assertCount(1, $response->data['rooms']);
        $this->assertSame(10, $response->data['rooms'][0]['Id']);
        $this->assertSame('Main Hall', $response->data['rooms'][0]['Name']);
    }

    /** @test */
    public function get_booking_redacts_no_invoice_required_for_non_privileged_viewers(): void {
        $_GET = [
            'booking_id' => 55,
            'nonce' => 'example',
        ];

        Functions\when('current_user_can')->alias(static fn(string $capability): bool => false);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->with(21)
            ->once()
            ->andReturnUsing(static fn(): array => ['Id' => 9]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->with(21, 7)
            ->twice()
            ->andReturn(false);

        $this->booking_service->shouldReceive('get_all_with_details')
            ->with(['customer_id' => 9])
            ->once()
            ->andReturn([
                [
                    'Id' => 55,
                    'CustomerId' => 9,
                    'RoomId' => 3,
                    'OrganisationId' => 0,
                    'Status' => 'confirmed',
                    'Description' => 'Community lunch',
                    'Public' => 1,
                    'NoInvoiceRequired' => 1,
                ],
            ]);

        $this->booking_service->shouldReceive('can_edit')->once()->andReturnUsing(static fn(): array => ['can_edit' => false, 'reason' => '']);
        $this->booking_service->shouldReceive('can_delete')->once()->andReturnUsing(static fn(): array => ['can_delete' => false, 'reason' => '']);
        $this->booking_service->shouldReceive('get_charges_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_addons_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_deposit_items_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_expected_deposit_for_booking')->with(55)->once()->andReturn(null);

        $response = $this->capture_json_response(function (): void {
            $this->controller->get_booking();
        });

        $this->assertTrue($response->success);
        $this->assertArrayNotHasKey('NoInvoiceRequired', $response->data['booking']);
        $this->assertFalse($response->data['can_manage_no_invoice_required']);
    }

    /** @test */
    public function get_booking_includes_no_invoice_required_for_client_admins(): void {
        $_GET = [
            'booking_id' => 55,
            'nonce' => 'example',
        ];

        Functions\when('current_user_can')->alias(static fn(string $capability): bool => false);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->with(21)
            ->once()
            ->andReturnUsing(static fn(): array => ['Id' => 9]);

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->with(21, 7)
            ->twice()
            ->andReturn(true);

        $this->booking_service->shouldReceive('get_by_id_with_details')
            ->with(55)
            ->once()
            ->andReturnUsing(static fn(): array => [
                'Id' => 55,
                'CustomerId' => 9,
                'RoomId' => 3,
                'OrganisationId' => 0,
                'Status' => 'confirmed',
                'Description' => 'Committee meeting',
                'Public' => 1,
                'NoInvoiceRequired' => 1,
            ]);

        $this->booking_service->shouldReceive('can_edit')->once()->andReturnUsing(static fn(): array => ['can_edit' => true, 'reason' => '']);
        $this->booking_service->shouldReceive('can_delete')->once()->andReturnUsing(static fn(): array => ['can_delete' => true, 'reason' => '']);
        $this->booking_service->shouldReceive('get_charges_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_addons_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_deposit_items_for_booking')->with(55)->once()->andReturn([]);
        $this->booking_service->shouldReceive('get_expected_deposit_for_booking')->with(55)->once()->andReturn(null);

        $response = $this->capture_json_response(function (): void {
            $this->controller->get_booking();
        });

        $this->assertTrue($response->success);
        $this->assertSame(1, $response->data['booking']['NoInvoiceRequired']);
        $this->assertTrue($response->data['can_manage_no_invoice_required']);
    }

    /** @test */
    public function update_event_blocks_portal_updates_when_can_edit_denies_the_booking(): void {
        $_POST = [
            'context' => 'portal',
            'booking_id' => 55,
            'start' => '2026-04-15 10:00:00',
            'end' => '2026-04-15 12:00:00',
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(true);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 9]);

        $this->booking_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(55)
            ->andReturn([
                'Id' => 55,
                'CustomerId' => 9,
                'OrganisationId' => 0,
                'Status' => 'pending',
            ]);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => false, 'reason' => 'Invoiced bookings cannot be edited.']);

        $this->calendar_service->shouldReceive('update_event')->never();

        $response = $this->capture_json_response(function (): void {
            $this->controller->update_event();
        });

        $this->assertFalse($response->success);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Invoiced bookings cannot be edited.', $response->data);
    }

    /** @test */
    public function update_event_allows_portal_updates_for_accessible_bookings_with_edit_permission(): void {
        $_POST = [
            'context' => 'portal',
            'booking_id' => 56,
            'start' => '2026-04-15 10:00:00',
            'end' => '2026-04-15 12:00:00',
            'text' => 'Updated booking',
        ];

        $this->client_admin_service->shouldReceive('can_administer_blog')
            ->once()
            ->with(21, 7)
            ->andReturn(false);

        $this->customer_service->shouldReceive('get_by_user_id')
            ->once()
            ->with(21)
            ->andReturn(['Id' => 9]);

        $this->booking_service->shouldReceive('get_by_id_with_details')
            ->once()
            ->with(56)
            ->andReturn([
                'Id' => 56,
                'CustomerId' => 9,
                'OrganisationId' => 0,
                'Status' => 'pending',
            ]);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => true, 'reason' => '']);

        $this->calendar_service->shouldReceive('update_event')
            ->once()
            ->with(
                \Mockery::on(static function (array $request): bool {
                    return ($request['context'] ?? '') === 'portal'
                        && intval($request['booking_id'] ?? 0) === 56;
                })
            )
            ->andReturn(['id' => 56]);

        $response = $this->capture_json_response(function (): void {
            $this->controller->update_event();
        });

        $this->assertTrue($response->success);
        $this->assertSame(['id' => 56], $response->data);
    }

    private function capture_json_response(callable $callback): CalendarJsonResponseException {
        try {
            $callback();
        } catch (CalendarJsonResponseException $response) {
            return $response;
        }

        $this->fail('Expected JSON response to be sent.');
    }
}

class CalendarJsonResponseException extends \RuntimeException {
    public bool $success;
    public $data;
    public int $statusCode;

    public function __construct(bool $success, $data, int $statusCode) {
        parent::__construct('JSON response intercepted');

        $this->success = $success;
        $this->data = $data;
        $this->statusCode = $statusCode;
    }
}