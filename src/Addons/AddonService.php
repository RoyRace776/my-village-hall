<?php
namespace MYVH\Addons;

use MYVH\Bookings\BookingAddonRepository;
use MYVH\Bookings\BookingRepository;

if (!defined('ABSPATH')) exit;

class AddonService {

    private $repo;
    private $booking_addon_repo;
    private $booking_repo;

    public function __construct(AddonRepository $repo,
                                BookingAddonRepository $booking_addon_repo,
                                BookingRepository $booking_repo) {
        $this->repo = $repo;
        $this->booking_addon_repo = $booking_addon_repo;
        $this->booking_repo = $booking_repo;
    }

    public function get_all($args = []): array {
        return $this->repo->get_all($args);
    }

    public function get($id): ?array {
        return $this->repo->get_by_id($id);
    }

    public function get_with_relations(): array {
        return $this->repo->get_all_with_relations();
    }

    public function get_by_room($room_id): array {
        return $this->repo->get_by_room($room_id);
    }

    public function save($data): int|\WP_Error {

        if (empty($data['name'])) {
            return new \WP_Error('validation', __('Add-on name is required', 'my-village-hall'));
        }

        if (empty($data['price']) || floatval($data['price']) < 0) {
            return new \WP_Error('validation', __('Valid price is required', 'my-village-hall'));
        }

        if (empty($data['charge_type'])) {
            return new \WP_Error('validation', __('Charge type is required', 'my-village-hall'));
        }

        $record = [
            'Name'         => sanitize_text_field($data['name']),
            'Description'  => sanitize_textarea_field($data['description'] ?? ''),
            'Price'        => floatval($data['price']),
            'ChargeType'   => sanitize_text_field($data['charge_type']),
            'RoomId'       => !empty($data['room_id']) ? intval($data['room_id']) : null,
            'IsActive'     => isset($data['is_active']) ? 1 : 0,
            'DisplayOrder' => intval($data['display_order'] ?? 0),
        ];

        if (!empty($data['addon_id'])) {
            $result = $this->repo->update($record, ['Id' => intval($data['addon_id'])]);
            if ($result === false) {
                return new \WP_Error('database', __('Failed to update add-on', 'my-village-hall'));
            }
            return intval($data['addon_id']);
        }

        $result = $this->repo->create($record);
        if ($result === false) {
            return new \WP_Error('database', __('Failed to create add-on', 'my-village-hall'));
        }

        return $result;
    }

    public function delete($id) {
        return $this->repo->delete_by_id($id);
    }

    /**
     * Save addons for a booking. Optionally delete existing ones first (for updates).
     *
     * @param int   $booking_id
     * @param array $addons     Array of ['addon_id' => int, 'quantity' => float, 'unit_price' => float, 'description' => string]
     * @param bool  $replace    If true, delete all existing addons before saving
     */
    public function save_booking_addons($booking_id, $addons, $replace = false) {
        $booking_addon_repo = $this->booking_addon_repo;
        $addon_repo = $this->repo;
        if (!$booking_addon_repo) return;

        if ($replace) {
            $booking_addon_repo->delete_by_booking_id($booking_id);
        }

        if (empty($addons) || !is_array($addons)) return;

        // Fetch booking hours lazily — only needed when a per_hour addon is present.
        $booking_hours = null;

        foreach ($addons as $addon) {
            $addon_id   = intval($addon['addon_id'] ?? 0);
            $unit_price = floatval($addon['unit_price'] ?? 0);

            if ($addon_id <= 0) continue;

            // Quantity is system-derived: fixed/per_day => 1, per_hour => booking hours.
            $quantity = 1.0;
            $addon_record = $addon_repo ? $addon_repo->get_by_id($addon_id) : null;
            if ($addon_record && ($addon_record['ChargeType'] ?? '') === 'per_hour') {
                if ($booking_hours === null) {
                    // Fetch booking details if needed (assume booking_repo is globally available)
                    $booking_repo = $this->booking_repo;
                    $booking = $booking_repo ? $booking_repo->get_by_id($booking_id) : null;
                    if ($booking) {
                        $start = strtotime($booking['StartDate'] . ' ' . $booking['StartTime']);
                        $end   = strtotime($booking['EndDate']   . ' ' . $booking['EndTime']);
                        $booking_hours = round(($end - $start) / 3600, 2);
                    }
                }
                if ($booking_hours !== null) {
                    $quantity = max(0, $booking_hours);
                }
            }

            if ($quantity <= 0) continue;

            $booking_addon_repo->create([
                'BookingId'   => $booking_id,
                'AddonId'     => $addon_id,
                'Quantity'    => $quantity,
                'UnitPrice'   => $unit_price,
                'TotalAmount' => round($quantity * $unit_price, 2),
                'Description' => sanitize_text_field($addon['description'] ?? ''),
            ]);
        }
    }

    /**
     * Get all addons for a booking (with addon name and type).
     *
     * @param int $booking_id
     * @return array
     */
    public function get_addons_for_booking($booking_id) {
        return $this->booking_addon_repo->get_by_booking_id($booking_id) ?? [];
    }
}