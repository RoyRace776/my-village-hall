<?php
if (!defined('ABSPATH')) exit;
if (!current_user_can('manage_myvh')) wp_die(__('Permission denied', 'my-village-hall'));

global $myvh_container;

use MYVH\Organisations\OrganisationTypeService;
use MYVH\AutoInvoicing\SingleBookingAutoInvoiceRuleRepository;

$type_service = $myvh_container->get(OrganisationTypeService::class);
$rule_repository = $myvh_container->get(SingleBookingAutoInvoiceRuleRepository::class);
$org_types = $type_service->get_all();
$rule_options = $rule_repository->get_rule_options();
$default_type_id = 0;
foreach ($org_types as $type) {
    if (!empty($type['IsDefault'])) {
        $default_type_id = intval($type['Id']);
        break;
    }
}
?>
<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Add Organisation', 'my-village-hall'); ?></h1>
    <a href="<?php echo admin_url('admin.php?page=myvh-organisations'); ?>" class="page-title-action">&larr; <?php _e('Back to Organisations', 'my-village-hall'); ?></a>
    <hr class="wp-header-end">

    <?php if (isset($_GET['error'])): ?>
        <div class="notice notice-error is-dismissible"><p><?php echo esc_html($_GET['error']); ?></p></div>
    <?php endif; ?>

    <div class="myvh-row">
        <div class="myvh-col-60">
            <div class="myvh-card">
                <h2><?php _e('Add Organisation', 'my-village-hall'); ?></h2>

                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="myvh_save_organisation">
                    <?php wp_nonce_field('myvh_save_organisation'); ?>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Name', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="text" name="name" required class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Organisation Type', 'my-village-hall'); ?></th>
                            <td>
                                <select name="organisation_type_id" class="regular-text">
                                    <option value=""><?php _e('— Select Type —', 'my-village-hall'); ?></option>
                                    <?php foreach ($org_types as $type): ?>
                                        <option value="<?php echo $type['Id']; ?>" <?php selected($default_type_id, intval($type['Id'])); ?>><?php echo esc_html($type['Name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <a href="<?php echo admin_url('admin.php?page=myvh-org-types'); ?>">
                                        <?php _e('Manage organisation types', 'my-village-hall'); ?>
                                    </a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Contact Email', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="email" name="contact_email" required class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Contact Phone', 'my-village-hall'); ?> *</th>
                            <td>
                                <input type="tel" name="contact_phone" required class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Website URL', 'my-village-hall'); ?></th>
                            <td>
                                <input type="url" name="website_url" class="regular-text" value="" placeholder="https://example.org">
                                <p class="description"><?php _e('Optional website for this organisation.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Invoice Organisation Bookings', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="invoice_organisation_bookings" value="1" class="myvh-org-invoice-toggle">
                                    <?php _e('Invoice this organisation for its bookings', 'my-village-hall'); ?>
                                </label>
                                <p class="description"><?php _e('Only when enabled will organisation billing details be used and shown.', 'my-village-hall'); ?></p>
                                <p>
                                    <label for="myvh-new-org-auto-invoice-rule"><strong><?php _e('Single booking auto-invoice rule', 'my-village-hall'); ?></strong></label><br>
                                    <select id="myvh-new-org-auto-invoice-rule" name="single_booking_auto_invoice_rule_id" class="regular-text">
                                        <option value="0"><?php _e('Use default rule', 'my-village-hall'); ?></option>
                                        <?php foreach ($rule_options as $rule_id => $rule_name): ?>
                                            <option value="<?php echo intval($rule_id); ?>"><?php echo esc_html($rule_name); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </p>
                            </td>
                        </tr>

                        <tbody class="myvh-org-billing-fields" style="display:none;">
                        <tr>
                            <th colspan="2" style="padding-top: 18px;"><?php _e('Invoicing Details', 'my-village-hall'); ?></th>
                        </tr>

                        <tr>
                            <th><?php _e('Billing Contact Name', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_contact_name" class="regular-text" value="" placeholder="<?php esc_attr_e('Accounts Payable', 'my-village-hall'); ?>">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Billing Email', 'my-village-hall'); ?></th>
                            <td>
                                <input type="email" name="billing_email" class="regular-text" value="" placeholder="billing@example.org">
                                <p class="description"><?php _e('Optional invoice recipient email for this organisation.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Billing Address Line 1', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_address_line1" class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Billing Address Line 2', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_address_line2" class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Town / City', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_town_city" class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Postcode', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_postcode" class="regular-text" value="">
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Billing Reference', 'my-village-hall'); ?></th>
                            <td>
                                <input type="text" name="billing_reference" class="regular-text" value="" placeholder="<?php esc_attr_e('PO number or internal ref', 'my-village-hall'); ?>">
                            </td>
                        </tr>
                        </tbody>

                        <tr>
                            <th><?php _e('Status', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_active" value="1" checked>
                                    <?php _e('Active', 'my-village-hall'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Default Organisation', 'my-village-hall'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="is_default" value="1">
                                    <?php _e('Use as the default organisation', 'my-village-hall'); ?>
                                </label>
                                <p class="description"><?php _e('Only one organisation can be default at a time.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Default Booking Visibility', 'my-village-hall'); ?></th>
                            <td>
                                <select name="default_public" class="regular-text">
                                    <option value="0"><?php _e('Private by default', 'my-village-hall'); ?></option>
                                    <option value="1"><?php _e('Public by default', 'my-village-hall'); ?></option>
                                </select>
                                <p class="description"><?php _e('Used when creating bookings without an explicit visibility choice.', 'my-village-hall'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button class="button button-primary"><?php _e('Add Organisation', 'my-village-hall'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=myvh-organisations'); ?>" class="button"><?php _e('Cancel', 'my-village-hall'); ?></a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>