<?php

namespace MYVH\Tests\Unit\Portal;

use Brain\Monkey\Functions;
use MYVH\Bookings\BookingService;
use MYVH\Calendar\CalendarService;
use MYVH\Portal\Actions\DeleteBookingAction;
use MYVH\Portal\Actions\GetBookingAction;
use MYVH\Portal\Actions\UpdateBookingAction;
use MYVH\Portal\Ajax\PortalBookingAjaxController;
use MYVH\Portal\ClientAdminService;
use MYVH\Tests\Unit\UnitTestCase;

class PortalBookingAjaxControllerTest extends UnitTestCase {
    private $get_action;
    private $update_action;
    private $delete_action;
    private $calendar_service;
    private $booking_service;
    private $client_admin_service;
    private PortalBookingAjaxController $controller;

    protected function setUp(): void {
        parent::setUp();

        $this->get_action = $this->mock(GetBookingAction::class);
        $this->update_action = $this->mock(UpdateBookingAction::class);
        $this->delete_action = $this->mock(DeleteBookingAction::class);
        $this->calendar_service = $this->mock(CalendarService::class);
        $this->booking_service = $this->mock(BookingService::class);
        $this->client_admin_service = $this->mock(ClientAdminService::class);

        $this->controller = new PortalBookingAjaxController(
            $this->get_action,
            $this->update_action,
            $this->delete_action,
            $this->calendar_service,
            $this->booking_service,
            $this->client_admin_service
        );

        Functions\stubs([
            'is_user_logged_in' => true,
            'check_ajax_referer' => true,
            'wp_unslash' => static fn($value) => $value,
            'is_wp_error' => false,
        ]);

        Functions\when('wp_send_json_success')->alias(function ($data = null, $status_code = null) {
            throw new PortalJsonResponseException(true, $data, (int) ($status_code ?? 200));
        });

        Functions\when('wp_send_json_error')->alias(function ($data = null, $status_code = null) {
            throw new PortalJsonResponseException(false, $data, (int) ($status_code ?? 400));
        });
    }

    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    /** @test */
    public function update_for_modal_blocks_when_can_edit_denies_the_booking(): void {
        $_POST = [
            'booking_id' => 88,
            'nonce' => 'example',
        ];

        $this->get_action->shouldReceive('execute')
            ->once()
            ->with(88)
            ->andReturn(['Id' => 88, 'Status' => 'pending']);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => false, 'reason' => 'Invoiced bookings cannot be edited.']);

        $this->calendar_service->shouldReceive('update_event')->never();

        $response = $this->capture_json_response(function (): void {
            $this->controller->update_for_modal();
        });

        $this->assertFalse($response->success);
        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Invoiced bookings cannot be edited.', $response->data);
    }

    /** @test */
    public function update_for_modal_allows_when_can_edit_passes(): void {
        $_POST = [
            'booking_id' => 89,
            'nonce' => 'example',
        ];

        $this->get_action->shouldReceive('execute')
            ->once()
            ->with(89)
            ->andReturn(['Id' => 89, 'Status' => 'pending']);

        $this->booking_service->shouldReceive('can_edit')
            ->once()
            ->andReturn(['can_edit' => true, 'reason' => '']);

        $this->calendar_service->shouldReceive('update_event')
            ->once()
            ->with(\Mockery::on(static function (array $payload): bool {
                return intval($payload['booking_id'] ?? 0) === 89
                    && ($payload['context'] ?? '') === 'portal';
            }))
            ->andReturn(['id' => 89]);

        $response = $this->capture_json_response(function (): void {
            $this->controller->update_for_modal();
        });

        $this->assertTrue($response->success);
        $this->assertSame(['id' => 89], $response->data);
    }

    private function capture_json_response(callable $callback): PortalJsonResponseException {
        try {
            $callback();
        } catch (PortalJsonResponseException $response) {
            return $response;
        }

        $this->fail('Expected JSON response to be sent.');
    }
}

class PortalJsonResponseException extends \RuntimeException {
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