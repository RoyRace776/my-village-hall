<?php

namespace MYVH\Tests\Unit\Calendar;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Calendar\CalendarAjaxController;
use MYVH\Calendar\CalendarService;
use MYVH\Customers\CustomerService;
use MYVH\Portal\ClientAdminService;
use MYVH\Pricing\RoomRateService;
use MYVH\Rooms\RoomRepository;
use MYVH\Tests\Unit\UnitTestCase;

class CalendarAjaxControllerTest extends UnitTestCase {
    private $calendar_service;
    private $booking_service;
    private $room_repository;
    private $customer_service;
    private $client_admin_service;
    private $room_rate_service;
    private CalendarAjaxController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->calendar_service = $this->mock(CalendarService::class);
        $this->booking_service = $this->mock(BookingService::class);
        $this->room_repository = $this->mock(RoomRepository::class);
        $this->customer_service = $this->mock(CustomerService::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);
        $this->room_rate_service = $this->mock(RoomRateService::class);

        $this->controller = new CalendarAjaxController(
            $this->calendar_service,
            $this->booking_service,
            $this->room_repository,
            $this->customer_service,
            $this->client_admin_service,
            $this->room_rate_service
        );

        Functions\stubs([
            'check_ajax_referer' => true,
            'current_user_can' => true,
            'is_user_logged_in' => true,
            'absint' => fn($value) => abs((int) $value),
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