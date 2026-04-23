<?php

namespace MYVH\Organisations;

class CachedOrganisationRepository extends OrganisationRepository
{
    private const CACHE_GROUP = 'myvh_organisations';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_organisations_';
    private const VERSION_KEY = 'version';

    private OrganisationRepository $repository;

    public function __construct(OrganisationRepository $repository)
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

        $organisation = $this->repository->get_by_id($id);
        wp_cache_set($cache_key, $organisation, self::CACHE_GROUP, self::CACHE_TTL);

        return $organisation;
    }

    public function get_all($args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $organisations = $this->repository->get_all($args);
        wp_cache_set($cache_key, $organisations, self::CACHE_GROUP, self::CACHE_TTL);

        return $organisations;
    }

    public function get_default(): ?array
    {
        $cache_key = $this->buildCacheKey(['get_default']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $organisation = $this->repository->get_default();
        wp_cache_set($cache_key, $organisation, self::CACHE_GROUP, self::CACHE_TTL);

        return $organisation;
    }

    public function get_all_with_type(array $args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all_with_type', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $organisations = $this->repository->get_all_with_type($args);
        wp_cache_set($cache_key, $organisations, self::CACHE_GROUP, self::CACHE_TTL);

        return $organisations;
    }

    public function count_all(): int
    {
        $cache_key = $this->buildCacheKey(['count_all']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return (int) $cached;
        }

        $count = $this->repository->count_all();
        wp_cache_set($cache_key, $count, self::CACHE_GROUP, self::CACHE_TTL);

        return $count;
    }

    public function has_default(): bool
    {
        $cache_key = $this->buildCacheKey(['has_default']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return (bool) $cached;
        }

        $has_default = $this->repository->has_default();
        wp_cache_set($cache_key, $has_default, self::CACHE_GROUP, self::CACHE_TTL);

        return $has_default;
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

    public function clear_default_except(?int $organisation_id = null): bool
    {
        $result = $this->repository->clear_default_except($organisation_id);
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
