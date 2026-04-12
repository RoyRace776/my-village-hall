<?php
if (!defined('ABSPATH')) exit;

$templates = is_array($templates ?? null) ? $templates : [];
?>

<div class="myvh-dashboard-section myvh-email-templates-page">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2>Email Templates</h2>
            <p>Customize transactional email wording and format with placeholders.</p>
        </div>
    </div>

    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>Available Templates</h3>
            <span><?php echo count($templates); ?> email templates</span>
        </div>

        <?php if (empty($templates)): ?>
            <p>No email templates are registered.</p>
        <?php else: ?>
            <table class="myvh-customer-list-table">
                <thead>
                    <tr>
                        <th style="padding-right:24px;">Template</th>
                        <th style="padding-right:24px;">Description</th>
                        <th style="padding-right:24px;">Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($templates as $slug => $template): ?>
                    <?php
                    $label = (string) ($template['label'] ?? $slug);
                    $description = (string) ($template['description'] ?? '');
                    $is_customized = !empty($template['is_customized']);
                    $message_id = 'myvh-email-template-message-' . sanitize_html_class((string) $slug);
                    ?>
                    <tr>
                        <td style="padding-right:24px;"><strong><?php echo esc_html($label); ?></strong><br><small style="color:#7a7166;"><?php echo esc_html($slug); ?></small></td>
                        <td style="padding-right:24px;"><?php echo esc_html($description); ?></td>
                        <td style="padding-right:24px;">
                            <span class="myvh-email-template-status <?php echo $is_customized ? 'is-customized' : 'is-default'; ?>">
                                <?php echo $is_customized ? 'Customized' : 'Default'; ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <a href="#email-template-edit?slug=<?php echo rawurlencode((string) $slug); ?>" class="myvh-action-icon" aria-label="Edit template" title="Edit template" style="margin-right:10px;">✎</a>

                            <?php if ($is_customized): ?>
                                <form class="myvh-inline-form" style="display:inline;" data-email-template-reset="1" data-template-slug="<?php echo esc_attr((string) $slug); ?>" data-message-target="<?php echo esc_attr($message_id); ?>" data-confirm="Reset this template to default?">
                                    <button type="submit" class="myvh-action-icon myvh-action-danger" aria-label="Reset template" title="Reset template" style="background:none; border:none; padding:0; margin:0; cursor:pointer;">↺</button>
                                </form>
                            <?php endif; ?>

                            <div id="<?php echo esc_attr($message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
