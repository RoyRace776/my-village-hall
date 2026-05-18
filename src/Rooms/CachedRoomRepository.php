<?php

namespace MYVH\Rooms;

class CachedRoomRepository extends RoomRepository
{
    private const CACHE_GROUP = 'myvh_rooms';
    private const CACHE_TTL = 300;
    private const CACHE_KEY_PREFIX = 'myvh_rooms_';
    private const VERSION_KEY = 'version';

    private RoomRepository $repository;

    public function __construct(RoomRepository $repository)
    {
        $this->repository = $repository;
        $this->wpdb = $repository->wpdb;
        $this->table_name = $repository->table_name;
    }

    /**
     * @param int|string $id
     * @return array<string, mixed>|null
     */
    public function get_by_id($id): ?array
    {
        $cache_key = $this->buildCacheKey(['get_by_id', $id]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $room = $this->repository->get_by_id($id);
        wp_cache_set($cache_key, $room, self::CACHE_GROUP, self::CACHE_TTL);

        return $room;
    }

    /**
     * @param array{orderby?: string, order?: string, limit?: int|string, offset?: int|string} $args
     * @return array<int, array<string, mixed>>
     */
    public function get_all($args = []): array
    {
        $cache_key = $this->buildCacheKey(['get_all', $args]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rooms = $this->repository->get_all($args);
        wp_cache_set($cache_key, $rooms, self::CACHE_GROUP, self::CACHE_TTL);

        return $rooms;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_all_with_venues(): array
    {
        $cache_key = $this->buildCacheKey(['get_all_with_venues']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rooms = $this->repository->get_all_with_venues();
        wp_cache_set($cache_key, $rooms, self::CACHE_GROUP, self::CACHE_TTL);

        return $rooms;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function get_public_with_venues(): array
    {
        $cache_key = $this->buildCacheKey(['get_public_with_venues']);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rooms = $this->repository->get_public_with_venues();
        wp_cache_set($cache_key, $rooms, self::CACHE_GROUP, self::CACHE_TTL);

        return $rooms;
    }

    /**
     * @param int $venue_id
     * @param bool $public_only
     * @return array<int, array<string, mixed>>
     */
    public function get_by_venue(int $venue_id, bool $public_only = false): array
    {
        $cache_key = $this->buildCacheKey(['get_by_venue', $venue_id, (bool) $public_only]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $rooms = $this->repository->get_by_venue($venue_id, $public_only);
        wp_cache_set($cache_key, $rooms, self::CACHE_GROUP, self::CACHE_TTL);

        return $rooms;
    }

    /**
     * @param array<int, int|string> $room_ids
     * @return array<int, int|string>
     */
    public function get_public_room_ids(array $room_ids = []): array
    {
        $cache_key = $this->buildCacheKey(['get_public_room_ids', $room_ids]);
        $cached = wp_cache_get($cache_key, self::CACHE_GROUP);

        if ($cached !== false) {
            return $cached;
        }

        $public_room_ids = $this->repository->get_public_room_ids($room_ids);
        wp_cache_set($cache_key, $public_room_ids, self::CACHE_GROUP, self::CACHE_TTL);

        return $public_room_ids;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create($data): int|false
    {
        $result = $this->repository->create($data);
        $this->incrementCacheVersion();

        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where
     */
    public function update( mixed $data, mixed $where): bool
    {
        $result = $this->repository->update($data, $where);
        $this->incrementCacheVersion();

        return $result;
    }

    /**
     * @param int|string $id
     */
    public function delete($id): bool
    {
        $result = $this->repository->delete($id);
        $this->incrementCacheVersion();

        return $result;
    }

    /**
     * @param int|string $id
     */
    public function delete_by_id($id): bool
    {
        $result = $this->repository->delete_by_id($id);
        $this->incrementCacheVersion();

        return $result;
    }

    /**
     * @param int|string $id
     */
    public function soft_delete_by_id($id): bool
    {
        $result = $this->repository->soft_delete_by_id($id);
        $this->incrementCacheVersion();

        return $result;
    }

    /**
     * @param int|string $id
     */
    public function restore_by_id($id): bool
    {
        $result = $this->repository->restore_by_id($id);
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

    public function last_error(): string
    {
        return $this->repository->last_error();
    }

    /**
     * @param string $name
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    public function __call( mixed $name, mixed $arguments): mixed
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
