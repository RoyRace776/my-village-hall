<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

use MYVH\Organisations\OrganisationService;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;

global $myvh_container;
$org_service = $myvh_container->get(OrganisationService::class);
$rule_repository = $myvh_container->get(SingleBookingAutoInvoiceRuleRepository::class);
$default_org = $org_service->get_default();
$rule_options = $rule_repository->get_rule_options();
$edit_customer = null;
$customer_orgs = [];
$available_orgs = [];
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Add Customer', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="page-title-action">&larr; <?php _e('Back to Customers', 'my-village-hall'); ?></a>
    <hr class="wp-header-end">
    <div class="myvh-row">
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('Add Customer', 'my-village-hall'); ?></h2>
                <?php if (!empty($default_org['Name'])): ?>
                    <div class="notice notice-info inline">
                        <p>
                            <?php printf(
                                __('New customers will automatically be added to the default organisation: %s', 'my-village-hall'),
                                '<strong>' . esc_html($default_org['Name']) . '</strong>'
                            ); ?>
                        </p>
                    </div>
                <?php endif; ?>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_customer">
                    <?php wp_nonce_field('myvh_save_customer'); ?>
                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text" value="">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Email', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="email" name="email" required class="regular-text" value="">
                            </td>
                        </tr>
                        <tr>
                            <th><?php _e('Phone', 'my-village-hall'); ?></th>
                            <td>
                                <input type="tel" name="phone" class="regular-text" value="">
                            </td>
                        </tr>
                        <?php if (current_user_can('manage_myvh_client_admin')): ?>
                        <tr>
                            <th><?php _e('Options', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="allow_auto_confirm" value="1">
                                    <?php _e('Allow Auto Confirm', 'my-village-hall'); ?>
                                </label>
                                <p style="margin-top:10px;">
                                    <label for="myvh-new-customer-auto-invoice-rule"><strong><?php _e('Single booking auto-invoice rule', 'my-village-hall'); ?></strong></label><br>
                                    <select id="myvh-new-customer-auto-invoice-rule" name="single_booking_auto_invoice_rule_id" class="regular-text">
                                        <option value="0"><?php _e('Use default rule', 'my-village-hall'); ?></option>
                                        <?php foreach ($rule_options as $rule_id => $rule_name): ?>
                                            <option value="<?php echo intval($rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </table>
                    <p class="submit">
                        <button class="button button-primary"><?php _e('Add Customer', 'my-village-hall'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=myvh-customers'); ?>" class="button">Cancel</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
