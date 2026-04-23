<?php

namespace MYVH\Venues;

class CachedVenueHoursRepository extends VenueHoursRepository
{
    private const CACHE_GROUP = 'myvh_venue_hours';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_venue_hours_';
    private const VERSION_KEY = 'version';

    private VenueHoursRepository $repository;

    public function __construct(VenueHoursRepository $repository)
    {
        $this->repository = $repository;
        $this->wpdb = $repository->wpdb;
        $this->table_name = $repository->table_name;
    }

    public function get_by_venue(int $venue_id): array
    {
        $cache_key = $this->buildCacheKey([$venue_id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rows = $this->repository->get_by_venue($venue_id);
        wp_cache_set($cache_key, $rows, self::CACHE_GROUP, self::CACHE_TTL);

        return $rows;
    }

    public function replace_for_venue(int $venue_id, array $rows): bool
    {
        $result = $this->repository->replace_for_venue($venue_id, $rows);
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
