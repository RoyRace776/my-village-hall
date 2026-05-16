<?php
namespace MYVH\Portal\Ajax;

use MYVH\Availability\AvailabilityService;
use MYVH\Bookings\BookingService;
use MYVH\Calendar\CalendarService;
use MYVH\Portal\Actions\GetBookingAction;
use MYVH\Portal\Actions\QuoteBookingAction;
use MYVH\Portal\Actions\UpdateBookingAction;
use MYVH\Portal\Actions\DeleteBookingAction;
use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Portal\Support\PortalAuth;

use Exception;

class PortalBookingAjaxController {
    public function __construct(
        private GetBookingAction $get_action,
        private QuoteBookingAction $quote_action,
        private UpdateBookingAction $update_action,
        private DeleteBookingAction $delete_action,
        private CalendarService $calendar_service,
        private BookingService $booking_service,
        private ClientAdminService $client_admin_service,
        private AvailabilityService $availability_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_portal_get_booking', [$this, 'get']);
        add_action('wp_ajax_myvh_portal_quote_booking', [$this, 'quote_for_modal']);
        add_action('wp_ajax_myvh_portal_update_booking', [$this, 'update']);
        add_action('wp_ajax_myvh_portal_delete_booking', [$this, 'delete']);
        add_action('wp_ajax_myvh_portal_create_booking', [$this, 'create_for_modal']);
        add_action('wp_ajax_myvh_portal_update_booking_modal', [$this, 'update_for_modal']);
        add_action('wp_ajax_myvh_portal_next_booking_slot', [$this, 'next_slot']);
    }

    public function get(): void {
        PortalAuth::require_user();

        $booking_id = 0;
        $booking = [];
        $edit_rules = [];
        $delete_rules = [];

        try {
            $booking_id = \intval($_POST['booking_id'] ?? $_GET['booking_id'] ?? 0);
            $booking = $this->get_action->execute($booking_id);
            $edit_rules = $this->booking_service->can_edit($booking);
            $delete_rules = $this->booking_service->can_delete($booking);
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
            return;
        }

        AjaxResponse::success([
            'booking' => $booking,
            'charges' => $this->booking_service->get_charges_for_booking($booking_id),
            'addons' => $this->booking_service->get_addons_for_booking($booking_id),
            'deposits' => $this->booking_service->get_deposit_items_for_booking($booking_id),
            'expected_deposit' => $this->booking_service->get_expected_deposit_for_booking($booking_id),
            'can_edit' => !empty($edit_rules['can_edit']),
            'edit_reason' => $edit_rules['reason'] ?? '',
            'can_delete' => !empty($delete_rules['can_delete']),
            'delete_reason' => $delete_rules['reason'] ?? '',
            'can_manage_no_invoice_required' => $this->current_user_can_manage_no_invoice_required(),
        ]);
    }

    public function update(): void {
        PortalAuth::require_user();

        try {
            $this->update_action->execute($_POST);
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
        }

        AjaxResponse::success([], __('Booking updated', 'my-village-hall'));
    }

    public function delete(): void {
        PortalAuth::require_user();

        try {
            $booking_id = \intval($_POST['booking_id'] ?? 0);
            $this->delete_action->execute($booking_id);
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
        }

        AjaxResponse::success([], __('Booking deleted', 'my-village-hall'));
    }

    public function create_for_modal(): void {
        PortalAuth::require_user();

        $request = wp_unslash($_POST);
        $result = [];

        try {
            $result = $this->calendar_service->create_event($request, 'portal', get_current_user_id());

            if (is_wp_error($result)) {
                AjaxResponse::error($result->get_error_message());
                return;
            }
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
            return;
        }

        AjaxResponse::success($result);
    }

    public function quote_for_modal(): void {
        PortalAuth::require_user();

        $request = wp_unslash($_POST);
        $result = [];

        try {
            $result = $this->quote_action->execute($request);

            if (is_wp_error($result)) {
                AjaxResponse::error($result->get_error_message());
                return;
            }
        } catch (Exception $e) {
            AjaxResponse::error($e->getMessage());
            return;
        }

        AjaxResponse::success($result);
    }

    public function update_for_modal(): void {
        PortalAuth::require_user();

        $request = wp_unslash($_POST);
        $request['context'] = 'portal';
        $booking_id = \intval($request['booking_id'] ?? $request['id'] ?? 0);
        $result = [];

        if ($booking_id <= 0) {
            AjaxResponse::error(__('Booking ID is required', 'my-village-hall'));
            return;
        }

        try {
            $booking = $this->get_action->execute($booking_id);
            $edit_rules = $this->booking_service->can_edit($booking);

            if (empty($edit_rules['can_edit'])) {
                throw new Exception($edit_rules['reason'] ?? __('Permission denied', 'my-village-hall'), 403);
            }

            $result = $this->calendar_service->update_event($request);

            if (is_wp_error($result)) {
                throw new Exception($result->get_error_message(), 400);
            }
        } catch (Exception $e) {
            $status = $e->getCode();
            AjaxResponse::error($e->getMessage(), $status >= 400 ? $status : 400);
            return;
        }

        AjaxResponse::success($result);
    }

    public function next_slot(): void {
        PortalAuth::require_user();

        $request = wp_unslash(array_merge($_GET, $_POST));

        $room_id = \intval($request['room_id'] ?? 0);
        $date = sanitize_text_field($request['date'] ?? wp_date('Y-m-d'));
        $length_minutes = \intval($request['length_minutes'] ?? 60);

        if ($room_id <= 0) {
            AjaxResponse::error(__('Room is required', 'my-village-hall'));
            return;
        }

        $result = $this->availability_service->next_available_slot($room_id, $date, $length_minutes);

        if (is_wp_error($result)) {
            AjaxResponse::error($result->get_error_message());
            return;
        }

        AjaxResponse::success($result);
    }

    private function current_user_can_manage_no_invoice_required(): bool {
        if (current_user_can('manage_myvh')) {
            return true;
        }

        return $this->client_admin_service->can_administer_blog(get_current_user_id(), get_current_blog_id());
    }
}