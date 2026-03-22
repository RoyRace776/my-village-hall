<?php
if (!defined('ABSPATH')) exit;

$settings_groups = is_array($settings_groups ?? null) ? $settings_groups : [];
?>

<div class="myvh-dashboard-section myvh-client-settings-page">
    <div class="myvh-account-header">
        <div>
            <h2>Client Settings</h2>
            <p>Manage site-level booking system settings for this client.</p>
        </div>
    </div>

    <?php if (empty($settings_groups)): ?>
        <div class="myvh-card">
            <p>No settings groups are registered.</p>
        </div>
    <?php else: ?>
        <div class="myvh-account-grid">
            <?php foreach ($settings_groups as $group): ?>
                <?php
                $group_key = (string) ($group['key'] ?? '');
                $group_label = (string) ($group['label'] ?? ucfirst($group_key));
                $schema = is_array($group['schema'] ?? null) ? $group['schema'] : [];
                $values = is_array($group['values'] ?? null) ? $group['values'] : [];
                $message_id = 'myvh-settings-message-' . sanitize_html_class($group_key);
                ?>

                <div class="myvh-card myvh-account-card">
                    <div class="myvh-account-card-head">
                        <h3><?php echo esc_html($group_label); ?></h3>
                        <span><?php echo esc_html($group_key); ?> settings</span>
                    </div>

                    <form class="myvh-account-form" data-portal-action="myvh_portal_save_client_settings" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="settings">
                        <input type="hidden" name="settings_group" value="<?php echo esc_attr($group_key); ?>">

                        <?php foreach ($schema as $section): ?>
                            <?php $fields = is_array($section['fields'] ?? null) ? $section['fields'] : []; ?>
                            <?php if (!empty($section['title'])): ?>
                                <h4><?php echo esc_html($section['title']); ?></h4>
                            <?php endif; ?>

                            <?php foreach ($fields as $field_key => $field): ?>
                                <?php
                                $field_type = (string) ($field['type'] ?? 'text');
                                $field_label = (string) ($field['label'] ?? $field_key);
                                $field_value = $values[$field_key] ?? ($field['default'] ?? '');
                                $field_description = (string) ($field['description'] ?? '');
                                ?>

                                <label class="myvh-account-field">
                                    <span><?php echo esc_html($field_label); ?></span>

                                    <?php if ($field_type === 'boolean'): ?>
                                        <span>
                                            <input type="checkbox" name="<?php echo esc_attr($field_key); ?>" value="1" <?php checked(!empty($field_value)); ?>>
                                            Enabled
                                        </span>
                                    <?php elseif ($field_type === 'textarea'): ?>
                                        <textarea name="<?php echo esc_attr($field_key); ?>" rows="3"><?php echo esc_textarea((string) $field_value); ?></textarea>
                                    <?php elseif ($field_type === 'select'): ?>
                                        <select name="<?php echo esc_attr($field_key); ?>">
                                            <?php foreach ((array) ($field['options'] ?? []) as $option_value => $option_label): ?>
                                                <option value="<?php echo esc_attr((string) $option_value); ?>" <?php selected((string) $field_value, (string) $option_value); ?>>
                                                    <?php echo esc_html((string) $option_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    <?php else: ?>
                                        <?php $input_type = in_array($field_type, ['number', 'integer'], true) ? 'number' : 'text'; ?>
                                        <input type="<?php echo esc_attr($input_type); ?>" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr((string) $field_value); ?>">
                                    <?php endif; ?>

                                    <?php if ($field_description !== ''): ?>
                                        <small class="myvh-muted"><?php echo esc_html($field_description); ?></small>
                                    <?php endif; ?>
                                </label>
                            <?php endforeach; ?>
                        <?php endforeach; ?>

                        <div class="myvh-account-actions">
                            <button type="submit" class="button button-primary">Save <?php echo esc_html($group_label); ?> Settings</button>
                            <div id="<?php echo esc_attr($message_id); ?>" class="myvh-muted" aria-live="polite"></div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>