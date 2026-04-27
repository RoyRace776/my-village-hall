<?php
namespace MYVH\Network;

if (!defined('ABSPATH')) {
    exit;
}

class NsClonerAdapter {
    public function clone_site(int $source_site_id, int $target_site_id, array $context = []): true|\WP_Error {
        if ($source_site_id <= 0 || $target_site_id <= 0) {
            return new \WP_Error('myvh_ns_cloner_invalid_ids', __('Source and target site IDs are required for cloning.', 'my-village-hall'));
        }

        $payload = [
            'source_site_id' => $source_site_id,
            'target_site_id' => $target_site_id,
            'context' => $context,
        ];

        $custom_result = apply_filters('myvh_network_ns_cloner_execute', null, $payload);
        if ($custom_result instanceof \WP_Error) {
            return $custom_result;
        }

        if ($custom_result === true) {
            return true;
        }

        if (has_action('ns_cloner_perform_clone')) {
            do_action('ns_cloner_perform_clone', $payload);
            return true;
        }

        if (has_action('ns_cloner')) {
            do_action('ns_cloner', $payload);
            return true;
        }

        return new \WP_Error(
            'myvh_ns_cloner_unavailable',
            __('NS Cloner integration is not available. Add a hook handler for myvh_network_ns_cloner_execute or install/enable NS Cloner integration hooks.', 'my-village-hall')
        );
    }
}
