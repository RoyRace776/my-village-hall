<?php
if (!defined('ABSPATH')) exit;

class MYVH_Customer_Group_Service {

    private $repo;

    public function __construct($repo) {
        $this->repo = $repo;
    }

    public function get_all($active_only = true) {
        return $this->repo->get_all($active_only);
    }

    public function get($id) {
        return $this->repo->get_by_id($id);
    }

    public function save($data) {

        if (empty($data['name'])) {
            return new WP_Error('validation', __('Group name is required', 'my-village-hall'));
        }

        $record = [
            'Name'        => sanitize_text_field($data['name']),
            'Description' => sanitize_textarea_field($data['description']),
            'IsActive'    => isset($data['is_active']) ? 1 : 0,
        ];

        if (!empty($data['group_id'])) {
            return $this->repo->update($record, ['Id' => intval($data['group_id'])]);
        }

        return $this->repo->create($record);
    }

    public function delete($id) {
        return $this->repo->delete($id);
    }
}