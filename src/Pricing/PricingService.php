<?php
namespace MYVH\Pricing;

use MYVH\Pricing\RoomRateService;
use MYVH\Customers\CustomerRepository;
use MYVH\Organisations\OrganisationRepository;
use MYVH\Bookings\BookingRepository;
use MYVH\Bookings\BookingAddonRepository;
use WP_Error;

class PricingService {

    private $room_rate_service;
    private $booking_repo;
    private $customer_repo;
    private $organisation_repo;
    private $booking_addon_repo;

    public function __construct(RoomRateService $room_rate_service,
                                BookingRepository $booking_repo,
                                CustomerRepository $customer_repo,
                                OrganisationRepository $organisation_repo,
                                BookingAddonRepository $booking_addons_repo) {
        $this->room_rate_service = $room_rate_service;
        $this->booking_repo = $booking_repo;
        $this->customer_repo = $customer_repo;
        $this->organisation_repo = $organisation_repo;
        $this->booking_addon_repo = $booking_addons_repo;
    }

    public function calculate_price($booking_id): float|WP_Error | array{

        $snapshot = $this->get_charge_snapshot($booking_id);
        if (is_wp_error($snapshot)) {
            return $snapshot;
        }

        return floatval($snapshot['TotalAmount']) + $this->calculate_addon_price($booking_id);
    }

    /**
     * Build a persistable snapshot payload for myvh_booking_charges.
     *
     * @param int $booking_id
     * @return array|WP_Error
     */
    public function get_charge_snapshot($booking_id): array|WP_Error {

        $booking_id = intval($booking_id);
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

        $room_rate = $this->room_rate_service->get_booking_rate($booking['RoomId'], $customer, $organisation);
        if (!$room_rate) {
            return new WP_Error('no room rate', __('No room rate configured', 'my-village-hall'));
        }

        ['quantity' => $quantity, 'unit_price' => $unit_price, 'total' => $total] =
            $this->calculate_room_price($booking, $room_rate);

        return [
            'BookingId'   => $booking_id,
            'RoomRateId'  => intval($room_rate['Id']),
            'ChargeType'  => sanitize_text_field((string) ($room_rate['ChargeType'] ?? $room_rate['RateType'] ?? 'fixed')),
            'Description' => __('Room charge snapshot', 'my-village-hall'),
            'Quantity'    => $quantity,
            'UnitPrice'   => $unit_price,
            'TotalAmount' => $total,
            'TaxRate'     => 0.00,
            'TaxAmount'   => 0.00,
        ];
    }

    /**
     * Returns quantity, unit_price, and total for the room charge.
     *
     * For hourly/per-day rates: Quantity = hours booked, UnitPrice = rate per hour.
     * For fixed rates: Quantity = 1, UnitPrice = fixed rate.
     *
     * @param array $booking
     * @param array $room_rate
     * @return array { quantity: float, unit_price: float, total: float }
     */
    private function calculate_room_price($booking, $room_rate) {

        $charge_type = $room_rate['ChargeType'] ?? $room_rate['RateType'] ?? '';
        $rate        = floatval($room_rate['Rate']);

        if ($charge_type === 'fixed') {
            return [
                'quantity'   => 1.00,
                'unit_price' => round($rate, 2),
                'total'      => round($rate, 2),
            ];
        }

        // Hourly / per-day: quantity is the number of hours booked
        $start = strtotime($booking['StartDate'] . ' ' . $booking['StartTime']);
        $end   = strtotime($booking['EndDate']   . ' ' . $booking['EndTime']);
        $hours = round(($end - $start) / 3600, 2);

        return [
            'quantity'   => $hours,
            'unit_price' => round($rate, 2),
            'total'      => round($hours * $rate, 2),
        ];
    }

    private function calculate_addon_price($booking_id) {

    // Get the addons. This might be an empty array if there aren't any
    $booking_addons = $this->booking_addon_repo->get_by_booking_id($booking_id);

    $addon_price = 0.0;

    foreach ($booking_addons as $booking_addon) {
        // The addon price is held on the booking addons table
        $addon_price += $booking_addon['TotalAmount'];
    }

    return $addon_price;
    }
}