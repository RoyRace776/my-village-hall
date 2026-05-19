<?php
namespace MYVH\Pricing;

use MYVH\Pricing\RoomRateService;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingAddonRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use WP_Error;

class PricingService {
    private const SLOT_SECONDS = 1800;

    private RoomRateService $room_rate_service;
    private BookingRepository $booking_repo;
    private CustomerRepository $customer_repo;
    private OrganisationRepository $organisation_repo;
    private BookingAddonRepository $booking_addon_repo;
    private LoggerInterface $logger;

    public function __construct(RoomRateService $room_rate_service,
                                BookingRepository $booking_repo,
                                CustomerRepository $customer_repo,
                                OrganisationRepository $organisation_repo,
                                BookingAddonRepository $booking_addons_repo,
                                ?LoggerInterface $logger = null) {
        $this->room_rate_service = $room_rate_service;
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->booking_addon_repo = $booking_addons_repo;
        $this->logger = $logger ?? new NullLogger();
    }

    public function calculate_price(int $booking_id): float|WP_Error | array{

        $snapshot = $this->get_charge_snapshot($booking_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        return \floatval($snapshot['TotalAmount']) + $this->calculate_addon_price($booking_id);
    }

    /**
     * Build a persistable snapshot payload for myvh_booking_charges.
     *
     * @param int $booking_id
     * @return array|WP_Error
     */
    public function get_charge_snapshot(int $booking_id): array|WP_Error {

        $booking_id = \intval($booking_id);
        if ($booking_id <= 0) {
            return new WP_Error('invalid_booking', __('Invalid booking id', 'my-village-hall'));
        }

        $booking = $this->booking_repo->get_by_id($booking_id);
        if (!$booking) {
            return new WP_Error('No booking', __('No booking found', 'my-village-hall'));
        }

        $customer = $this->customer_repo->get_by_id($booking['CustomerId']);
        if (!$customer) {
            return new WP_Error('no customer', __('No customer found for booking', 'my-village-hall'));
        }

        $organisation = $this->organisation_repo->get_by_id($booking['OrganisationId']);
        if (!$organisation) {
            return new WP_Error('no organisation', __('No organisation found for booking', 'my-village-hall'));
        }

        $room_charge = $this->calculate_segmented_room_charge(
            (int) ($booking['RoomId'] ?? 0),
            (int) ($organisation['OrganisationTypeId'] ?? 0),
            (string) ($booking['StartDate'] ?? ''),
            (string) ($booking['StartTime'] ?? ''),
            (string) ($booking['EndDate'] ?? ''),
            (string) ($booking['EndTime'] ?? ''),
            (string) ($booking['StartDate'] ?? '')
        );
        if (is_wp_error($room_charge)) {
            return $room_charge;
        }

        return [
            'BookingId'   => $booking_id,
            'RoomRateId'  => \intval($room_charge['room_rate_id'] ?? 0),
            'ChargeType'  => sanitize_text_field((string) ($room_charge['charge_type'] ?? 'fixed')),
            'Description' => __('Room charge', 'my-village-hall'),
            'Quantity'    => (float) ($room_charge['quantity'] ?? 0.0),
            'UnitPrice'   => (float) ($room_charge['unit_price'] ?? 0.0),
            'TotalAmount' => (float) ($room_charge['total'] ?? 0.0),
            'TaxRate'     => 0.00,
            'TaxAmount'   => 0.00,
        ];
    }

    /**
     * Build a charge snapshot for unsaved booking data.
     *
     * @param array $booking_data
     * @return array|WP_Error
     */
    public function get_charge_snapshot_for_data(array $booking_data): array|WP_Error {
        $room_id = \intval($booking_data['room_id'] ?? $booking_data['RoomId'] ?? 0);
        $customer_id = \intval($booking_data['customer_id'] ?? $booking_data['CustomerId'] ?? 0);
        $organisation_id = \intval($booking_data['organisation_id'] ?? $booking_data['OrganisationId'] ?? 0);
        $start_date = sanitize_text_field((string) ($booking_data['start_date'] ?? $booking_data['StartDate'] ?? ''));
        $start_time = sanitize_text_field((string) ($booking_data['start_time'] ?? $booking_data['StartTime'] ?? ''));
        $end_date = sanitize_text_field((string) ($booking_data['end_date'] ?? $booking_data['EndDate'] ?? ''));
        $end_time = sanitize_text_field((string) ($booking_data['end_time'] ?? $booking_data['EndTime'] ?? ''));

        if ($room_id <= 0) {
            return new WP_Error('invalid_room', __('Room is required', 'my-village-hall'));
        }

        if ($customer_id <= 0) {
            return new WP_Error('invalid_customer', __('Customer is required', 'my-village-hall'));
        }

        if ($organisation_id <= 0) {
            return new WP_Error('invalid_organisation', __('Organisation is required', 'my-village-hall'));
        }

        if ($start_date === '' || $start_time === '' || $end_date === '' || $end_time === '') {
            return new WP_Error('invalid_datetime', __('Start and end date/time are required', 'my-village-hall'));
        }

        $customer = $this->customer_repo->get_by_id($customer_id);
        if (!$customer) {
            return new WP_Error('no customer', __('No customer found for booking', 'my-village-hall'));
        }

        $organisation = $this->organisation_repo->get_by_id($organisation_id);
        if (!$organisation) {
            return new WP_Error('no organisation', __('No organisation found for booking', 'my-village-hall'));
        }

        $room_charge = $this->calculate_segmented_room_charge(
            $room_id,
            (int) ($organisation['OrganisationTypeId'] ?? 0),
            $start_date,
            $start_time,
            $end_date,
            $end_time,
            $start_date
        );
        if (is_wp_error($room_charge)) {
            return $room_charge;
        }

        return [
            'BookingId'   => 0,
            'RoomRateId'  => \intval($room_charge['room_rate_id'] ?? 0),
            'ChargeType'  => sanitize_text_field((string) ($room_charge['charge_type'] ?? 'fixed')),
            'Description' => __('Room charge', 'my-village-hall'),
            'Quantity'    => (float) ($room_charge['quantity'] ?? 0.0),
            'UnitPrice'   => (float) ($room_charge['unit_price'] ?? 0.0),
            'TotalAmount' => (float) ($room_charge['total'] ?? 0.0),
            'TaxRate'     => 0.00,
            'TaxAmount'   => 0.00,
        ];
    }

    /**
     * Preview a room charge against active rates valid on the day the test runs.
     *
     * @return array|WP_Error
     */
    public function test_room_rate_schedule(
        int $room_id,
        int $organisation_type_id,
        string $start_date,
        string $start_time,
        string $end_date,
        string $end_time
    ): array|WP_Error {
        $validity_reference_date = date('Y-m-d');

        $charge = $this->calculate_segmented_room_charge(
            $room_id,
            $organisation_type_id,
            $start_date,
            $start_time,
            $end_date,
            $end_time,
            $validity_reference_date
        );
        if (is_wp_error($charge)) {
            return $charge;
        }

        return [
            'room_rate_id' => (int) ($charge['room_rate_id'] ?? 0),
            'charge_type' => (string) ($charge['charge_type'] ?? ''),
            'quantity' => (float) ($charge['quantity'] ?? 0.0),
            'unit_price' => (float) ($charge['unit_price'] ?? 0.0),
            'total' => (float) ($charge['total'] ?? 0.0),
            'used_rates' => is_array($charge['used_rates'] ?? null) ? $charge['used_rates'] : [],
            'segments' => is_array($charge['segments'] ?? null) ? $charge['segments'] : [],
            'validity_reference_date' => $validity_reference_date,
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    private function calculate_segmented_room_charge(
        int $room_id,
        int $organisation_type_id,
        string $start_date,
        string $start_time,
        string $end_date,
        string $end_time,
        ?string $validity_reference_date = null
    ): array|WP_Error {
        if ($room_id <= 0) {
            return new WP_Error('invalid_room', __('Room is required', 'my-village-hall'));
        }

        $start = $this->create_datetime($start_date, $start_time);
        $end = $this->create_datetime($end_date, $end_time);
        if (!$start || !$end || $end <= $start) {
            return new WP_Error('invalid_datetime', __('Start and end date/time are required', 'my-village-hall'));
        }

        $validity_date = $validity_reference_date ?: $start->format('Y-m-d');
        $org_type_scope = $organisation_type_id > 0 ? $organisation_type_id : null;

        $org_specific_rates = $org_type_scope
            ? $this->room_rate_service->get_active_rates_for_scope($room_id, $org_type_scope, $validity_date)
            : [];
        $all_type_rates = $this->room_rate_service->get_active_rates_for_scope($room_id, null, $validity_date);

        if (empty($org_specific_rates) && empty($all_type_rates)) {
            return new WP_Error('no room rate', __('No room rate configured', 'my-village-hall'));
        }

        $cursor = $start;
        $hours = 0.0;
        $total = 0.0;
        $segments = [];
        $first_rate_id = 0;
        $first_charge_type = '';

        while ($cursor < $end) {
            $slice_end = $cursor->modify('+' . self::SLOT_SECONDS . ' seconds');
            if ($slice_end > $end) {
                $slice_end = $end;
            }

            $resolved = $this->resolve_rate_for_slice($org_specific_rates, $all_type_rates, $cursor, $slice_end);
            if ($resolved === null) {
                return new WP_Error(
                    'no_room_rate_window',
                    sprintf(
                        __('No room rate covers %1$s to %2$s', 'my-village-hall'),
                        $cursor->format('Y-m-d H:i'),
                        $slice_end->format('Y-m-d H:i')
                    )
                );
            }

            $rate = $resolved['rate'];
            $scope = $resolved['scope'];
            $rate_id = (int) ($rate['Id'] ?? 0);
            $charge_type = (string) ($rate['ChargeType'] ?? $rate['RateType'] ?? 'per_hour');
            $unit_rate = (float) ($rate['Rate'] ?? 0.0);

            if ($first_rate_id === 0) {
                $first_rate_id = $rate_id;
                $first_charge_type = $charge_type;
            }

            if ($charge_type === 'fixed') {
                return [
                    'room_rate_id' => $rate_id,
                    'charge_type' => 'fixed',
                    'quantity' => 1.0,
                    'unit_price' => round($unit_rate, 2),
                    'total' => round($unit_rate, 2),
                    'used_rates' => [
                        [
                            'room_rate_id' => $rate_id,
                            'name' => (string) ($rate['Name'] ?? ''),
                            'scope' => $scope,
                        ],
                    ],
                    'segments' => [
                        [
                            'room_rate_id' => $rate_id,
                            'room_rate_name' => (string) ($rate['Name'] ?? ''),
                            'scope' => $scope,
                            'charge_type' => 'fixed',
                            'start' => $start->format('Y-m-d H:i:s'),
                            'end' => $end->format('Y-m-d H:i:s'),
                            'hours' => round(($end->getTimestamp() - $start->getTimestamp()) / 3600, 2),
                            'rate' => round($unit_rate, 2),
                            'subtotal' => round($unit_rate, 2),
                        ],
                    ],
                ];
            }

            $slice_hours = ($slice_end->getTimestamp() - $cursor->getTimestamp()) / 3600;
            $slice_total = $slice_hours * $unit_rate;

            $hours += $slice_hours;
            $total += $slice_total;
            $this->append_or_extend_segment(
                $segments,
                $rate_id,
                (string) ($rate['Name'] ?? ''),
                $scope,
                $charge_type,
                $cursor,
                $slice_end,
                $slice_hours,
                $unit_rate,
                $slice_total
            );

            $cursor = $slice_end;
        }

        $rounded_total = round($total, 2);
        $rounded_hours = round($hours, 2);
        $unit_price = $rounded_hours > 0 ? round($rounded_total / $rounded_hours, 2) : 0.0;

        return [
            'room_rate_id' => $first_rate_id,
            'charge_type' => $first_charge_type !== '' ? $first_charge_type : 'per_hour',
            'quantity' => $rounded_hours,
            'unit_price' => $unit_price,
            'total' => $rounded_total,
            'used_rates' => $this->extract_used_rates($segments),
            'segments' => $segments,
        ];
    }

    private function calculate_addon_price(int $booking_id): float {

    // Get the addons. This might be an empty array if there aren't any
    $booking_addons = $this->booking_addon_repo->get_by_booking_id($booking_id);

    $addon_price = 0.0;

    foreach ($booking_addons as $booking_addon) {
        // The addon price is held on the booking addons table
        $addon_price += \floatval($booking_addon['TotalAmount']);
    }

    return $addon_price;
    }

    /**
     * @param array<int, array<string, mixed>> $org_specific_rates
     * @param array<int, array<string, mixed>> $all_type_rates
     * @return array{rate: array<string, mixed>, scope: string}|null
     */
    private function resolve_rate_for_slice(
        array $org_specific_rates,
        array $all_type_rates,
        \DateTimeImmutable $slice_start,
        \DateTimeImmutable $slice_end
    ): ?array {
        $org_match = $this->room_rate_service->match_rate_for_slot($org_specific_rates, $slice_start, $slice_end);
        if ($org_match) {
            return [
                'rate' => $org_match,
                'scope' => 'organisation_type',
            ];
        }

        $all_match = $this->room_rate_service->match_rate_for_slot($all_type_rates, $slice_start, $slice_end);
        if ($all_match) {
            return [
                'rate' => $all_match,
                'scope' => 'all_types',
            ];
        }

        return null;
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     */
    private function append_or_extend_segment(
        array &$segments,
        int $rate_id,
        string $rate_name,
        string $scope,
        string $charge_type,
        \DateTimeImmutable $slice_start,
        \DateTimeImmutable $slice_end,
        float $slice_hours,
        float $unit_rate,
        float $slice_total
    ): void {
        $last_index = count($segments) - 1;
        if ($last_index >= 0) {
            $last = $segments[$last_index];
            $is_same_rate = (int) ($last['room_rate_id'] ?? 0) === $rate_id
                && (string) ($last['scope'] ?? '') === $scope
                && (string) ($last['charge_type'] ?? '') === $charge_type
                && abs(((float) ($last['rate'] ?? 0.0)) - $unit_rate) < 0.0001;

            if ($is_same_rate) {
                $segments[$last_index]['end'] = $slice_end->format('Y-m-d H:i:s');
                $segments[$last_index]['hours'] = round((float) ($segments[$last_index]['hours'] ?? 0.0) + $slice_hours, 2);
                $segments[$last_index]['subtotal'] = round((float) ($segments[$last_index]['subtotal'] ?? 0.0) + $slice_total, 2);
                return;
            }
        }

        $segments[] = [
            'room_rate_id' => $rate_id,
            'room_rate_name' => $rate_name,
            'scope' => $scope,
            'charge_type' => $charge_type,
            'start' => $slice_start->format('Y-m-d H:i:s'),
            'end' => $slice_end->format('Y-m-d H:i:s'),
            'hours' => round($slice_hours, 2),
            'rate' => round($unit_rate, 2),
            'subtotal' => round($slice_total, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $segments
     * @return array<int, array<string, mixed>>
     */
    private function extract_used_rates(array $segments): array {
        $used = [];

        foreach ($segments as $segment) {
            $rate_id = (int) ($segment['room_rate_id'] ?? 0);
            if ($rate_id <= 0) {
                continue;
            }

            if (isset($used[$rate_id])) {
                continue;
            }

            $used[$rate_id] = [
                'room_rate_id' => $rate_id,
                'name' => (string) ($segment['room_rate_name'] ?? ''),
                'scope' => (string) ($segment['scope'] ?? ''),
            ];
        }

        return array_values($used);
    }

    private function create_datetime(string $date, string $time): ?\DateTimeImmutable {
        $value = trim($date . ' ' . $time);
        if ($value === '') {
            return null;
        }

        return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d H:i', $value)
            ?: null;
    }
}