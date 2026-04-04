<?php
if (!defined('ABSPATH')) {
    exit;
}

if (!current_user_can('manage_myvh')) {
    wp_die(__('Permission denied', 'my-village-hall'));
}
//TODO: Update to make password mandatory when creating or editing customer
use MYVH\Customers\CustomerService;
use MYVH\Organisations\OrganisationService;

global $myvh_container;

$edit_id          = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
$customer_service = $myvh_container->get(CustomerService::class);
$org_service      = $myvh_container->get(OrganisationService::class);
$default_org      = $org_service->get_default();
$edit_customer    = $edit_id ? $customer_service->get($edit_id) : null;
$customers        = $customer_service->get_with_organisations();

// For edit mode: current memberships and organisations still available to join
$customer_orgs  = [];
$available_orgs = [];
if ($edit_customer) {
    $customer_orgs  = $customer_service->get_organisations_for_customer($edit_id);
    $all_orgs       = $org_service->get_all(true);
    $joined_org_ids = array_column($customer_orgs, 'Id');
    $available_orgs = array_filter($all_orgs, fn($o) => !in_array($o['Id'], $joined_org_ids));
}
?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Customers', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-customer-add'); ?>" class="page-title-action">
        <?php _e('Add New', 'my-village-hall'); ?>
    </a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['updated'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Customer saved successfully.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <div class="notice notice-success is-dismissible">
            <p><?php _e('Customer deleted successfully.', 'my-village-hall'); ?></p>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible">
            <p><?php echo esc_html($_GET['error']); ?></p>
        </div>
    <?php endif; ?>

    <div class="myvh-row">

        <!-- ── Customer list ─────────────────────────────────────────────────── -->
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('All Customers', 'my-village-hall'); ?></h2>

                <table class="wp-list-table widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?></th>
                            <th><?php _e('Email', 'my-village-hall'); ?></th>
                            <th><?php _e('Phone', 'my-village-hall'); ?></th>
                            <th><?php _e('Organisations', 'my-village-hall'); ?></th>
                            <th><?php _e('Actions', 'my-village-hall'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($customers)): ?>
                            <tr>
                                <td colspan="5"><?php _e('No customers found.', 'my-village-hall'); ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($customers as $customer): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($customer['Name']); ?></strong>
                                        <?php if ($customer['EmailVerified']): ?>
                                            <span class="dashicons dashicons-yes-alt" style="color:#46b450;"
                                                title="<?php _e('Email verified', 'my-village-hall'); ?>"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html($customer['Email']); ?></td>
                                    <td><?php echo esc_html($customer['PhoneNumber'] ?? '—'); ?></td>
                                    <td>
                                        <?php if (!empty($customer['OrganisationNames'])): ?>
                                            <?php echo esc_html($customer['OrganisationNames']); ?>
                                        <?php else: ?>
                                            <span style="color:#999;"><?php _e('None', 'my-village-hall'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo admin_url('admin.php?page=myvh-customers&edit=' . $customer['Id']); ?>">
                                            <?php _e('Edit', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="#" class="send-password-reset" data-customer-id="<?php echo intval($customer['Id']); ?>">
                                            <?php _e('Send Password Reset', 'my-village-hall'); ?>
                                        </a> |
                                        <a href="<?php echo wp_nonce_url(
                                            admin_url('admin-post.php?action=myvh_delete_customer&id=' . $customer['Id']),
                                            'myvh_delete_customer'
                                        ); ?>" class="link-delete"
                                           onclick="return confirm('<?php _e('Are you sure you want to delete this customer?', 'my-village-hall'); ?>');">
                                            <?php _e('Delete', 'my-village-hall'); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ── Edit panel only (no add) ─────────────────────────────────────── -->
        <?php if ($edit_customer): ?>
        <div class="myvh-col-40">
            <div class="myvh-card">
                <h2><?php _e('Edit Customer', 'my-village-hall'); ?></h2>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_customer">
                    <?php wp_nonce_field('myvh_save_customer'); ?>
                    <input type="hidden" name="customer_id" value="<?php echo $edit_customer['Id']; ?>">
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text"
                                    value="<?php echo esc_attr($edit_customer['Name']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="email" name="email" required class="regular-text"
                                    value="<?php echo esc_attr($edit_customer['Email']); ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Phone Number', 'my-village-hall'); ?></th>
                            <td>
                                <input type="tel" name="phone_number" class="regular-text"
                                    value="<?php echo $edit_customer ? esc_attr($edit_customer['PhoneNumber'] ?? '') : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Post Code', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="post_code" class="regular-text"
                                    value="<?php echo $edit_customer ? esc_attr($edit_customer['PostCode'] ?? '') : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Address', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="address_line1" class="large-text"
                                    value="<?php echo $edit_customer ? esc_attr($edit_customer['AddressLine1'] ?? '') : ''; ?>">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_verified" value="1"
                                        <?php checked($edit_customer && $edit_customer['EmailVerified']); ?>>
                                    <?php _e('Email verified', 'my-village-hall'); ?>
                                </label>
                                <?php if (current_user_can('manage_myvh_client_admin')): ?>
                                <br>
                                <label>
                                    <input type="checkbox" name="allow_auto_confirm" value="1" <?php checked($edit_customer && !empty($edit_customer['AllowAutoConfirm'])); ?>>
                                    <?php _e('Allow Auto Confirm', 'my-village-hall'); ?>
                                </label>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary">
                            <?php echo $edit_customer
                                ? __('Update Customer', 'my-village-hall')
                                : __('Add Customer', 'my-village-hall'); ?>
                        </button>
                        <?php if ($edit_customer): ?>
                            <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button">
                                <?php _e('Cancel', 'my-village-hall'); ?>
                            </a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>

            <?php if ($edit_customer): ?>

            <!-- Organisation memberships panel -->
            <div class="myvh-card" style="margin-top:16px;">
                <h3><?php _e('Organisations', 'my-village-hall'); ?></h3>

                <?php if (!$edit_customer['Id']): ?>
                    <!-- No WP user linked — membership not possible yet -->
                    <p class="description">
                        <?php _e('Organisation membership is linked via a WordPress user account. This customer has no WP user attached, so memberships cannot be managed here.', 'my-village-hall'); ?>
                    </p>

                <?php else: ?>

                    <!-- Current memberships table -->
                    <?php if (!empty($customer_orgs)): ?>
                    <table class="widefat striped" style="margin-bottom:14px;">
                        <thead>
                            <tr>
                                <th><?php _e('Organisation', 'my-village-hall'); ?></th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($customer_orgs as $org):
                                // Resolve the member-row Id so we can remove it
                                $member_row = null;
                                foreach ($org_service->get_members($org['Id']) as $m) {
                                    if ((int) $m['CustomerId'] === (int) $edit_customer['Id']) {
                                        $member_row = $m;
                                        break;
                                    }
                                }
                            ?>
                                <tr>
                                    <td><?php echo esc_html($org['Name']); ?></td>
                                    <td>
                                        <?php if ($member_row): ?>
                                            <a href="<?php echo wp_nonce_url(
                                                admin_url(
                                                    'admin-post.php?action=myvh_remove_org_member'
                                                    . '&id='          . $member_row['Id']
                                                    . '&redirect=customer'
                                                    . '&customer_id=' . $edit_customer['Id']
                                                ),
                                                'myvh_remove_org_member'
                                            ); ?>" class="link-delete"
                                               onclick="return confirm('<?php _e('Remove from this organisation?', 'my-village-hall'); ?>');"
                                               style="white-space:nowrap;">
                                                <?php _e('Remove', 'my-village-hall'); ?>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                        <p style="color:#999; margin-bottom:12px;">
                            <?php _e('Not a member of any organisation.', 'my-village-hall'); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Add to an organisation -->
                    <?php if (!empty($available_orgs)): ?>
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action"      value="myvh_add_org_member">
                        <input type="hidden" name="user_id"     value="<?php echo intval($edit_customer['Id']); ?>">
                        <input type="hidden" name="redirect"    value="customer">
                        <input type="hidden" name="customer_id" value="<?php echo $edit_customer['Id']; ?>">
                        <?php wp_nonce_field('myvh_add_org_member'); ?>

                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <select name="organisation_id" required>
                                <option value=""><?php _e('— Add to organisation —', 'my-village-hall'); ?></option>
                                <?php foreach ($available_orgs as $org): ?>
                                    <option value="<?php echo $org['Id']; ?>">
                                        <?php echo esc_html($org['Name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button"><?php _e('Add', 'my-village-hall'); ?></button>
                        </div>
                    </form>
                    <?php else: ?>
                        <p class="description">
                            <?php _e('Customer is already a member of all active organisations.', 'my-village-hall'); ?>
                        </p>
                    <?php endif; ?>

                <?php endif; // has CustomerId ?>

            </div>

            <?php endif; // edit_customer ?>

        </div>
        <?php endif; // add or edit ?>

    </div>
</div>
