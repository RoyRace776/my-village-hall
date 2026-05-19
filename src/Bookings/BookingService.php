<?php

namespace MYVH\Bookings;

use MYVH\Rooms\RoomService;
use MYVH\Addons\AddonService;
use MYVH\Bookings\Services\BookingAccessControl;
use MYVH\Bookings\Services\BookingAddonSyncService;
use MYVH\Bookings\Services\BookingChargeService;
use MYVH\Bookings\Services\BookingChargeableHoursCalculator;
use MYVH\Bookings\Services\BookingCreationEventDispatcher;
use MYVH\Bookings\Services\BookingDeletionService;
use MYVH\Bookings\Services\BookingLifecycleEventDispatcher;
use MYVH\Bookings\Services\BookingMovementService;
use MYVH\Bookings\Services\BookingQueryService;
use MYVH\Bookings\Services\BookingStatusTransitionDispatcher;
use MYVH\Bookings\Services\BookingUpdateEventDispatcher;
use MYVH\Bookings\Services\RecurringBookingCreator;
use MYVH\Bookings\Services\RecurringBookingUpdater;
use MYVH\Deposits\DepositService;
use MYVH\Events\EventDispatcher;
use MYVH\Events\BookingEvents;
use MYVH\Invoices\InvoiceItemRepository;
use MYVH\Invoices\InvoiceService;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

use WP_Error;
use Exception;

if (!defined('ABSPATH')) exit;

class BookingService {
    private const EDIT_SCOPE_THIS_ONLY = 'this_only';
    private const EDIT_SCOPE_ALL_BOOKINGS = 'all_bookings';
    private const EDIT_SCOPE_THIS_AND_FUTURE = 'this_and_future';

    private RoomService $room_service;
    private BookingRepository $booking_repo;
    private BookingAddonRepository $booking_addon_repo;
    private AddonService $addon_service;
    private BookingValidator $validator;
    private BookingAddonSyncService $booking_addon_sync_service;
    private BookingChargeService $booking_charge_service;
    private BookingChargeableHoursCalculator $booking_chargeable_hours_calculator;
    private BookingCreationEventDispatcher $booking_creation_event_dispatcher;
    private BookingDeletionService $booking_deletion_service;
    private BookingLifecycleEventDispatcher $booking_lifecycle_event_dispatcher;
    private BookingAccessControl $booking_access_control;
    private BookingMovementService $booking_movement_service;
    private BookingQueryService $booking_query_service;
    private BookingStatusTransitionDispatcher $booking_status_transition_dispatcher;
    private BookingUpdateEventDispatcher $booking_update_event_dispatcher;
    private RecurringPatternService $recurring_pattern_service;
    private RecurringBookingCreator $recurring_booking_creator;
    private RecurringBookingUpdater $recurring_booking_updater;
    private InvoiceService $invoice_service;
    private InvoiceItemRepository $invoice_item_repo;
    private BookingChargeRepository $booking_charge_repo;
    private DepositService $deposit_service;
    private LoggerInterface $logger;
    private array $last_warnings = [];

    public function __construct(
        RoomService $room_service,
        BookingRepository $booking_repo,
        BookingAddonRepository $booking_addon_repo,
        AddonService $addon_service,
        BookingValidator $validator,
        BookingAddonSyncService $booking_addon_sync_service,
        BookingChargeService $booking_charge_service,
        BookingChargeableHoursCalculator $booking_chargeable_hours_calculator,
        BookingCreationEventDispatcher $booking_creation_event_dispatcher,
        BookingDeletionService $booking_deletion_service,
        BookingLifecycleEventDispatcher $booking_lifecycle_event_dispatcher,
        BookingAccessControl $booking_access_control,
        BookingMovementService $booking_movement_service,
        BookingQueryService $booking_query_service,
        BookingStatusTransitionDispatcher $booking_status_transition_dispatcher,
        BookingUpdateEventDispatcher $booking_update_event_dispatcher,
        RecurringPatternService $recurring_pattern_service,
        RecurringBookingCreator $recurring_booking_creator,
        RecurringBookingUpdater $recurring_booking_updater,
        InvoiceService $invoice_service,
        InvoiceItemRepository $invoice_item_repo,
        BookingChargeRepository $booking_charge_repo,
        DepositService $deposit_service,
        ?LoggerInterface $logger = null
    ) {
        $this->room_service = $room_service;
        $this->booking_repo = $booking_repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->addon_service = $addon_service;
        $this->validator = $validator;
        $this->booking_addon_sync_service = $booking_addon_sync_service;
        $this->booking_charge_service = $booking_charge_service;
        $this->booking_chargeable_hours_calculator = $booking_chargeable_hours_calculator;
        $this->booking_creation_event_dispatcher = $booking_creation_event_dispatcher;
        $this->booking_deletion_service = $booking_deletion_service;
        $this->booking_lifecycle_event_dispatcher = $booking_lifecycle_event_dispatcher;
        $this->booking_access_control = $booking_access_control;
        $this->booking_movement_service = $booking_movement_service;
        $this->booking_query_service = $booking_query_service;
        $this->booking_status_transition_dispatcher = $booking_status_transition_dispatcher;
        $this->booking_update_event_dispatcher = $booking_update_event_dispatcher;
        $this->recurring_pattern_service = $recurring_pattern_service;
        $this->recurring_booking_creator = $recurring_booking_creator;
        $this->recurring_booking_updater = $recurring_booking_updater;
        $this->invoice_service = $invoice_service;
        $this->invoice_item_repo = $invoice_item_repo;
        $this->booking_charge_repo = $booking_charge_repo;
        $this->deposit_service = $deposit_service;
        $this->logger = $logger ?? new NullLogger();
    }

    public function save(array $data): int|WP_Error {
        $this->logger->info('Saving booking with data', ['data' => $data]);
        $this->last_warnings = [];
        $this->booking_repo->begin();
        try {
            $this->booking_lifecycle_event_dispatcher->dispatch_before_save($data);

            $validation_result = $this->validator->validate($data);
            if (is_wp_error($validation_result)) {
                $this->booking_repo->rollback();
                return $validation_result;
            }

            $customer_id = \intval($data['customer_id']);
            $organisation_id = !empty($data['organisation_id']) ? \intval($data['organisation_id']) : 0;

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

            $booking_id = !empty($data['booking_id']) ? \intval($data['booking_id']) : 0;
            $public_visibility = $this->resolve_public_visibility($data, $organisation_id, $booking_id);
            $no_invoice_required = \intval($data['no_invoice_required'] ?? 0);

            $record = [
                'CustomerId'        => $customer_id,
                'OrganisationId'    => $organisation_id > 0 ? $organisation_id : null,
                'RoomId'            => \intval($data['room_id']),
                'Status'            => sanitize_text_field($data['status'] ?? (myvh_setting('booking.require_approval', true) ? BookingStatus::PENDING->value : BookingStatus::CONFIRMED->value)),
                'StartDate'         => $start_date,
                'EndDate'           => $end_date,
                'StartTime'         => $start_time,
                'EndTime'           => $end_time,
                'Description'       => sanitize_textarea_field($data['description'] ?? ''),
                'Public'            => $public_visibility,
                'NoInvoiceRequired' => $no_invoice_required,
                'ChargeableHours'   => $chargeable_hours
            ];

            if (empty($data['booking_id'])) {
                $deposit = $this->evaluate_deposit_for_record($record);
                if ($deposit !== null && ($deposit['action'] ?? '') === 'require_review') {
                    $record['Status'] = BookingStatus::PENDING->value;
                }
            }

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

    private function update_booking( mixed $data, mixed $record): int|WP_Error {
        $booking_id = \intval($data['booking_id']);
        $current_record = $this->booking_repo->get_by_id($booking_id);

        if (!$current_record) {
            $this->logger->warning('Booking not found', ['booking_id' => $booking_id]);
            return new WP_Error('not_found', __('Booking not found', 'my-village-hall'));
        }

        $normalized_data = $this->build_single_update_data($data, $record, $current_record);
        $edit_scope = $this->normalize_edit_scope($normalized_data['edit_scope'] ?? '');
        $pattern_id = \intval($current_record['RecurringPatternId'] ?? 0);

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
        $edit_scope = $this->normalize_edit_scope($data['edit_scope'] ?? '');
        $pattern_id = \intval($current_record['RecurringPatternId'] ?? 0);

        if ($pattern_id > 0 && $edit_scope === self::EDIT_SCOPE_THIS_ONLY) {
            $detach_result = $this->detach_booking_from_pattern($booking_id, $pattern_id);
            if (is_wp_error($detach_result)) {
                return $detach_result;
            }
        }

        $new_status = sanitize_text_field((string) ($record['Status'] ?? $data['status'] ?? ''));

        $this->booking_status_transition_dispatcher->dispatch_before(
            $booking_id,
            $current_record['Status'] ?? null,
            $new_status,
            $data
        );

        $result = $this->booking_repo->update($record, ['Id' => $booking_id]);
        if ($result === false) {
            return new WP_Error('database', __('Failed to update booking', 'my-village-hall'));
        }

        if (array_key_exists('addons', $data)) {
            $this->booking_addon_sync_service->sync($booking_id, $data['addons'] ?? [], true);
        }

        $charge_result = $this->recalculate_booking_charges($booking_id);
        if (is_wp_error($charge_result)) {
            return $charge_result;
        }

        $this->booking_update_event_dispatcher->dispatch($data, $current_record['Status'] ?? null);

        $this->booking_status_transition_dispatcher->dispatch_after(
            $booking_id,
            $current_record['Status'] ?? null,
            $new_status,
            $data
        );

        $this->booking_lifecycle_event_dispatcher->dispatch_after_update($data);

        return $booking_id;
    }

    private function detach_booking_from_pattern(int $booking_id, int $pattern_id): bool|WP_Error {
        $pattern = $this->recurring_pattern_service->get_by_id($pattern_id);
        if (!$pattern) {
            return new WP_Error('not_found', __('Recurring booking series not found', 'my-village-hall'));
        }

        $detached = $this->booking_repo->update(['RecurringPatternId' => null], ['Id' => $booking_id]);
        if ($detached === false) {
            return new WP_Error('database', __('Failed to detach booking from recurring series', 'my-village-hall'));
        }

        $remaining_bookings = $this->booking_repo->get_by_pattern_id($pattern_id);
        usort($remaining_bookings, static function (array $left, array $right): int {
            $left_key = sprintf(
                '%s %s %010d',
                (string) ($left['StartDate'] ?? ''),
                (string) ($left['StartTime'] ?? ''),
                \intval($left['Id'] ?? 0)
            );
            $right_key = sprintf(
                '%s %s %010d',
                (string) ($right['StartDate'] ?? ''),
                (string) ($right['StartTime'] ?? ''),
                \intval($right['Id'] ?? 0)
            );

            return strcmp($left_key, $right_key);
        });

        $remaining_count = count($remaining_bookings);
        if ($remaining_count <= 0) {
            $updated = $this->recurring_pattern_service->update_pattern($pattern_id, [
                'OccurrenceCount' => 0,
                'IsActive' => 0,
            ]);

            if ($updated === false) {
                return new WP_Error('database', __('Failed to update recurring booking series after detaching booking', 'my-village-hall'));
            }

            return true;
        }

        $pattern_update = [
            'OccurrenceCount' => $remaining_count,
        ];

        $is_parent_booking = \intval($pattern['ParentBookingId'] ?? 0) === $booking_id;
        if ($is_parent_booking) {
            $new_parent = $remaining_bookings[0];
            $pattern_update['ParentBookingId'] = \intval($new_parent['Id'] ?? 0);
            $pattern_update['StartDate'] = sanitize_text_field($new_parent['StartDate'] ?? '');
        }

        $updated = $this->recurring_pattern_service->update_pattern($pattern_id, $pattern_update);
        if ($updated === false) {
            return new WP_Error('database', __('Failed to update recurring booking series after detaching booking', 'my-village-hall'));
        }

        return true;
    }

    private function build_single_update_data(array $data, array $record, array $current_record): array {
        $normalized = $data;
        $normalized['booking_id'] = \intval($data['booking_id'] ?? $current_record['Id'] ?? 0);
        $normalized['customer_id'] = \intval($data['customer_id'] ?? $current_record['CustomerId'] ?? 0);
        $normalized['organisation_id'] = \intval($data['organisation_id'] ?? $current_record['OrganisationId'] ?? 0);
        $normalized['room_id'] = \intval($data['room_id'] ?? $current_record['RoomId'] ?? 0);
        $normalized['start_date'] = sanitize_text_field($data['start_date'] ?? $current_record['StartDate'] ?? '');
        $normalized['end_date'] = sanitize_text_field($data['end_date'] ?? $current_record['EndDate'] ?? $normalized['start_date']);
        $normalized['start_time'] = sanitize_text_field($data['start_time'] ?? $current_record['StartTime'] ?? '');
        $normalized['end_time'] = sanitize_text_field($data['end_time'] ?? $current_record['EndTime'] ?? '');
        $normalized['status'] = sanitize_text_field((string) ($record['Status'] ?? $data['status'] ?? $current_record['Status'] ?? ''));
        $normalized['description'] = sanitize_textarea_field($data['description'] ?? $record['Description'] ?? $current_record['Description'] ?? '');
        $normalized['public'] = \intval($record['Public'] ?? $data['public'] ?? $current_record['Public'] ?? 0);
        $normalized['no_invoice_required'] = \intval($record['NoInvoiceRequired'] ?? $data['no_invoice_required'] ?? $current_record['NoInvoiceRequired'] ?? 0);
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

    private function create_booking( mixed $data, mixed $record): int|WP_Error {
        // Before create event already dispatched in save()
        $booking_id = $this->booking_repo->create($record);
        if ($booking_id === false) {
            return new WP_Error('database', __('Failed to create booking', 'my-village-hall'));
        }

        $this->booking_addon_sync_service->sync($booking_id, $data['addons'] ?? [], false);

        $charge_result = $this->recalculate_booking_charges($booking_id);
        if (is_wp_error($charge_result)) {
            $this->booking_addon_repo->delete_by_booking_id($booking_id);
            $this->booking_repo->delete($booking_id);
            return $charge_result;
        }

        $deposit_result = $this->apply_deposit_after_create($booking_id, $record);
        if (is_wp_error($deposit_result)) {
            return $deposit_result;
        }

        $this->booking_creation_event_dispatcher->dispatch_created($booking_id, $data);

        $recurring_result = $this->recurring_booking_creator->create_for_booking($booking_id, $data);
        if (is_wp_error($recurring_result)) {
            return $recurring_result;
        }

        if (!empty($recurring_result)) {
            $this->last_warnings = array_merge($this->last_warnings, $recurring_result);
        }

        $this->booking_creation_event_dispatcher->dispatch_after_create($booking_id, $data);

        return $booking_id;
    }

    /**
     * Evaluate room deposit settings for a booking record.
     *
     * @param array $record
     * @return array<string,mixed>|null
     */
    private function evaluate_deposit_for_record(array $record): ?array {
        $room_id = \intval($record['RoomId'] ?? 0);
        $end_date = (string) ($record['EndDate'] ?? '');
        $end_time = (string) ($record['EndTime'] ?? '');

        if ($room_id <= 0 || $end_date === '' || $end_time === '') {
            return null;
        }

        try {
            $end = new \DateTime(trim($end_date . ' ' . $end_time));
        } catch (\Exception $exception) {
            return null;
        }

        return $this->deposit_service->evaluate($room_id, $end);
    }

    /**
     * Apply auto-add deposit behavior to any existing booking invoice.
     *
     * @param int $booking_id
     * @param array $record
     * @return true|WP_Error
     */
    private function apply_deposit_after_create(int $booking_id, array $record): bool|WP_Error {
        $deposit = $this->evaluate_deposit_for_record($record);
        if ($deposit === null) {
            return true;
        }

        if (($deposit['action'] ?? 'auto_add') !== 'auto_add') {
            return true;
        }

        $amount = (float) ($deposit['amount'] ?? 0.0);
        if ($amount <= 0.0) {
            return true;
        }

        $invoice_ids = $this->booking_repo->get_active_invoice_ids_for_booking($booking_id);
        if (empty($invoice_ids)) {
            return true;
        }

        foreach ($invoice_ids as $invoice_id) {
            $invoice_id = (int) $invoice_id;

            if ($this->invoice_item_repo->has_deposit_item($invoice_id, $booking_id)) {
                continue;
            }

            $added = $this->invoice_service->add_booking_line_item(
                $invoice_id,
                $booking_id,
                'deposit',
                $amount,
                'Deposit'
            );

            if (is_wp_error($added)) {
                return $added;
            }
        }

        return true;
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

    private function resolve_public_visibility( mixed $data, mixed $organisation_id, mixed $booking_id): int {
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

    public function save_addons( mixed $booking_id, mixed $addons, mixed $replace = false): void {
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

    public function get_charges_for_booking($booking_id): array {
        return $this->booking_charge_repo->get_by_booking_id((int) $booking_id);
    }

    public function get_deposit_items_for_booking($booking_id): array {
        return $this->invoice_item_repo->get_deposit_items_for_booking((int) $booking_id);
    }

    public function get_expected_deposit_for_booking($booking_id): ?array {
        $booking = $this->booking_repo->get_by_id((int) $booking_id);
        if (!$booking) {
            return null;
        }

        $record = [
            'RoomId' => $booking['RoomId'] ?? 0,
            'EndDate' => $booking['EndDate'] ?? $booking['StartDate'] ?? '',
            'EndTime' => $booking['EndTime'] ?? '00:00:00',
        ];

        return $this->evaluate_deposit_for_record($record);
    }

    public function get_by_id($booking_id): ?Booking {
        $booking_id = (int) $booking_id;

        if ($booking_id <= 0) {
            return null;
        }

        try {
            return $this->booking_repo->get($booking_id);
        } catch (\RuntimeException $exception) {
            return null;
        }
    }

    public function cancel($id): int|false {
        $current_record = $this->booking_repo->get_by_id($id);
        if (!$current_record) {
            return false;
        }

        $result = $this->update_status($id, BookingStatus::CANCELLED->value);

        if ($result !== false && ($current_record['Status'] ?? null) !== BookingStatus::CANCELLED->value) {
            $this->booking_update_event_dispatcher->dispatch(
                [
                    'booking_id' => $id,
                    'room_id' => $current_record['RoomId'] ?? null,
                    'start_time' => $current_record['StartTime'] ?? null,
                    'end_time' => $current_record['EndTime'] ?? null,
                    'status' => BookingStatus::CANCELLED->value,
                ],
                $current_record['Status'] ?? null
            );
        }

        return $result;
    }

    public function update_status( mixed $id, mixed $status): int|false {
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

    public function get_between( mixed $start, mixed $end, mixed $context = null, mixed $filters = []): array {
        return $this->booking_query_service->get_between($start, $end, $context, $filters);
    }

    public function move_booking( mixed $id, mixed $start, mixed $end, mixed $room): int|WP_Error {
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

    /**
     * Get uninvoiced bookings for reporting
     *
     * @param array $args Optional filters (orderby, order, limit, offset, organisation_id, customer_id)
     * @return array Array of uninvoiced bookings with details
     */
    public function get_uninvoiced_bookings($args = []): array {
        return $this->booking_repo->get_uninvoiced_bookings($args);
    }

    public function get_uninvoiced_single_bookings($args = []): array {
        return $this->booking_repo->get_uninvoiced_single_bookings($args);
    }

    public function get_uninvoiced_recurring_bookings($args = []): array {
        return $this->booking_repo->get_uninvoiced_recurring_bookings($args);
    }

    /**
     * Get confirmed bookings for a recurring pattern that fall within a date range.
     *
     * Useful for period-based auto-invoicing where you want all bookings in a specific
     * period (week, month, quarter) to be invoiced together.
     *
     * @param int $pattern_id The recurring pattern ID
     * @param string $start_date Start date in Y-m-d format
     * @param string $end_date End date in Y-m-d format (inclusive)
     * @return array Array of confirmed booking records
     */
    public function get_bookings_by_recurring_pattern_in_period(int $pattern_id, string $start_date, string $end_date): array {
        $bookings = $this->booking_repo->get_by_pattern_id_in_period($pattern_id, $start_date, $end_date);

        // Filter to only confirmed bookings
        return array_values(array_filter($bookings, function (array $booking): bool {
            return ($booking['Status'] ?? '') === 'confirmed';
        }));
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
