<?php

namespace MYVH\Bookings;

use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonService;
use MYVH\Bookings\Services\BookingAccessControl;
use MYVH\Bookings\Services\BookingChargeService;
use MYVH\Bookings\Services\BookingChargeableHoursCalculator;
use MYVH\Bookings\Services\BookingDeletionService;
use MYVH\Bookings\Services\BookingMovementService;
use MYVH\Bookings\Services\BookingQueryService;
use MYVH\Bookings\Services\RecurringBookingCreator;
use MYVH\Bookings\Services\RecurringBookingUpdater;
use MYVH\Events\EventDispatcher;
use MYVH\Events\BookingEvents;

use WP_Error;
use Exception;

if (!defined('ABSPATH')) exit;

class BookingService {
    private const EDIT_SCOPE_THIS_ONLY = 'this_only';
    private const EDIT_SCOPE_ALL_BOOKINGS = 'all_bookings';
    private const EDIT_SCOPE_THIS_AND_FUTURE = 'this_and_future';

    private $room_service;
    private $booking_repo;
    private $booking_addon_repo;
    private $addon_service;
    private $validator;
    private $booking_charge_service;
    private $booking_chargeable_hours_calculator;
    private $booking_deletion_service;
    private $booking_access_control;
    private $booking_movement_service;
    private $booking_query_service;
    private $recurring_booking_creator;
    private $recurring_booking_updater;
    private $last_warnings = [];

    public function __construct(
        RoomService $room_service,
        BookingRepository $booking_repo,
        BookingAddonRepository $booking_addon_repo,
        AddonService $addon_service,
        BookingValidator $validator,
        BookingChargeService $booking_charge_service,
        BookingChargeableHoursCalculator $booking_chargeable_hours_calculator,
        BookingDeletionService $booking_deletion_service,
        BookingAccessControl $booking_access_control,
        BookingMovementService $booking_movement_service,
        BookingQueryService $booking_query_service,
        RecurringBookingCreator $recurring_booking_creator,
        RecurringBookingUpdater $recurring_booking_updater
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->addon_service = $addon_service;
        $this->validator = $validator;
        $this->booking_charge_service = $booking_charge_service;
        $this->booking_chargeable_hours_calculator = $booking_chargeable_hours_calculator;
        $this->booking_deletion_service = $booking_deletion_service;
        $this->booking_access_control = $booking_access_control;
        $this->booking_movement_service = $booking_movement_service;
        $this->booking_query_service = $booking_query_service;
        $this->recurring_booking_creator = $recurring_booking_creator;
        $this->recurring_booking_updater = $recurring_booking_updater;
    }

    public function save($data): int|WP_Error {
        $this->last_warnings = [];
        $this->booking_repo->begin();
        try {
            if (!empty($data['booking_id'])) {
                EventDispatcher::dispatch(
                    BookingEvents::BEFORE_UPDATE,
                    $data
                );
            } else {
                EventDispatcher::dispatch(
                BookingEvents::BEFORE_CREATE,
                    $data
                );
            }

            $validation_result = $this->validator->validate($data);
            if (is_wp_error($validation_result)) {
                $this->booking_repo->rollback();
                return $validation_result;
            }

            $customer_id = intval($data['customer_id']);
            $organisation_id = !empty($data['organisation_id']) ? intval($data['organisation_id']) : 0;

            $room = $this->room_service->get($data['room_id']);
            $start_date = sanitize_text_field($data['start_date']);
            $end_date = !empty($data['end_date']) ? sanitize_text_field($data['end_date']) : $start_date;
            $start_time = sanitize_text_field($data['start_time']);
            $end_time = sanitize_text_field($data['end_time']);

            if (!isset($data['chargeable_hours'])) {
                $chargeable_hours = $this->booking_chargeable_hours_calculator->calculate(
                    $start_date,
                    $start_time,
                    $end_date,
                    $end_time,
                    $room
                );
            } else {
                $chargeable_hours = $data['chargeable_hours'];
            }

            $booking_id = !empty($data['booking_id']) ? intval($data['booking_id']) : 0;
            $public_visibility = $this->resolve_public_visibility($data, $organisation_id, $booking_id);
            $no_invoice_required = intval($data['no_invoice_required'] ?? 0);

            $record = [
                'CustomerId'        => $customer_id,
                'OrganisationId'    => $organisation_id > 0 ? $organisation_id : null,
                'RoomId'            => intval($data['room_id']),
                'Status'            => sanitize_text_field($data['status'] ?? (myvh_setting('booking.require_approval', true) ? BookingStatus::PENDING : BookingStatus::CONFIRMED)),
                'StartDate'         => $start_date,
                'EndDate'           => $end_date,
                'StartTime'         => $start_time,
                'EndTime'           => $end_time,
                'Description'       => sanitize_textarea_field($data['description'] ?? ''),
                'Public'            => $public_visibility,
                'NoInvoiceRequired' => $no_invoice_required,
                'ChargeableHours'   => $chargeable_hours
            ];

            if (!empty($data['booking_id'])) {
                $result = $this->update_booking($data, $record);
            } else {
                $result = $this->create_booking($data, $record);
            }

            if (is_wp_error($result)) {
                $this->booking_repo->rollback();
                return $result;
            }

            $this->booking_repo->commit();
            return $result;
        } catch (Exception $e) {
            $this->booking_repo->rollback();
            return new WP_Error('transaction', __('A database error occurred: ', 'my-village-hall') . $e->getMessage());
        }
    }

    private function update_booking($data, $record): int|WP_Error {
        $booking_id = intval($data['booking_id']);
        $current_record = $this->booking_repo->get_by_id($booking_id);

        if (!$current_record) {
            return new WP_Error('not_found', __('Booking not found', 'my-village-hall'));
        }

        $normalized_data = $this->build_single_update_data($data, $record, $current_record);
        $edit_scope = $this->normalize_edit_scope($normalized_data['edit_scope'] ?? '');
        $pattern_id = intval($current_record['RecurringPatternId'] ?? 0);

        if ($pattern_id > 0 && $edit_scope !== self::EDIT_SCOPE_THIS_ONLY) {
            return $this->recurring_booking_updater->update_with_scope(
                $normalized_data,
                $record,
                $current_record,
                $pattern_id,
                $edit_scope,
                function (int $booking_id, array $scoped_data, array $scoped_record, array $scoped_current_record): int|WP_Error {
                    return $this->apply_single_booking_update($booking_id, $scoped_data, $scoped_record, $scoped_current_record);
                }
            );
        }

        return $this->apply_single_booking_update($booking_id, $normalized_data, $record, $current_record);
    }

    private function apply_single_booking_update(int $booking_id, array $data, array $record, array $current_record): int|WP_Error {
        $new_status = sanitize_text_field((string) ($record['Status'] ?? $data['status'] ?? ''));

        if (isset($current_record['Status']) && $current_record['Status'] !== $new_status) {
            EventDispatcher::dispatch(
                BookingEvents::BEFORE_STATUS_CHANGE,
                [
                    'booking_id' => $booking_id,
                    'old_status' => $current_record['Status'],
                    'new_status' => $new_status,
                    'data' => $data,
                ]
            );
        }

        $result = $this->booking_repo->update($record, ['Id' => $booking_id]);
        if ($result === false) {
            return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
        }

        if (array_key_exists('addons', $data)) {
            EventDispatcher::dispatch(
                BookingEvents::BEFORE_ADDONS_CHANGE,
                [
                    'booking_id' => $booking_id,
                    'addons' => $data['addons'] ?? [],
                    'replace' => true,
                ]
            );
            $this->save_addons($booking_id, $data['addons'] ?? [], true);
            EventDispatcher::dispatch(
                BookingEvents::AFTER_ADDONS_CHANGE,
                [
                    'booking_id' => $booking_id,
                    'addons' => $data['addons'] ?? [],
                    'replace' => true,
                ]
            );
        }

        $charge_result = $this->recalculate_booking_charges($booking_id);
        if (is_wp_error($charge_result)) {
            return $charge_result;
        }

        $this->dispatch_update_event($data, $current_record['Status'] ?? null);

        if (isset($current_record['Status']) && $current_record['Status'] !== $new_status) {
            EventDispatcher::dispatch(
                BookingEvents::AFTER_STATUS_CHANGE,
                [
                    'booking_id' => $booking_id,
                    'old_status' => $current_record['Status'],
                    'new_status' => $new_status,
                    'data' => $data,
                ]
            );
        }

        EventDispatcher::dispatch(
            BookingEvents::AFTER_UPDATE,
            $data
        );

        return $booking_id;
    }

    private function build_single_update_data(array $data, array $record, array $current_record): array {
        $normalized = $data;
        $normalized['booking_id'] = intval($data['booking_id'] ?? $current_record['Id'] ?? 0);
        $normalized['customer_id'] = intval($data['customer_id'] ?? $current_record['CustomerId'] ?? 0);
        $normalized['organisation_id'] = intval($data['organisation_id'] ?? $current_record['OrganisationId'] ?? 0);
        $normalized['room_id'] = intval($data['room_id'] ?? $current_record['RoomId'] ?? 0);
        $normalized['start_date'] = sanitize_text_field($data['start_date'] ?? $current_record['StartDate'] ?? '');
        $normalized['end_date'] = sanitize_text_field($data['end_date'] ?? $current_record['EndDate'] ?? $normalized['start_date']);
        $normalized['start_time'] = sanitize_text_field($data['start_time'] ?? $current_record['StartTime'] ?? '');
        $normalized['end_time'] = sanitize_text_field($data['end_time'] ?? $current_record['EndTime'] ?? '');
        $normalized['status'] = sanitize_text_field((string) ($record['Status'] ?? $data['status'] ?? $current_record['Status'] ?? ''));
        $normalized['description'] = sanitize_textarea_field($data['description'] ?? $record['Description'] ?? $current_record['Description'] ?? '');
        $normalized['public'] = intval($record['Public'] ?? $data['public'] ?? $current_record['Public'] ?? 0);
        $normalized['no_invoice_required'] = intval($record['NoInvoiceRequired'] ?? $data['no_invoice_required'] ?? $current_record['NoInvoiceRequired'] ?? 0);
        $normalized['edit_scope'] = $this->normalize_edit_scope($data['edit_scope'] ?? '');

        return $normalized;
    }

    private function normalize_edit_scope($scope): string {
        $value = sanitize_text_field((string) $scope);

        if ($value === self::EDIT_SCOPE_ALL_BOOKINGS || $value === self::EDIT_SCOPE_THIS_AND_FUTURE) {
            return $value;
        }

        return self::EDIT_SCOPE_THIS_ONLY;
    }

    private function create_booking($data, $record): int|WP_Error {
        // Before create event already dispatched in save()
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        // Addons before/after events
        EventDispatcher::dispatch(
            BookingEvents::BEFORE_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $data['addons'] ?? [],
                'replace' => false
            ]
        );
        $this->save_addons($booking_id, $data['addons'] ?? []);
        EventDispatcher::dispatch(
            BookingEvents::AFTER_ADDONS_CHANGE,
            [
                'booking_id' => $booking_id,
                'addons' => $data['addons'] ?? [],
                'replace' => false
            ]
        );

        $charge_result = $this->recalculate_booking_charges($booking_id);
        if (is_wp_error($charge_result)) {
            $this->booking_addon_repo->delete_by_booking_id($booking_id);
            $this->booking_repo->delete($booking_id);
            return $charge_result;
        }

        EventDispatcher::dispatch(
            BookingEvents::CREATED,
            [
                'booking_id' => $booking_id,
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

        if ($data['status'] == BookingStatus::CONFIRMED) {
            EventDispatcher::dispatch(
                BookingEvents::CONFIRMED,
                [
                    'booking_id' => $booking_id,
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        $recurring_result = $this->recurring_booking_creator->create_for_booking($booking_id, $data);
        if (is_wp_error($recurring_result)) {
            return $recurring_result;
        }

        if (!empty($recurring_result)) {
            $this->last_warnings = array_merge($this->last_warnings, $recurring_result);
        }

        EventDispatcher::dispatch(
            BookingEvents::AFTER_CREATE,
            $data + ['booking_id' => $booking_id]
        );

        return $booking_id;
    }

    /**
     * Recalculate and persist booking charge snapshot rows.
     *
     * TODO: Decide all lifecycle events that should trigger automatic
     * recalculation (status transitions, room/time edits, addon changes, etc.).
     *
     * @param int $booking_id
     * @return true|WP_Error
     */
    public function recalculate_booking_charges($booking_id): bool|WP_Error {
        return $this->booking_charge_service->recalculate($booking_id);
    }

    private function resolve_public_visibility($data, $organisation_id, $booking_id): int {
        return $this->booking_access_control->resolve_public_visibility($data, $organisation_id, $booking_id);
    }

    public function get_last_warnings(): array {
        return $this->last_warnings;
    }

    /**
     * Save addons for a booking. Optionally delete existing ones first (for updates).
     *
     * @param int   $booking_id
     * @param array $addons     Array of ['addon_id' => int, 'quantity' => float, 'unit_price' => float, 'description' => string]
     * @param bool  $replace    If true, delete all existing addons before saving
     */

    public function save_addons($booking_id, $addons, $replace = false): void {
        // Delegate to AddonService (implement a method if needed)
        if (!$this->addon_service) return;
        $this->addon_service->save_booking_addons($booking_id, $addons, $replace);
    }

    /**
     * Get all addons for a booking (with addon name and type).
     *
     * @param int $booking_id
     * @return array
     */
    public function get_addons_for_booking($booking_id): array {
        if (!$this->addon_service) return [];
        return $this->addon_service->get_addons_for_booking($booking_id);
    }

    public function get_by_id($booking_id): ?array {
        return $this->booking_repo->get_by_id($booking_id);
    }

    public function cancel($id): int|false {
        return $this->update_status($id, BookingStatus::CANCELLED);
    }

    public function update_status($id, $status): int|false {
        return $this->booking_repo->update(
            ['Status' => $status],
            ['Id' => $id]
        );

        //TODO: Dispatch status change events here or in the controller, and consider if old vs new status comparison is needed for BEFORE/AFTER status change events
    }

    public function delete($id): bool|WP_Error {
        return $this->booking_deletion_service->delete($id);
    }

    public function get_all_with_details($args = []): array {
        return $this->booking_query_service->get_all_with_details($args);
    }

    public function get_by_id_with_details(int $booking_id): ?array {
        return $this->booking_query_service->get_by_id_with_details($booking_id);
    }

    public function get_by_id_with_details_for_customer(int $booking_id, int $customer_id): ?array {
        return $this->booking_query_service->get_by_id_with_details_for_customer($booking_id, $customer_id);
    }

    public function get_booking_list($filters = []): array {
        return $this->booking_query_service->get_booking_list($filters);
    }

    public function get_between($start, $end, $context = null, $filters = []): array {
        return $this->booking_query_service->get_between($start, $end, $context, $filters);
    }

    public function move_booking($id, $start, $end, $room): int|WP_Error {
        return $this->booking_movement_service->move_booking($id, $start, $end, $room);
    }

    public function can_delete($booking): array {
        return $this->booking_access_control->can_delete($booking);
    }

    public function can_edit($booking): array {
        return $this->booking_access_control->can_edit($booking);
    }

    public function get_upcoming_bookings($wp_user): array {
        return $this->booking_query_service->get_upcoming_bookings($wp_user);
    }

    private function dispatch_update_event($data, $old_status = null): void {
        // TODO: Update this to handle status changes
        $new_status = $data['status'];
        $current_status = $old_status;

        if ($new_status==BookingStatus::CONFIRMED && $current_status<>BookingStatus::CONFIRMED) {
            EventDispatcher::dispatch(
                BookingEvents::CONFIRMED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        if ($new_status==BookingStatus::CANCELLED && $current_status<>BookingStatus::CANCELLED) {
            EventDispatcher::dispatch(
                BookingEvents::CANCELLED,
                [
                    'booking_id' => $data['booking_id'],
                    'room_id' => $data['room_id'],
                    'start' => $data['start_time'],
                    'end' => $data['end_time']
                ]
            );
        }

        EventDispatcher::dispatch(
            BookingEvents::UPDATED,
            [
                'booking_id' => $data['booking_id'],
                'room_id' => $data['room_id'],
                'start' => $data['start_time'],
                'end' => $data['end_time']
            ]
        );

    }

    /**
     * Get uninvoiced bookings for reporting
     *
     * @param array $args Optional filters (orderby, order, limit, offset, organisation_id, customer_id)
     * @return array Array of uninvoiced bookings with details
     */
    public function get_uninvoiced_bookings($args = []): array {
        return $this->booking_repo->get_uninvoiced_bookings($args);
    }

    /**
     * Get uninvoiced booking counts by organisation
     *
     * @return array Array of [OrganisationId, OrganisationName, UninvoicedCount]
     */
    public function get_uninvoiced_by_organisation(): array {
        return $this->booking_repo->count_uninvoiced_by_organisation();
    }

    /**
     * Get uninvoiced booking counts by customer
     *
     * @return array Array of [CustomerId, CustomerName, CustomerEmail, UninvoicedCount]
     */
    public function get_uninvoiced_by_customer(): array {
        return $this->booking_repo->count_uninvoiced_by_customer();
    }

    public function booking_requires_no_invoice(int $booking_id): bool {
        return $this->booking_repo->is_no_invoice_required($booking_id);
    }

    /**
     * Check if a booking has been invoiced (mutual exclusivity check)
     *
     * @param int $booking_id The booking ID to check
     * @return bool True if booking has any non-cancelled invoices
     */
    public function booking_has_invoices($booking_id): bool {
        return $this->booking_repo->has_invoiced_items($booking_id);
    }

}
