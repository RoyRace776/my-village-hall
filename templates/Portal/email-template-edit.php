<?php
if (!defined('ABSPATH')) exit;

$slug = (string) ($slug ?? '');
$definition = isset($definition) && is_array($definition) ? $definition : [];
$subject = (string) ($subject ?? '');
$html_body = (string) ($html_body ?? '');
$is_customized = !empty($is_customized);
$placeholders = is_array($definition['placeholders'] ?? null) ? $definition['placeholders'] : [];
$label = (string) ($definition['label'] ?? $slug);
$description = (string) ($definition['description'] ?? '');
?>

<div class="myvh-dashboard-section myvh-email-template-edit-page" data-template-slug="<?php echo esc_attr($slug); ?>">
    <div class="myvh-account-header" style="display:flex; align-items:end; justify-content:space-between; gap:24px;">
        <div>
            <h2><?php echo esc_html($label); ?></h2>
            <p><?php echo esc_html($description); ?></p>
        </div>
        <a href="#email-templates" class="button">Back to Templates</a>
    </div>

    <div class="myvh-card myvh-account-card">
        <div class="myvh-account-card-head">
            <h3>Edit Template</h3>
            <span><?php echo $is_customized ? 'Customized' : 'Default'; ?></span>
        </div>

        <form id="myvh-email-template-form" class="myvh-account-form" autocomplete="off">
            <input type="hidden" name="template" value="<?php echo esc_attr($slug); ?>">

            <label class="myvh-account-field">
                <span>Subject</span>
                <input type="text" name="subject" value="<?php echo esc_attr($subject); ?>" required>
            </label>

            <label class="myvh-account-field">
                <span>HTML Body</span>
                <textarea id="myvh-email-template-body" name="html_body" rows="16" required><?php echo esc_textarea($html_body); ?></textarea>
            </label>

            <?php if (!empty($placeholders)): ?>
                <div class="myvh-email-placeholder-panel">
                    <h4>Placeholders</h4>
                    <p class="myvh-muted">Click any token to insert it at the cursor position.</p>
                    <div class="myvh-email-placeholder-list">
                        <?php foreach ($placeholders as $token => $token_help): ?>
                            <button type="button" class="myvh-email-placeholder-pill" data-email-placeholder="{{<?php echo esc_attr((string) $token); ?>}}" title="<?php echo esc_attr((string) $token_help); ?>">
                                {{<?php echo esc_html((string) $token); ?>}}
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="myvh-account-actions">
                <button type="submit" class="button button-primary">Save Template</button>
                <button type="button" class="button" data-email-template-preview="1">Preview</button>
                <button type="button" class="button" data-email-template-send-test="1">Send Test Email</button>
                <button type="button" class="button" data-email-template-reset="1">Reset to Default</button>
                <div id="myvh-email-template-message" class="myvh-muted" aria-live="polite"></div>
            </div>
        </form>
    </div>

    <div id="myvh-email-template-preview-modal" class="myvh-email-preview-modal" hidden>
        <div class="myvh-email-preview-modal__backdrop" data-email-preview-close="1"></div>
        <div class="myvh-email-preview-modal__dialog" role="dialog" aria-modal="true" aria-label="Email preview">
            <div class="myvh-email-preview-modal__head">
                <h3>Email Preview</h3>
                <button type="button" class="button" data-email-preview-close="1">Close</button>
            </div>
            <div class="myvh-email-preview-modal__content" data-email-preview-content></div>
        </div>
    </div>
</div>
