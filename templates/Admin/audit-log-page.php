<?php

use MYVH\Audit\AuditTrail;

if (!defined('ABSPATH')) {
    exit;
}

if (!AuditTrail::can_view_dashboard()) {
    wp_die(
        esc_html__('You do not have sufficient permissions to access this page.', 'my-village-hall'),
        esc_html__('Access Denied', 'my-village-hall'),
        ['response' => 403]
    );
}

$page = max(1, intval($_GET['paged'] ?? 1));
$action_filter = sanitize_key($_GET['action_filter'] ?? '');
$entity_filter = sanitize_key($_GET['entity_filter'] ?? '');
$origin_filter = sanitize_key($_GET['origin_filter'] ?? '');
$user_filter = max(0, intval($_GET['user_filter'] ?? 0));

$result = AuditTrail::query([
    'page' => $page,
    'per_page' => 50,
    'action' => $action_filter,
    'entity_type' => $entity_filter,
    'origin' => $origin_filter,
    'actor_user_id' => $user_filter,
]);

$rows = $result['rows'] ?? [];
$total_pages = max(1, intval($result['total_pages'] ?? 1));
$current_page = max(1, intval($result['page'] ?? 1));

$entity_options = [
    '' => __('All entities', 'my-village-hall'),
    'booking' => __('Bookings', 'my-village-hall'),
    'customer' => __('Customers', 'my-village-hall'),
    'organisation' => __('Organisations', 'my-village-hall'),
    'organisation_type' => __('Organisation Types', 'my-village-hall'),
    'venue' => __('Venues', 'my-village-hall'),
    'room' => __('Rooms', 'my-village-hall'),
    'room_rate' => __('Room Rates', 'my-village-hall'),
    'addon' => __('Add-ons', 'my-village-hall'),
    'invoice' => __('Invoices', 'my-village-hall'),
    'payment' => __('Payments', 'my-village-hall'),
];

$action_options = [
    '' => __('All actions', 'my-village-hall'),
    'create' => __('Create', 'my-village-hall'),
    'delete' => __('Delete', 'my-village-hall'),
];

$origin_options = [
    '' => __('All origins', 'my-village-hall'),
    'dashboard' => __('Dashboard', 'my-village-hall'),
    'portal' => __('Portal', 'my-village-hall'),
    'ajax' => __('AJAX', 'my-village-hall'),
    'system' => __('System', 'my-village-hall'),
];
?>

<div class="wrap">
    <h1><?php esc_html_e('Audit Log', 'my-village-hall'); ?></h1>

    <?php if (!AuditTrail::is_enabled()): ?>
        <div class="notice notice-warning"><p><?php esc_html_e('Auditing is currently disabled in Settings > Admin.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <?php if (!empty($_GET['reset'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Audit log has been reset.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>

    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" style="margin: 12px 0; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <input type="hidden" name="page" value="myvh-audit-log">

        <select name="action_filter">
            <?php foreach ($action_options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($action_filter, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="entity_filter">
            <?php foreach ($entity_options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($entity_filter, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>

        <select name="origin_filter">
            <?php foreach ($origin_options as $key => $label): ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($origin_filter, $key); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>

        <input type="number" name="user_filter" min="0" step="1" value="<?php echo esc_attr((string) $user_filter); ?>" placeholder="<?php esc_attr_e('User ID', 'my-village-hall'); ?>">

        <button type="submit" class="button button-secondary"><?php esc_html_e('Filter', 'my-village-hall'); ?></button>
    </form>

    <?php if (AuditTrail::is_enabled()): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Reset the entire audit log?', 'my-village-hall')); ?>');" style="margin-bottom: 16px;">
            <?php wp_nonce_field('myvh_reset_audit_log'); ?>
            <input type="hidden" name="action" value="myvh_reset_audit_log">
            <button type="submit" class="button button-link-delete"><?php esc_html_e('Reset Audit Log', 'my-village-hall'); ?></button>
        </form>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php esc_html_e('When', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Action', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Entity', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Entity ID', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Actor', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Origin', 'my-village-hall'); ?></th>
                <th><?php esc_html_e('Summary', 'my-village-hall'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($rows)): ?>
                <tr><td colspan="7"><?php esc_html_e('No audit entries found.', 'my-village-hall'); ?></td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php
                    $actor_user_id = intval($row['ActorUserId'] ?? 0);
                    $actor_display = $actor_user_id > 0 ? '#' . $actor_user_id : __('System', 'my-village-hall');
                    $summary = !empty($row['SummaryArray']) ? wp_json_encode($row['SummaryArray']) : '';
                    ?>
                    <tr>
                        <td><?php echo esc_html((string) ($row['CreatedAt'] ?? '')); ?></td>
                        <td><?php echo esc_html(ucfirst((string) ($row['Action'] ?? ''))); ?></td>
                        <td><?php echo esc_html((string) ($row['EntityType'] ?? '')); ?></td>
                        <td><?php echo esc_html((string) ($row['EntityId'] ?? '')); ?></td>
                        <td><?php echo esc_html($actor_display); ?></td>
                        <td><?php echo esc_html((string) ($row['Origin'] ?? '')); ?></td>
                        <td><?php echo esc_html($summary); ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ($total_pages > 1): ?>
        <div class="tablenav" style="margin-top: 12px;">
            <div class="tablenav-pages">
                <?php
                echo paginate_links([
                    'base' => add_query_arg([
                        'page' => 'myvh-audit-log',
                        'action_filter' => $action_filter,
                        'entity_filter' => $entity_filter,
                        'origin_filter' => $origin_filter,
                        'user_filter' => $user_filter,
                        'paged' => '%#%',
                    ], admin_url('admin.php')),
                    'format' => '',
                    'current' => $current_page,
                    'total' => $total_pages,
                    'type' => 'plain',
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>
</div>
