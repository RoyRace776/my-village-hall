<?php

namespace MYVH\Pricing;

class CachedRoomRateRepository extends RoomRateRepository
{
    private const CACHE_GROUP = 'myvh_room_rates';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_room_rates_';
    private const VERSION_KEY = 'version';

    private RoomRateRepository $repository;

    public function __construct(RoomRateRepository $repository)
    {
        $this->repository = $repository;
        $this->wpdb = $repository->wpdb;
        $this->table_name = $repository->table_name;
    }

    public function get_by_id($id): ?array
    {
        $cache_key = $this->buildCacheKey(['get_by_id', $id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rate = $this->repository->get_by_id($id);
        wp_cache_set($cache_key, $rate, self::CACHE_GROUP, self::CACHE_TTL);

        return $rate;
    }

    public function get_all($args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rates = $this->repository->get_all($args);
        wp_cache_set($cache_key, $rates, self::CACHE_GROUP, self::CACHE_TTL);

        return $rates;
    }

    public function get_by_room($room_id): array
    {
        $cache_key = $this->buildCacheKey(['get_by_room', $room_id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rates = $this->repository->get_by_room($room_id);
        wp_cache_set($cache_key, $rates, self::CACHE_GROUP, self::CACHE_TTL);

        return $rates;
    }

    public function get_room_ids_with_active_rates(array $room_ids = []): array
    {
        $cache_key = $this->buildCacheKey(['get_room_ids_with_active_rates', $room_ids]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $ids = $this->repository->get_room_ids_with_active_rates($room_ids);
        wp_cache_set($cache_key, $ids, self::CACHE_GROUP, self::CACHE_TTL);

        return $ids;
    }

    public function get_active_room_rate($room_id, $org_type_id = null): ?array
    {
        $cache_key = $this->buildCacheKey(['get_active_room_rate', $room_id, $org_type_id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rate = $this->repository->get_active_room_rate($room_id, $org_type_id);
        wp_cache_set($cache_key, $rate, self::CACHE_GROUP, self::CACHE_TTL);

        return $rate;
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

    public function delete_by_id($id): bool
    {
        $result = $this->repository->delete_by_id($id);
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
