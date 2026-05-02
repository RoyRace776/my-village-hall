<?php
if (!defined('ABSPATH')) exit;

$settings_groups = (isset($settings_groups) && is_array($settings_groups)) ? $settings_groups : [];
?>

<div class="myvh-dashboard-section myvh-client-settings-page">
    <div class="myvh-account-header myvh-settings-header">
        <div>
            <h2>Client Settings</h2>
            <p>Manage booking defaults, calendar behavior, and customer-facing labels for this client site.</p>
        </div>
    </div>

    <?php if (empty($settings_groups)): ?>
        <div class="myvh-card">
            <p>No settings groups are registered.</p>
        </div>
    <?php else: ?>
        <?php
        $groups_by_key = [];
        $first_group_key = '';

        foreach ($settings_groups as $group) {
            $group_key = (string) ($group['key'] ?? '');

            if ($group_key === '') {
                continue;
            }

            if ($first_group_key === '') {
                $first_group_key = $group_key;
            }

            $groups_by_key[$group_key] = $group;
        }
        ?>

        <?php if (empty($groups_by_key)): ?>
            <div class="myvh-card">
                <p>No valid settings groups are registered.</p>
            </div>
        <?php else: ?>
            <div class="myvh-settings-tabs" role="tablist" aria-label="Settings groups">
                <?php foreach ($groups_by_key as $group_key => $group): ?>
                    <?php
                    $group_label = (string) ($group['label'] ?? ucfirst($group_key));
                    $group_slug = sanitize_html_class(str_replace('_', '-', strtolower($group_key)));
                    $is_active = ($group_key === $first_group_key);
                    ?>
                    <button
                        type="button"
                        class="myvh-settings-tab<?php echo $is_active ? ' is-active' : ''; ?>"
                        role="tab"
                        aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                        data-settings-tab="<?php echo esc_attr($group_key); ?>"
                        data-settings-tab-target="myvh-settings-panel-<?php echo esc_attr($group_slug); ?>"
                    >
                        <?php echo esc_html($group_label); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="myvh-account-grid myvh-settings-groups myvh-settings-panels">
            <?php foreach ($groups_by_key as $group): ?>
                <?php
                $group_key = (string) ($group['key'] ?? '');
                $group_label = (string) ($group['label'] ?? ucfirst($group_key));
                $schema = is_array($group['schema'] ?? null) ? $group['schema'] : [];
                $values = is_array($group['values'] ?? null) ? $group['values'] : [];
                $portal_limited_fields = is_array($group['portal_limited_fields'] ?? null) ? $group['portal_limited_fields'] : [];
                $message_id = 'myvh-settings-message-' . sanitize_html_class($group_key);
                $group_slug = sanitize_html_class(str_replace('_', '-', strtolower($group_key)));
                $is_active = ($group_key === $first_group_key);
                ?>

                <div
                    id="myvh-settings-panel-<?php echo esc_attr($group_slug); ?>"
                    class="myvh-card myvh-account-card myvh-settings-group myvh-settings-group-<?php echo esc_attr($group_slug); ?><?php echo $is_active ? ' is-active' : ''; ?>"
                    role="tabpanel"
                    data-settings-panel="<?php echo esc_attr($group_key); ?>"
                    <?php if (!$is_active): ?>hidden<?php endif; ?>
                >
                    <div class="myvh-account-card-head">
                        <h3><?php echo esc_html($group_label); ?></h3>
                        <span class="myvh-settings-group-key"><?php echo esc_html($group_key); ?> settings</span>
                    </div>

                    <?php if (!empty($portal_limited_fields)): ?>
                        <p class="myvh-muted">Only audit settings are available here for this group.</p>
                    <?php endif; ?>

                    <form class="myvh-account-form myvh-settings-form" data-portal-action="myvh_portal_save_client_settings" data-message-target="<?php echo esc_attr($message_id); ?>" data-reload-page="settings">
                        <input type="hidden" name="settings_group" value="<?php echo esc_attr($group_key); ?>">

                        <?php foreach ($schema as $section): ?>
                            <?php $fields = is_array($section['fields'] ?? null) ? $section['fields'] : []; ?>
                            <section class="myvh-settings-section">
                                <?php if (!empty($section['title'])): ?>
                                    <h4><?php echo esc_html($section['title']); ?></h4>
                                <?php endif; ?>

                                <?php foreach ($fields as $field_key => $field): ?>
                                    <?php
                                    $field_type        = (string) ($field['type'] ?? 'text');
                                    $field_label       = (string) ($field['label'] ?? $field_key);
                                    $field_value       = $values[$field_key] ?? ($field['default'] ?? '');
                                    $field_description = (string) ($field['description'] ?? '');
                                    ?>

                                    <?php if ($field_type === 'notices'): ?>

                                        <?php
                                        $notice_rows = is_array($field_value) ? $field_value : [];
                                        $notice_slug = esc_attr($field_key);
                                        ?>
                                        <div class="myvh-account-field myvh-settings-field myvh-settings-field-notices">
                                            <span class="myvh-settings-field-label"><?php echo esc_html($field_label); ?></span>
                                            <div class="myvh-settings-field-control">
                                                <div class="myvh-notices-repeater" data-notices-repeater
                                                     data-field="<?php echo $notice_slug; ?>"
                                                     data-placeholder-from="From now"
                                                     data-placeholder-to="Forever">

                                                    <table class="myvh-notices-table" style="width:100%;border-collapse:collapse;margin-bottom:8px;">
                                                        <thead>
                                                            <tr>
                                                                <th style="text-align:left;padding:4px 8px;font-weight:600;">Notice Text</th>
                                                                <th style="text-align:left;padding:4px 8px;font-weight:600;width:130px;">Start Date</th>
                                                                <th style="text-align:left;padding:4px 8px;font-weight:600;width:130px;">End Date</th>
                                                                <th style="width:80px;"></th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="myvh-notices-body">
                                                            <?php foreach ($notice_rows as $ni => $nr): ?>
                                                            <tr class="myvh-notice-row">
                                                                <td style="padding:4px 8px;">
                                                                    <textarea name="<?php echo $notice_slug; ?>[<?php echo (int) $ni; ?>][message]"
                                                                              rows="2" style="width:100%;"><?php echo esc_textarea($nr['message'] ?? ''); ?></textarea>
                                                                </td>
                                                                <td style="padding:4px 8px;">
                                                                    <input type="text"
                                                                           name="<?php echo $notice_slug; ?>[<?php echo (int) $ni; ?>][start_date]"
                                                                           value="<?php echo esc_attr($nr['start_date'] ?? ''); ?>"
                                                                           placeholder="From now"
                                                                           data-myvh-picker="date"
                                                                           autocomplete="off"
                                                                           style="width:100%;">
                                                                </td>
                                                                <td style="padding:4px 8px;">
                                                                    <input type="text"
                                                                           name="<?php echo $notice_slug; ?>[<?php echo (int) $ni; ?>][end_date]"
                                                                           value="<?php echo esc_attr($nr['end_date'] ?? ''); ?>"
                                                                           placeholder="Forever"
                                                                           data-myvh-picker="date"
                                                                           autocomplete="off"
                                                                           style="width:100%;">
                                                                </td>
                                                                <td style="padding:4px 8px;">
                                                                    <button type="button" class="myvh-notice-remove" style="cursor:pointer;">x Remove</button>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>

                                                    <button type="button" class="myvh-notice-add-row" style="cursor:pointer;">+ Add Notice</button>
                                                </div>
                                            </div>
                                            <?php if ($field_description !== ''): ?>
                                                <small class="myvh-muted"><?php echo esc_html($field_description); ?></small>
                                            <?php endif; ?>
                                        </div>

                                    <?php else: ?>

                                        <label class="myvh-account-field myvh-settings-field myvh-settings-field-<?php echo esc_attr($field_type); ?>">
                                            <span class="myvh-settings-field-label"><?php echo esc_html($field_label); ?></span>

                                            <span class="myvh-settings-field-control">
                                                <?php if ($field_type === 'boolean'): ?>
                                                    <span class="myvh-settings-checkbox-row">
                                                        <input type="checkbox" name="<?php echo esc_attr($field_key); ?>" value="1" <?php checked(!empty($field_value)); ?>>
                                                        <span>Enabled</span>
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
                                                <?php elseif ($field_type === 'media'): ?>
                                                    <?php $media_url = is_string($field_value) ? $field_value : ''; ?>
                                                    <div class="myvh-media-field" data-myvh-media-field>
                                                        <input type="hidden" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr($media_url); ?>" data-myvh-media-input>

                                                        <div class="myvh-media-preview<?php echo $media_url !== '' ? ' has-image' : ''; ?>" data-myvh-media-preview>
                                                            <?php if ($media_url !== ''): ?>
                                                                <img src="<?php echo esc_url($media_url); ?>" alt="<?php esc_attr_e('Selected logo', 'my-village-hall'); ?>">
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="myvh-media-actions">
                                                            <button type="button" class="button" data-myvh-media-select>
                                                                <?php esc_html_e('Upload/Select Logo', 'my-village-hall'); ?>
                                                            </button>
                                                            <button type="button" class="button" data-myvh-media-clear<?php echo $media_url === '' ? ' style="display:none;"' : ''; ?>>
                                                                <?php esc_html_e('Remove', 'my-village-hall'); ?>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <?php $input_type = in_array($field_type, ['number', 'integer'], true) ? 'number' : 'text'; ?>
                                                    <input type="<?php echo esc_attr($input_type); ?>" name="<?php echo esc_attr($field_key); ?>" value="<?php echo esc_attr((string) $field_value); ?>">
                                                <?php endif; ?>
                                            </span>

                                            <?php if ($field_description !== ''): ?>
                                                <small class="myvh-muted"><?php echo esc_html($field_description); ?></small>
                                            <?php endif; ?>
                                        </label>

                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </section>
                        <?php endforeach; ?>

                        <div class="myvh-account-actions">
                            <button type="submit" class="button button-primary">Save <?php echo esc_html($group_label); ?> Settings</button>
                            <div id="<?php echo esc_attr($message_id); ?>" class="myvh-muted myvh-settings-feedback" aria-live="polite"></div>
                        </div>
                    </form>
                </div>
            <?php endforeach; ?>
            </div>

        <?php endif; ?>
    <?php endif; ?>
</div>