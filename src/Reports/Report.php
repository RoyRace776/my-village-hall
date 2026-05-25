<?php

declare(strict_types=1);

namespace MYVH\Reports;

final class Report {
    public const TYPE_SYSTEM = 'system';
    public const TYPE_USER = 'user';

    public function __construct(
        private int $id,
        private string $name,
        private string $description,
        private string $type,
        private string $data_source,
        private string $query_json,
        private ?int $created_by,
        private string $created_at
    ) {
    }

    public function get_id(): int {
        return $this->id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_description(): string {
        return $this->description;
    }

    public function get_type(): string {
        return $this->type;
    }

    public function get_data_source(): string {
        return $this->data_source;
    }

    public function get_query_json(): string {
        return $this->query_json;
    }

    public function get_created_by(): ?int {
        return $this->created_by;
    }

    public function get_created_at(): string {
        return $this->created_at;
    }

    public function is_system(): bool {
        return $this->type === self::TYPE_SYSTEM;
    }

    /**
     * @return array<string, int|string|null>
     */
    public function to_array(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'type' => $this->type,
            'data_source' => $this->data_source,
            'query_json' => $this->query_json,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
        ];
    }
}
