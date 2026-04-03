<?php
namespace MYVH\Organisations;

use WP_Error;

if (!defined('ABSPATH')) exit;

class OrganisationTypeService {
    private $repo;
    public function __construct(OrganisationTypeRepository $repo) {
        $this->repo = $repo;
    }
    public function get_all(): array {
        return $this->repo->get_all();
    }
    public function get(int $id): ?array {
        return $this->repo->get_by_id($id);
    }
    public function save(array $data): int|bool|WP_Error {
        if (empty($data['name'])) {
            return new WP_Error('validation', __('Organisation type name is required', 'my-village-hall'));
        }

        $requested_default = !empty($data['is_default']) ? 1 : 0;

        if (!empty($data['org_type_id'])) {
            $existing = $this->repo->get_by_id(intval($data['org_type_id']));
            if (empty($existing['Id'])) {
                return new WP_Error('not_found', __('Organisation type not found', 'my-village-hall'));
            }

            if (!empty($existing['IsSystem'])) {
                return new WP_Error('forbidden', __('System organisation types cannot be edited', 'my-village-hall'));
            }

            $updated = $this->repo->update([
                'Name'        => sanitize_text_field($data['name']),
                'Description' => sanitize_textarea_field($data['description'] ?? ''),
                'IsDefault'   => $requested_default,
            ], ['Id' => intval($data['org_type_id'])]);

            if (!$updated) {
                return false;
            }

            if ($requested_default) {
                $this->repo->clear_default_except(intval($data['org_type_id']));
            } elseif (!$this->repo->has_default()) {
                $this->repo->update(['IsDefault' => 1], ['Id' => intval($data['org_type_id'])]);
            }

            return true;
        }

        $record = [
            'Name'        => sanitize_text_field($data['name']),
            'Description' => sanitize_textarea_field($data['description'] ?? ''),
            'IsDefault'   => $requested_default,
        ];

        $created_id = $this->repo->create($record);
        if (!$created_id) {
            return false;
        }

        if ($requested_default) {
            $this->repo->clear_default_except(intval($created_id));
        } elseif (!$this->repo->has_default()) {
            $this->repo->update(['IsDefault' => 1], ['Id' => intval($created_id)]);
        }

        return $created_id;
    }
    public function delete(int $id): bool|WP_Error {
        $existing = $this->repo->get_by_id($id);
        if (empty($existing['Id'])) {
            return new WP_Error('not_found', __('Organisation type not found', 'my-village-hall'));
        }

        if (!empty($existing['IsSystem'])) {
            return new WP_Error('forbidden', __('System organisation types cannot be deleted', 'my-village-hall'));
        }

        if (!empty($existing['IsDefault'])) {
            return new WP_Error('forbidden', __('Assign another default organisation type before deleting this one', 'my-village-hall'));
        }

        if ($this->repo->count_organisations_using_type($id) > 0) {
            return new WP_Error('in_use', __('Organisation type is in use and cannot be deleted', 'my-village-hall'));
        }

        return $this->repo->delete($id);
    }
}
