<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

global $myvh_container;
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;

$org_id      = isset($_GET['organisation_id']) ? intval($_GET['organisation_id']) : 0;
$org_service = $myvh_container->get(OrganisationService::class);
$customer_service = $myvh_container->get(CustomerService::class);

if (!$org_id) {
    // Show a list of all organisations to pick from
    $orgs = $org_service->get_all_with_type();
    ?>
    <div class="wrap">
        <h1><?php _e('Organisation Members', 'my-village-hall'); ?></h1>
        <hr class="wp-header-end">
        <div class="myvh-card">
            <p><?php _e('Select an organisation to manage its members:', 'my-village-hall'); ?></p>
            <table class="wp-list-table widefat striped">
                <thead>
                    <tr>
                        <th><?php _e('Organisation', 'my-village-hall'); ?></th>
                        <th><?php _e('Type', 'my-village-hall'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orgs as $org): ?>
                        <tr>
                            <td>
                                <a href="<?php echo admin_url('admin.php?page=myvh-org-members&organisation_id=' . $org['Id']); ?>">
                                    <strong><?php echo esc_html($org['Name']); ?></strong>
                                </a>
                            </td>
                            <td><?php echo esc_html($org['OrganisationTypeName'] ?? '—'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    return;
}

$organisation = $org_service->get($org_id);
if (!$organisation) {
    wp_die(__('Organisation not found.', 'my-village-hall'));
}

$members  = $org_service->get_members($org_id);

$customers = $customer_service->get_all();

// Build a set of already-added user IDs so we can exclude them from the add dropdown
$existing_user_ids = array_column($members, 'CustomerId');
$available_users   = array_filter($customers, fn($u) => !in_array($u['Id'], $existing_user_ids));
?>

<div class="wrap">
    <h1 class="wp-heading-inline">
        <?php printf(__('Members: %s', 'my-village-hall'), esc_html($organisation['Name'])); ?>
    </h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-organisations'); ?>" class="page-title-action">
        ← <?php _e('All Organisations', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Member added.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Member removed.', 'my-village-hall'); ?></p></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($_GET['error']); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">

        <!-- ── Current members ─────────────────────────────────────────────── -->
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('Current Members', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Email', 'my-village-hall'); ?></th>
                            <th><?php _e('Added', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($members)): ?>
                            <tr><td colspan="4"><?php _e('No members yet.', 'my-village-hall'); ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($members as $member): ?>
                                <tr>
                                    <td><strong><?php echo esc_html($member['Name'] ?? __('Unknown user', 'my-village-hall')); ?></strong></td>
                                    <td><?php echo esc_html($member['Email'] ?? ''); ?></td>
                                    <td><?php echo $member['Created'] ? date('d M Y', strtotime($member['Created'])) : '—'; ?></td>
                                    <td>
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_remove_org_member&id=' . $member['Id'] . '&organisation_id=' . $org_id),
                                            'myvh_remove_org_member'
                                        ); ?>" class="link-delete"
                                           onclick="return confirm('<?php _e('Remove this member?', 'my-village-hall'); ?>');">
                                            <?php _e('Remove', 'my-village-hall'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Add member form ─────────────────────────────────────────────── -->
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php _e('Add Member', 'my-village-hall'); ?></h2>

                <?php if (empty($available_users)): ?>
                    <p><?php _e('All users are already members of this organisation.', 'my-village-hall'); ?></p>
                <?php else: ?>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="myvh_add_org_member">
                        <input type="hidden" name="organisation_id" value="<?php echo $org_id; ?>">
                        <?php wp_nonce_field('myvh_add_org_member'); ?>

                        <table class="form-table">
                            <tr>
                                <th><?php _e('User', 'my-village-hall'); ?> *</th>
                                <td>
                                    <select name="user_id" required class="regular-text">
                                        <option value=""><?php _e('— Select User —', 'my-village-hall'); ?></option>
                                        <?php foreach ($available_users as $user): ?>
                                            <option value="<?php echo $user['Id']; ?>">
                                                <?php echo esc_html($user['Name'] . ' (' . $user['Email'] . ')'); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                        </table>

                        <p class="submit">
                            <button class="button button-primary"><?php _e('Add Member', 'my-village-hall'); ?></button>
                        </p>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>
