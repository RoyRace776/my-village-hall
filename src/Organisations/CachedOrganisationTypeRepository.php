<?php

namespace MYVH\Organisations;

class CachedOrganisationTypeRepository extends OrganisationTypeRepository
{
    private const CACHE_GROUP = 'myvh_organisation_types';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_organisation_types_';
    private const VERSION_KEY = 'version';

    private OrganisationTypeRepository $repository;

    public function __construct(OrganisationTypeRepository $repository)
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

        $type = $this->repository->get_by_id($id);
        wp_cache_set($cache_key, $type, self::CACHE_GROUP, self::CACHE_TTL);

        return $type;
    }

    public function get_all($args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $types = $this->repository->get_all($args);
        wp_cache_set($cache_key, $types, self::CACHE_GROUP, self::CACHE_TTL);

        return $types;
    }

    public function get_by_name(string $name): ?array
    {
        $cache_key = $this->buildCacheKey(['get_by_name', $name]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $type = $this->repository->get_by_name($name);
        wp_cache_set($cache_key, $type, self::CACHE_GROUP, self::CACHE_TTL);

        return $type;
    }

    public function get_default(): ?array
    {
        $cache_key = $this->buildCacheKey(['get_default']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $type = $this->repository->get_default();
        wp_cache_set($cache_key, $type, self::CACHE_GROUP, self::CACHE_TTL);

        return $type;
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

    public function update( mixed $data, mixed $where): bool
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

    public function clear_default_except(?int $type_id = null): bool
    {
        $result = $this->repository->clear_default_except($type_id);
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

    public function __call( mixed $name, mixed $arguments)
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
