<?php

namespace MYVH\Addons;

class CachedAddonRepository extends AddonRepository
{
    private const CACHE_GROUP = 'myvh_addons';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_addons_';
    private const VERSION_KEY = 'version';

    private AddonRepository $repository;

    public function __construct(AddonRepository $repository)
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

        $addon = $this->repository->get_by_id($id);
        wp_cache_set($cache_key, $addon, self::CACHE_GROUP, self::CACHE_TTL);

        return $addon;
    }

    public function get_all($args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $addons = $this->repository->get_all($args);
        wp_cache_set($cache_key, $addons, self::CACHE_GROUP, self::CACHE_TTL);

        return $addons;
    }

    public function get_all_with_relations(bool $include_archived = false): array
    {
        $cache_key = $this->buildCacheKey(['get_all_with_relations', $include_archived]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $addons = $this->repository->get_all_with_relations($include_archived);
        wp_cache_set($cache_key, $addons, self::CACHE_GROUP, self::CACHE_TTL);

        return $addons;
    }

    public function get_by_room($room_id): array
    {
        $cache_key = $this->buildCacheKey(['get_by_room', $room_id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $addons = $this->repository->get_by_room($room_id);
        wp_cache_set($cache_key, $addons, self::CACHE_GROUP, self::CACHE_TTL);

        return $addons;
    }

    public function get_active_by_id(int $id): ?array
    {
        $cache_key = $this->buildCacheKey(['get_active_by_id', $id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $addon = $this->repository->get_active_by_id($id);
        wp_cache_set($cache_key, $addon, self::CACHE_GROUP, self::CACHE_TTL);

        return $addon;
    }

    public function get_all_active(array $args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all_active', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $addons = $this->repository->get_all_active($args);
        wp_cache_set($cache_key, $addons, self::CACHE_GROUP, self::CACHE_TTL);

        return $addons;
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

    public function archive_by_id(int $id): bool
    {
        $result = $this->repository->archive_by_id($id);
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
