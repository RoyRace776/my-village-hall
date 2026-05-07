<?php

namespace MYVH\Bookings;

class CachedBookingRepository extends BookingRepository
{
    private const CACHE_GROUP = 'myvh_bookings';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_bookings_';
    private const VERSION_KEY = 'version';

    private BookingRepository $repository;

    public function __construct(BookingRepository $repository)
    {
        $this->repository = $repository;
        $this->wpdb = $repository->wpdb;
        $this->table_name = $repository->table_name;
    }

    public function get(int $id): Booking
    {
        $cache_key = $this->buildCacheKey([$id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $booking = $this->repository->get($id);
        wp_cache_set($cache_key, $booking, self::CACHE_GROUP, self::CACHE_TTL);

        return $booking;
    }

    public function getAll(): array
    {
        $cache_key = $this->buildCacheKey([]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $bookings = $this->repository->getAll();
        wp_cache_set($cache_key, $bookings, self::CACHE_GROUP, self::CACHE_TTL);

        return $bookings;
    }

    public function get_between($start, $end, $context = null, $filters = []): array
    {
        $cache_key = $this->buildCacheKey([$start, $end, $context, $filters]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $bookings = $this->repository->get_between($start, $end, $context, $filters);
        wp_cache_set($cache_key, $bookings, self::CACHE_GROUP, self::CACHE_TTL);

        return $bookings;
    }

    public function get_all_with_details($args = []): array
    {
        $cache_key = $this->buildCacheKey([$args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $bookings = $this->repository->get_all_with_details($args);
        wp_cache_set($cache_key, $bookings, self::CACHE_GROUP, self::CACHE_TTL);

        return $bookings;
    }

    public function get_upcoming_bookings($customer_id): array
    {
        $cache_key = $this->buildCacheKey([$customer_id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $bookings = $this->repository->get_upcoming_bookings($customer_id);
        wp_cache_set($cache_key, $bookings, self::CACHE_GROUP, self::CACHE_TTL);

        return $bookings;
    }

    public function create($data): int|false
    {
        $result = $this->repository->create($data);
        $this->incrementCacheVersion();

        return $result;
    }

    public function update($data, $where): bool
    {
        $result = $this->repository->update($data, $where);
        $this->incrementCacheVersion();

        return $result;
    }

    public function delete($id): bool
    {
        $result = $this->repository->delete($id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function cancel_future_by_pattern($pattern_id): int|false
    {
        $result = $this->repository->cancel_future_by_pattern($pattern_id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function delete_future_by_pattern($pattern_id): int|false
    {
        $result = $this->repository->delete_future_by_pattern($pattern_id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function begin(): void
    {
        $this->repository->begin();
    }

    public function commit(): void
    {
        $this->repository->commit();
    }

    public function rollback(): void
    {
        $this->repository->rollback();
    }

    public function get_by_id($id): ?array
    {
        return $this->repository->get_by_id($id);
    }

    public function get_all($args = []): array
    {
        return $this->repository->get_all($args);
    }

    public function delete_by_id($id): bool
    {
        $result = $this->repository->delete_by_id($id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function find($where = [], $order = '', $limit = null, $offset = null): array
    {
        return $this->repository->find($where, $order, $limit, $offset);
    }

    public function soft_delete_by_id($id): bool
    {
        $result = $this->repository->soft_delete_by_id($id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function restore_by_id($id): bool
    {
        $result = $this->repository->restore_by_id($id);
        $this->incrementCacheVersion();

        return $result;
    }

    public function with_deleted(): array
    {
        return $this->repository->with_deleted();
    }

    public function only_deleted(): array
    {
        return $this->repository->only_deleted();
    }

    public function paginate($page = 1, $per_page = 20, $where = []): array
    {
        return $this->repository->paginate($page, $per_page, $where);
    }

    public function get_by_pattern_id($pattern_id): array
    {
        return $this->repository->get_by_pattern_id($pattern_id);
    }

    public function has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id = null, $end_date = null): bool
    {
        return $this->repository->has_conflict($room_id, $date, $start_time, $end_time, $exclude_booking_id, $end_date);
    }

    public function count_by_customer(int $customer_id): int
    {
        return $this->repository->count_by_customer($customer_id);
    }

    public function get_conflicts($room_id, $start, $end): array
    {
        return $this->repository->get_conflicts($room_id, $start, $end);
    }

    public function move_booking($id, $start, $end, $room): int|\WP_Error
    {
        $result = $this->repository->move_booking($id, $start, $end, $room);
        $this->incrementCacheVersion();

        return $result;
    }

    public function get_uninvoiced_bookings($args = []): array
    {
        return $this->repository->get_uninvoiced_bookings($args);
    }

    public function get_uninvoiced_single_bookings($args = []): array
    {
        return $this->repository->get_uninvoiced_single_bookings($args);
    }

    public function count_uninvoiced_by_organisation(): array
    {
        return $this->repository->count_uninvoiced_by_organisation();
    }

    public function count_uninvoiced_by_customer(): array
    {
        return $this->repository->count_uninvoiced_by_customer();
    }

    public function has_invoiced_items($booking_id): bool
    {
        return $this->repository->has_invoiced_items($booking_id);
    }

    public function get_active_invoice_ids_for_booking(int $booking_id): array
    {
        return $this->repository->get_active_invoice_ids_for_booking($booking_id);
    }

    public function is_no_invoice_required(int $booking_id): bool
    {
        return $this->repository->is_no_invoice_required($booking_id);
    }

    public function has_conflict_in_buffer_window(int $room_id, string $window_start, string $window_end, ?int $exclude_booking_id = null): bool
    {
        return $this->repository->has_conflict_in_buffer_window($room_id, $window_start, $window_end, $exclude_booking_id);
    }

    public function __call($name, $arguments)
    {
        return $this->repository->{$name}(...$arguments);
    }

    private function buildCacheKey(array $args): string
    {
        return self::CACHE_KEY_PREFIX . $this->getCacheVersion() . '_' . md5(serialize($args));
    }

    private function getCacheVersion(): int
    {
        $version = wp_cache_get(self::VERSION_KEY, self::CACHE_GROUP);

        if ($version === false) {
            $version = 1;
            wp_cache_set(self::VERSION_KEY, $version, self::CACHE_GROUP, self::CACHE_TTL);
        }

        return (int) $version;
    }

    private function incrementCacheVersion(): void
    {
        $version = $this->getCacheVersion() + 1;
        wp_cache_set(self::VERSION_KEY, $version, self::CACHE_GROUP, self::CACHE_TTL);
    }
}