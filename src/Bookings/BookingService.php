<?php

namespace MYVH\Bookings;

use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonRepository;
use MYVH\Addons\AddonService;
use MYVH\Availability\AvailabilityService;
use MYVH\Rooms\RoomRulesService;
use MYVH\Pricing\PricingService;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\RecurringPatternService;
use MYVH\Events\EventDispatcher;
use MYVH\Events\BookingEvents;

use WP_Error;
use Exception;
use DateTime;

if (!defined('ABSPATH')) exit;

class BookingService {
    private const EDIT_SCOPE_THIS_ONLY = 'this_only';
    private const EDIT_SCOPE_ALL_BOOKINGS = 'all_bookings';
    private const EDIT_SCOPE_THIS_AND_FUTURE = 'this_and_future';

    private $room_service;
    private $booking_repo;
    private $booking_charge_repo;
    private $booking_addon_repo;
    private $addon_repo;
    private $addon_service;
    private $validator;
    private $availability;
    private $room_rules;
    private $pricing;
    private $customer_repo;
    private $organisation_repo;
    private $recurring_pattern_service = null;
    private $last_warnings = [];

    public function __construct(
        RoomService $room_service,
        BookingRepository $booking_repo,
        BookingChargeRepository $booking_charge_repo,
        BookingAddonRepository $booking_addon_repo,
        AddonRepository $addon_repo,
        AddonService $addon_service,
        BookingValidator $validator,
        AvailabilityService $availability,
        RoomRulesService $room_rules,
        PricingService $pricing,
        CustomerRepository $customer_repo,
        OrganisationRepository $organisation_repo,
        RecurringPatternService $recurring_pattern_service
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->addon_repo = $addon_repo;
        $this->addon_service = $addon_service;
        $this->validator = $validator;
        $this->availability = $availability;
        $this->room_rules = $room_rules;
        $this->pricing = $pricing;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->recurring_pattern_service = $recurring_pattern_service;
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
                $chargeable_hours = $this->calculate_chargeable_hours(
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
            return $this->update_recurring_booking_with_scope(
                $normalized_data,
                $record,
                $current_record,
                $pattern_id,
                $edit_scope
            );
        }

        return $this->apply_single_booking_update($booking_id, $normalized_data, $record, $current_record);
    }

    private function update_recurring_booking_with_scope($data, $record, $current_record, int $pattern_id, string $edit_scope): int|WP_Error {
        $schedule_change_error = $this->validate_series_safe_changes($data, $current_record);
        if (is_wp_error($schedule_change_error)) {
            return $schedule_change_error;
        }

        $bookings = $this->get_sorted_pattern_bookings($pattern_id);
        if (empty($bookings)) {
            return new WP_Error('not_found', __('Recurring booking series not found', 'my-village-hall'));
        }

        if ($edit_scope === self::EDIT_SCOPE_ALL_BOOKINGS) {
            return $this->apply_scoped_updates_to_bookings($bookings, $data, $record);
        }

        $selected_index = $this->find_booking_index($bookings, intval($data['booking_id']));
        if ($selected_index < 0) {
            return new WP_Error('not_found', __('Booking not found in recurring series', 'my-village-hall'));
        }

        if ($selected_index === 0) {
            return $this->apply_scoped_updates_to_bookings($bookings, $data, $record);
        }

        if (!$this->recurring_pattern_service) {
            return new WP_Error('validation', __('Recurring booking updates are not available', 'my-village-hall'));
        }

        $split_result = $this->recurring_pattern_service->split_pattern_from_booking($pattern_id, intval($data['booking_id']));
        if (is_wp_error($split_result)) {
            return $split_result;
        }

        return $this->apply_scoped_updates_to_bookings(
            $split_result['future_bookings'] ?? [],
            $data,
            $record,
            intval($split_result['new_pattern_id'] ?? 0)
        );
    }

    private function apply_scoped_updates_to_bookings(array $bookings, array $base_data, array $base_record, int $override_pattern_id = 0): int|WP_Error {
        if (empty($bookings)) {
            return new WP_Error('validation', __('No recurring bookings matched the selected scope', 'my-village-hall'));
        }

        foreach ($bookings as $booking) {
            $booking_id = intval($booking['Id'] ?? 0);
            if ($booking_id <= 0) {
                continue;
            }

            $data = $this->build_series_update_data_for_booking($booking, $base_data, $base_record);
            $record = $this->build_series_update_record($base_record, $override_pattern_id);
            $current_record = $booking;

            if ($override_pattern_id > 0) {
                $current_record['RecurringPatternId'] = $override_pattern_id;
            }

            $result = $this->apply_single_booking_update($booking_id, $data, $record, $current_record);
            if (is_wp_error($result)) {
                return $result;
            }
        }

        return intval($base_data['booking_id']);
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
        $normalized['edit_scope'] = $this->normalize_edit_scope($data['edit_scope'] ?? '');

        return $normalized;
    }

    private function build_series_update_data_for_booking(array $booking, array $base_data, array $base_record): array {
        $data = [
            'booking_id' => intval($booking['Id'] ?? 0),
            'customer_id' => intval($booking['CustomerId'] ?? 0),
            'organisation_id' => intval($booking['OrganisationId'] ?? 0),
            'room_id' => intval($booking['RoomId'] ?? 0),
            'start_date' => sanitize_text_field($booking['StartDate'] ?? ''),
            'end_date' => sanitize_text_field($booking['EndDate'] ?? ($booking['StartDate'] ?? '')),
            'start_time' => sanitize_text_field($booking['StartTime'] ?? ''),
            'end_time' => sanitize_text_field($booking['EndTime'] ?? ''),
            'status' => sanitize_text_field((string) ($base_record['Status'] ?? $booking['Status'] ?? '')),
            'description' => sanitize_textarea_field($base_record['Description'] ?? $booking['Description'] ?? ''),
            'public' => intval($base_record['Public'] ?? $booking['Public'] ?? 0),
            'edit_scope' => $this->normalize_edit_scope($base_data['edit_scope'] ?? ''),
        ];

        if (array_key_exists('addons', $base_data)) {
            $data['addons'] = $base_data['addons'];
        }

        return $data;
    }

    private function build_series_update_record(array $base_record, int $override_pattern_id = 0): array {
        $record = [
            'Status' => $base_record['Status'],
            'Description' => $base_record['Description'],
            'Public' => $base_record['Public'],
        ];

        if ($override_pattern_id > 0) {
            $record['RecurringPatternId'] = $override_pattern_id;
        }

        return $record;
    }

    private function validate_series_safe_changes(array $data, array $current_record): bool|WP_Error {
        $sensitive_fields = [
            'customer_id' => 'customer',
            'organisation_id' => 'organisation',
            'room_id' => 'room',
            'start_date' => 'start date',
            'end_date' => 'end date',
            'start_time' => 'start time',
            'end_time' => 'end time',
        ];

        $changed_fields = [];

        foreach ($sensitive_fields as $field => $label) {
            $existing_key = match ($field) {
                'customer_id' => 'CustomerId',
                'organisation_id' => 'OrganisationId',
                'room_id' => 'RoomId',
                'start_date' => 'StartDate',
                'end_date' => 'EndDate',
                'start_time' => 'StartTime',
                'end_time' => 'EndTime',
                default => '',
            };

            $incoming = isset($data[$field]) ? (string) $data[$field] : '';
            $existing = isset($current_record[$existing_key]) ? (string) $current_record[$existing_key] : '';

            if ($incoming !== '' && $incoming !== $existing) {
                $changed_fields[] = $label;
            }
        }

        if (!empty($changed_fields)) {
            return new WP_Error(
                'validation',
                sprintf(
                    __('This recurring update can only change description, status, visibility, and add-ons. Use "This booking only" for %s.', 'my-village-hall'),
                    implode(', ', $changed_fields)
                )
            );
        }

        return true;
    }

    private function get_sorted_pattern_bookings(int $pattern_id): array {
        $bookings = $this->booking_repo->get_by_pattern_id($pattern_id);

        usort($bookings, static function (array $left, array $right): int {
            $left_key = sprintf(
                '%s %s %010d',
                (string) ($left['StartDate'] ?? ''),
                (string) ($left['StartTime'] ?? ''),
                intval($left['Id'] ?? 0)
            );
            $right_key = sprintf(
                '%s %s %010d',
                (string) ($right['StartDate'] ?? ''),
                (string) ($right['StartTime'] ?? ''),
                intval($right['Id'] ?? 0)
            );

            return strcmp($left_key, $right_key);
        });

        return $bookings;
    }

    private function find_booking_index(array $bookings, int $booking_id): int {
        foreach ($bookings as $index => $booking) {
            if (intval($booking['Id'] ?? 0) === $booking_id) {
                return $index;
            }
        }

        return -1;
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

        // Recurring pattern events
        if (!empty($data['is_recurring']) && $this->recurring_pattern_service) {
            EventDispatcher::dispatch(
                BookingEvents::BEFORE_RECURRING,
                [
                    'parent_booking_id' => $booking_id,
                    'data' => $data
                ]
            );
            $pattern_data = [
                'parent_booking_id'   => $booking_id,
                'recurrence_type'     => $data['recurrence_type'] ?? '',
                'recurrence_interval' => $data['recurrence_interval'] ?? 1,
                'recurrence_day'      => $data['recurrence_day'] ?? '',
                'recurrence_week'     => $data['recurrence_week'] ?? '',
                'start_date'          => $data['start_date'] ?? '',
                'is_active'           => 1,
            ];
            // Set end condition
            if (($data['recurrence_end_type'] ?? '') === 'date') {
                $pattern_data['end_date'] = $data['recurrence_end_date'] ?? '';
            } else {
                $pattern_data['max_occurrences'] = $data['max_occurrences'] ?? null;
            }
            $pattern_result = $this->recurring_pattern_service->save($pattern_data);
            if (is_wp_error($pattern_result)) {
                return $pattern_result;
            }
            $booking_results = $this->recurring_pattern_service->get_last_booking_results();
            if (is_array($booking_results)) {
                if (!empty($booking_results['conflicts'])) {
                    $this->last_warnings[] = sprintf(
                        __('Some recurring bookings were not created due to conflicts: %s', 'my-village-hall'),
                        implode(', ', $booking_results['conflicts'])
                    );
                }
                if (!empty($booking_results['errors'])) {
                    $this->last_warnings[] = implode(' ', $booking_results['errors']);
                }
            }
            EventDispatcher::dispatch(
                BookingEvents::AFTER_RECURRING,
                [
                    'parent_booking_id' => $booking_id,
                    'data' => $data
                ]
            );
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
        $booking_id = intval($booking_id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Invalid booking id for charge recalculation', 'my-village-hall'));
        }

        $snapshot = $this->pricing->get_charge_snapshot($booking_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        $deleted = $this->booking_charge_repo->delete_by_booking_id($booking_id);
        if ($deleted === false) {
            return new WP_Error('database', __('Failed to clear previous booking charge rows', 'my-village-hall'));
        }

        $created = $this->booking_charge_repo->create($snapshot);
        if ($created === false) {
            return new WP_Error('database', __('Failed to save booking charge snapshot', 'my-village-hall'));
        }

        return true;
    }

    private function resolve_public_visibility($data, $organisation_id, $booking_id): int {
        if (array_key_exists('public', $data)) {
            return !empty($data['public']) ? 1 : 0;
        }

        if ($booking_id > 0) {
            $existing = $this->booking_repo->get_by_id($booking_id);
            if (is_array($existing) && array_key_exists('Public', $existing)) {
                return !empty($existing['Public']) ? 1 : 0;
            }
        }

        if ($organisation_id > 0) {
            $organisation = $this->organisation_repo->get_by_id($organisation_id);
            if (is_array($organisation) && array_key_exists('DefaultPublic', $organisation)) {
                return !empty($organisation['DefaultPublic']) ? 1 : 0;
            }
        }

        return 0;
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
        $booking_id = intval($id);
        if ($booking_id <= 0) {
            return new WP_Error('validation', __('Booking ID is required', 'my-village-hall'));
        }

        $this->booking_repo->begin();
        try {
            EventDispatcher::dispatch(
                BookingEvents::BEFORE_DELETE,
                ['booking_id' => $booking_id]
            );

            if ($this->booking_addon_repo && !$this->booking_addon_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking addons', 'my-village-hall'));
            }

            if ($this->booking_charge_repo && !$this->booking_charge_repo->delete_by_booking_id($booking_id)) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to remove booking charges', 'my-village-hall'));
            }

            $deleted = $this->booking_repo->delete($booking_id);
            if (!$deleted) {
                $this->booking_repo->rollback();
                return new WP_Error('database', __('Failed to delete booking', 'my-village-hall'));
            }

            EventDispatcher::dispatch(
                BookingEvents::DELETED,
                ['booking_id' => $booking_id]
            );
            EventDispatcher::dispatch(
                BookingEvents::AFTER_DELETE,
                ['booking_id' => $booking_id]
            );

            $this->booking_repo->commit();
            return true;
        } catch (Exception $e) {
            $this->booking_repo->rollback();
            return new WP_Error('transaction', __('A database error occurred: ', 'my-village-hall') . $e->getMessage());
        }
    }

    public function get_all_with_details($args = []): array {
        return $this->booking_repo->get_all_with_details($args);
    }

    public function get_by_id_with_details(int $booking_id): ?array {
        if ($booking_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_by_id_with_details_for_customer(int $booking_id, int $customer_id): ?array {
        if ($booking_id <= 0 || $customer_id <= 0) {
            return null;
        }

        $results = $this->booking_repo->get_all_with_details([
            'booking_id' => $booking_id,
            'customer_id' => $customer_id,
        ]);

        return $results[0] ?? null;
    }

    public function get_booking_list($filters = []): array {
        $defaults = [
            'status'      => '',
            'room_id'     => 0,
            'customer_id' => 0,
        ];

        $filters = wp_parse_args($filters, $defaults);

        $query_args = [
            'orderby'     => 'b.StartDate',
            'order'       => 'DESC',
            'status'      => $filters['status'],
            'room_id'     => $filters['room_id'],
            'customer_id' => $filters['customer_id'],
        ];

        $bookings = $this->booking_repo->get_all_with_details($query_args);

        $groups = $this->group_bookings($bookings);

        return [
            'groups'           => $groups,
            'total'            => count($bookings),
            'recurring_groups' => count(array_filter($groups, fn($g) => $g['type'] === 'recurring')),
        ];
    }

    public function get_between($start, $end, $context = null, $filters = []): array {

        $bookings = $this->booking_repo->get_between($start, $end, $context, $filters);
        return $bookings;
    }

    public function move_booking($id, $start, $end, $room): int|WP_Error {
        $room_id = intval($room);

        [$start_date, $start_time] = $this->split_datetime($start);
        [$end_date, $end_time] = $this->split_datetime($end, $start_date);

        if (!$room_id || !$start_date || !$start_time || !$end_date || !$end_time) {
            return new WP_Error('validation', __('Invalid booking movement data', 'my-village-hall'));
        }

        $room_record = $this->room_service->get($room_id);
        if (!$room_record) {
            return new WP_Error('validation', __('Room is required', 'my-village-hall'));
        }

        if ($start_date !== $end_date && empty($room_record['AllowMultiDayBookings'])) {
            return new WP_Error('validation', __('Multi day bookings are not allowed for this room', 'my-village-hall'));
        }

        $within_opening_hours = $this->availability->booking_within_opening_hours(
            $room_id,
            $start_time,
            $end_time
        );

        if (is_wp_error($within_opening_hours)) {
            return $within_opening_hours;
        }

        if (!$within_opening_hours) {
            return new WP_Error(
                'validation',
                sprintf(
                    __('Booking times must be within the room opening hours of %1$s to %2$s.', 'my-village-hall'),
                    substr((string) $room_record['OpeningTime'], 0, 5),
                    substr((string) $room_record['ClosingTime'], 0, 5)
                )
            );
        }

        $is_available = $this->availability->room_is_available(
            $room_id,
            $start_date,
            $start_time,
            $end_time,
            $end_date,
            intval($id)
        );

        if (!$is_available) {
            return new WP_Error('validation', __('This room is not available for the selected time.', 'my-village-hall'));
        }

        $return =  $this->booking_repo->move_booking($id, $start, $end, $room);

        if ($return <> 1) {
            return new WP_Error('Move booking', __('Something went wrong', 'my-village-hall'));
        }

        return $return;
    }

    public function can_delete($booking): array {
        if (empty($booking) || empty($booking['StartDate']) || empty($booking['StartTime'])) {
            return [
                'can_delete' => false,
                'reason' => 'Booking details are incomplete.',
            ];
        }

        $status = strtolower((string) ($booking['Status'] ?? ''));
        $start_ts = strtotime((string) $booking['StartDate'] . ' ' . (string) $booking['StartTime']);
        $now_ts = current_time('timestamp');
        $min_notice_hours = max(0, intval(myvh_setting('booking.general.min_notice_hours', 24)));
        $min_notice_days = (int) ceil($min_notice_hours / 24);

        if (!$start_ts || $start_ts <= $now_ts) {
            return [
                'can_delete' => false,
                'reason' => 'Past bookings cannot be deleted.',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::PENDING || $status === strtolower(BookingStatus::PENDING)) {
            return [
                'can_delete' => true,
                'reason' => '',
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        if ($status === BookingStatus::CONFIRMED || $status === strtolower(BookingStatus::CONFIRMED)) {
            $threshold_ts = $now_ts + ($min_notice_hours * 3600);

            if ($start_ts > $threshold_ts) {
                return [
                    'can_delete' => true,
                    'reason' => '',
                    'min_notice_hours' => $min_notice_hours,
                    'min_notice_days' => $min_notice_days,
                ];
            }

            return [
                'can_delete' => false,
                'reason' => sprintf(
                    'Confirmed bookings can only be deleted when more than %d day(s) away.',
                    $min_notice_days
                ),
                'min_notice_hours' => $min_notice_hours,
                'min_notice_days' => $min_notice_days,
            ];
        }

        return [
            'can_delete' => false,
            'reason' => 'Only pending or confirmed bookings can be deleted.',
            'min_notice_hours' => $min_notice_hours,
            'min_notice_days' => $min_notice_days,
        ];

    }

    public function can_edit($booking): array {
        //TODO: Implement edit restrictions based on user, status and time thresholds, similar to can_delete()
        return [
            'can_edit' => true,
            'reason' => '',
        ];
    }

    private function split_datetime($value, $default_date = '') {
        $raw = trim((string) $value);

        if ($raw === '') {
            return [sanitize_text_field($default_date), ''];
        }

        $timestamp = strtotime($raw);

        if ($timestamp !== false) {
            return [
                date('Y-m-d', $timestamp),
                date('H:i:s', $timestamp),
            ];
        }

        $parts = preg_split('/[T\s]/', $raw);
        $date = sanitize_text_field($parts[0] ?? $default_date);
        $time = sanitize_text_field($parts[1] ?? '');

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        return [$date, $time];
    }

    public function get_upcoming_bookings($wp_user): array {
        $customer_id = $this->customer_repo->get_customer_id($wp_user);

        return $this->booking_repo->get_upcoming_bookings($customer_id);
    }

    private function group_bookings($bookings): array {
        $groups = [];
        $today = date('Y-m-d');

        foreach ($bookings as $b) {

            if ($b['RecurringPatternId']) {

                $key = 'r_' . $b['RecurringPatternId'];

                if (!isset($groups[$key])) {

                    $groups[$key] = [
                        'type'     => 'recurring',
                        'pattern'  => [
                            'Id'                 => $b['RecurringPatternId'],
                            'RecurrenceType'     => $b['RecurrenceType'],
                            'RecurrenceInterval' => $b['RecurrenceInterval'],
                            'RecurrenceDay'      => $b['RecurrenceDay'],
                            'RecurrenceWeek'     => $b['RecurrenceWeek'],
                            'StartDate'          => $b['PatternStartDate'],
                            'EndDate'            => $b['PatternEndDate'],
                            'IsActive'           => $b['PatternIsActive'],
                        ],
                        'bookings' => [],
                    ];
                }

                $groups[$key]['bookings'][] = $b;

            } else {

                $groups['b_' . $b['Id']] = [
                    'type'     => 'standalone',
                    'bookings' => [$b],
                ];
            }
        }

        // Add computed fields
        foreach ($groups as &$group) {

            if ($group['type'] === 'recurring') {

                $group['next_booking'] = $this->find_next_booking($group['bookings'], $today);
                $group['status']       = $this->determine_group_status($group['bookings']);
                $group['count']        = count($group['bookings']);

            } else {

                $b = $group['bookings'][0];

                $group['next_booking'] = $b;
                $group['status']       = $b['Status'];
                $group['count']        = 1;
            }
        }

        return $groups;
    }

    private function find_next_booking($bookings, $today): ?array {
        foreach (array_reverse($bookings) as $b) {

            if ($b['StartDate'] >= $today && $b['Status'] !== BookingStatus::CANCELLED) {
                return $b;
            }

        }

        return null;
    }

    private function determine_group_status($members): string {
        $statuses = array_column($members, 'Status');

        foreach ($statuses as $status) {
            if ($status === BookingStatus::CONFIRMED) {
                return BookingStatus::CONFIRMED;
            }
        }

        foreach ($statuses as $status) {
            if ($status === BookingStatus::PENDING) {
                return BookingStatus::PENDING;
            }
        }

        return $statuses[0] ?? BookingStatus::COMPLETED;
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

    private function calculate_chargeable_hours($startdate, $start_time, $enddate, $end_time, $room): float {
        $start = new DateTime($startdate . ' ' . $start_time);
        $end   = new DateTime($enddate . ' ' . $end_time);
        $interval = $start->diff($end);

        $hours =
            ($interval->days * 24) +
            $interval->h +
            ($interval->i / 60);

        if (!$room['CalcClosedHours']) {
            $room_open = strtotime($room['OpeningTime']);
            $room_close = strtotime($room['ClosingTime']);

            if ($room_open !== false && $room_close !== false) {
                $open_hours = ($room_close - $room_open) / 3600;

                // Handle schedules that wrap past midnight (e.g. 20:00 to 02:00).
                if ($open_hours <= 0) {
                    $open_hours += 24;
                }

                $closed_hours_per_day = max(0, 24 - $open_hours);

                $start_day = (clone $start)->setTime(0, 0, 0);
                $end_day = (clone $end)->setTime(0, 0, 0);
                $days_crossed = $start_day->diff($end_day)->days;

                $hours -= ($closed_hours_per_day * $days_crossed);
            }
        }

        return max(0, $hours);
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
