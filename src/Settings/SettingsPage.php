<?php
namespace MYVH\Settings;

class SettingsPage {

    public function init() {

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_myvh_save_settings', [$this, 'save']);

    }


    /**
     * Add admin menu
     */
    public function menu() {

        // Separator (using a disabled submenu as visual separator)
        \MyVillageHall::add_menu_separator();

        add_submenu_page(
            'my-village-hall',
            __('Settings', 'my-village-hall'),
            \MyVillageHall::menu_label('dashicons-admin-generic', __('Settings', 'my-village-hall')),
            'manage_options',
            'myvh-settings',
            [$this, 'render_page']
        );

    }


    /**
     * Render full settings page
     */
    public function render_page() {

        if (!current_user_can('manage_options')) {
            return;
        }

        $groups = SettingsRegistry::groups();
        $visible_groups = $this->filter_accessible_groups($groups);

        if (empty($visible_groups)) {
            echo '<div class="wrap"><p>' . esc_html__('No settings groups are available for your user account.', 'my-village-hall') . '</p></div>';
            return;
        }

        $requested_tab = sanitize_key($_GET['tab'] ?? '');
        $active_tab = isset($visible_groups[$requested_tab])
            ? $requested_tab
            : array_key_first($visible_groups);

        ?>

        <div class="wrap myvh-settings-page">

            <h1><?php esc_html_e('My Village Hall Settings', 'my-village-hall'); ?></h1>
            <p class="myvh-settings-intro"><?php esc_html_e('Configure system behavior, booking defaults, and display preferences for this site.', 'my-village-hall'); ?></p>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'my-village-hall'); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper myvh-settings-tabs">

                <?php foreach ($visible_groups as $key => $group): ?>

                    <a href="?page=myvh-settings&tab=<?php echo esc_attr($key); ?>"
                       class="nav-tab <?php echo ($active_tab === $key) ? 'nav-tab-active' : ''; ?>">

                        <?php echo esc_html($group['label']); ?>

                    </a>

                <?php endforeach; ?>

            </h2>

            <?php $this->render_tab($active_tab); ?>

        </div>

        <?php

    }


    /**
     * Render a single tab
     */
    private function render_tab($key) {

        $settings = SettingsRegistry::get($key);

        if (!$settings) {
            echo '<p>Invalid settings group.</p>';
            return;
        }

        if (!$settings->user_can_access()) {
            echo '<p>' . esc_html__('You do not have permission to access this settings group.', 'my-village-hall') . '</p>';
            return;
        }

        $schema = $settings->schema();
        $values = $settings->all();

        ?>

        <form class="myvh-settings-form" method="post" action="<?php echo admin_url('admin-post.php'); ?>">

            <input type="hidden" name="action" value="myvh_save_settings">
            <input type="hidden" name="group" value="<?php echo esc_attr($key); ?>">

            <?php wp_nonce_field('myvh_settings_nonce'); ?>

            <?php

            foreach ($schema as $section_key => $section) {

                echo '<section class="myvh-settings-section">';

                if (!empty($section['title'])) {
                    echo '<h2 class="myvh-settings-section-title">' . esc_html($section['title']) . '</h2>';
                }

                echo '<table class="form-table myvh-settings-table">';

                if (!empty($section['fields'])) {

                    foreach ($section['fields'] as $field_key => $field) {

                        $value = $values[$field_key] ?? '';

                        echo '<tr>';

                        echo '<th scope="row">';
                        echo esc_html($field['label'] ?? $field_key);
                        echo '</th>';

                        echo '<td>';

                        $this->render_field($field_key, $field, $value);

                        if (!empty($field['description'])) {
                            echo '<p class="description">' . esc_html($field['description']) . '</p>';
                        }

                        echo '</td>';

                        echo '</tr>';

                    }

                }

                echo '</table>';
                echo '</section>';

            }

            echo '<div class="myvh-settings-actions">';
            submit_button(__('Save Settings', 'my-village-hall'));
            echo '</div>';

            ?>

        </form>

        <script>
        document.addEventListener('DOMContentLoaded', function () {

            const SettingsPage = (function () {

                let form, submitBtn;
                let isDirty = false;
                const debugPrefix = '[MYVH Settings]';

                function debugLog() {
                    if (!window.console || typeof window.console.log !== 'function') {
                        return;
                    }
                    const args = Array.prototype.slice.call(arguments);
                    args.unshift(debugPrefix);
                    window.console.log.apply(window.console, args);
                }

                // ─────────────────────────────────────────
                // Dirty state
                // ─────────────────────────────────────────

                function enableSubmitControl() {
                    if (!submitBtn) {
                        return;
                    }

                    submitBtn.disabled = false;
                    submitBtn.removeAttribute('disabled');
                    submitBtn.setAttribute('aria-disabled', 'false');
                    submitBtn.classList.remove('disabled');
                    submitBtn.classList.remove('button-disabled');
                }

                function markDirty(source = 'unknown') {
                    if (!isDirty) {
                        isDirty = true;
                    }
                    enableSubmitControl();
                    debugLog('markDirty called', { source: source, hasSubmitButton: !!submitBtn, submitDisabled: !!(submitBtn && submitBtn.disabled) });
                }

                function resetDirty() {
                    isDirty = false;
                }

                function initDirtyTracking() {
                    if (!form) return;

                    submitBtn = form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn) {
                        debugLog('submit control found', { tag: submitBtn.tagName, type: submitBtn.type || '' });
                    } else {
                        debugLog('submit control not found inside form');
                    }

                    // Generic inputs
                    form.addEventListener('input', function () {
                        markDirty('native:input');
                    }, true);
                    form.addEventListener('change', function () {
                        markDirty('native:change');
                    }, true);

                    form.addEventListener('submit', function () {
                        resetDirty();
                    });

                    window.addEventListener('beforeunload', function (e) {
                        if (isDirty) {
                            e.preventDefault();
                            e.returnValue = '';
                        }
                    });

                    // Expose globally for Flatpickr
                    window.MyvhMarkDirty = markDirty;
                }

                // ─────────────────────────────────────────
                // Flatpickr integration
                // ─────────────────────────────────────────

                function getDatePickerInitOptions() {
                    return {
                        '[data-myvh-picker="date"]': {
                            onChange: [function () {
                                debugLog('flatpickr onChange fired via init options');
                                markDirty('flatpickr:initOptions');
                            }]
                        }
                    };
                }

                function attachFlatpickrHook(input) {
                    if (!input || input.dataset.myvhDirtyHookAttached === '1') {
                        return true;
                    }

                    const instance = input._flatpickr;
                    if (!instance) {
                        debugLog('flatpickr instance missing for input (will retry)', { name: input.name || '', id: input.id || '' });
                        return false;
                    }

                    const onChangeHooks = Array.isArray(instance.config.onChange)
                        ? instance.config.onChange
                        : [];

                    onChangeHooks.push(function () {
                        debugLog('flatpickr onChange fired via attachFlatpickrHook');
                        markDirty('flatpickr:attachHook');
                    });

                    if (typeof instance.set === 'function') {
                        instance.set('onChange', onChangeHooks);
                    }

                    input.dataset.myvhDirtyHookAttached = '1';
                    debugLog('flatpickr hook attached', { name: input.name || '', id: input.id || '' });
                    return true;
                }

                function initDateFields(scope = document, attempts = 0) {
                    let pending = false;

                    scope.querySelectorAll('[data-myvh-picker="date"]').forEach(function (input) {
                        if (!attachFlatpickrHook(input)) {
                            pending = true;
                        }
                    });

                    // Flatpickr instances may be attached just after row render/init.
                    if (pending && attempts < 8) {
                        debugLog('date inputs pending flatpickr instance, scheduling retry', { attempts: attempts + 1 });
                        setTimeout(function () {
                            initDateFields(scope, attempts + 1);
                        }, 50);
                    } else if (pending) {
                        debugLog('date inputs still pending flatpickr instance after retries');
                    }
                }

                // ─────────────────────────────────────────
                // Media fields
                // ─────────────────────────────────────────

                function updateMediaField(field, url) {
                    const preview = field.querySelector('[data-myvh-media-preview]');
                    const clear   = field.querySelector('[data-myvh-media-clear]');

                    if (!preview || !clear) return;

                    if (url) {
                        preview.classList.add('has-image');
                        preview.innerHTML = '<img src="' + url.replace(/"/g, '&quot;') + '" alt="">';
                        clear.style.display = '';
                    } else {
                        preview.classList.remove('has-image');
                        preview.innerHTML = '';
                        clear.style.display = 'none';
                    }
                }

                function initMediaFields() {
                    if (!window.wp || !wp.media) return;

                    form.querySelectorAll('[data-myvh-media-field]').forEach(function (field) {

                        const input  = field.querySelector('[data-myvh-media-input]');
                        const select = field.querySelector('[data-myvh-media-select]');
                        const clear  = field.querySelector('[data-myvh-media-clear]');

                        if (!input || !select || !clear) return;

                        select.addEventListener('click', function () {

                            const frame = wp.media({
                                title: 'Select image',
                                button: { text: 'Use this image' },
                                multiple: false,
                                library: { type: 'image' }
                            });

                            frame.on('select', function () {
                                const attachment = frame.state().get('selection').first().toJSON();
                                const url = attachment.url || '';

                                input.value = url;
                                updateMediaField(field, url);
                                markDirty();
                            });

                            frame.open();
                        });

                        clear.addEventListener('click', function () {
                            input.value = '';
                            updateMediaField(field, '');
                            markDirty();
                        });
                    });
                }

                // ─────────────────────────────────────────
                // Notices repeater
                // ─────────────────────────────────────────

                function initNoticesRepeater() {

                    document.querySelectorAll('.myvh-notices-repeater').forEach(function (wrapper) {

                        const tbody  = wrapper.querySelector('.myvh-notices-body');
                        const addBtn = wrapper.querySelector('.myvh-notice-add-row');

                        if (!tbody || !addBtn) return;

                        const fieldName = addBtn.dataset.field;

                        function rowCount() {
                            return tbody.querySelectorAll('.myvh-notice-row').length;
                        }

                        function reindex() {
                            tbody.querySelectorAll('.myvh-notice-row').forEach(function (row, i) {
                                row.querySelectorAll('[name]').forEach(function (el) {
                                    el.name = el.name.replace(/\[\d+\]/, '[' + i + ']');
                                });
                            });
                        }

                        function buildRow(index) {
                            const tr = document.createElement('tr');
                            tr.className = 'myvh-notice-row';

                            tr.innerHTML = `
                                <td><textarea name="${fieldName}[${index}][message]" rows="2" style="width:100%;" required></textarea></td>
                                <td><input type="text" name="${fieldName}[${index}][start_date]" data-myvh-picker="date" autocomplete="off"></td>
                                <td><input type="text" name="${fieldName}[${index}][end_date]" data-myvh-picker="date" autocomplete="off"></td>
                                <td><button type="button" class="button myvh-notice-remove">Remove</button></td>
                            `;

                            return tr;
                        }

                        // Add row
                        addBtn.addEventListener('click', function () {

                            const row = buildRow(rowCount());
                            tbody.appendChild(row);

                            markDirty();

                            // Init flatpickr + hook
                            if (window.MyvhFlatpickr) {
                                window.MyvhFlatpickr.initWithin(row, getDatePickerInitOptions());
                                debugLog('MyvhFlatpickr.initWithin called for new repeater row');
                            } else {
                                debugLog('MyvhFlatpickr unavailable when adding repeater row');
                            }

                            initDateFields(row);
                        });

                        // Remove row (event delegation)
                        tbody.addEventListener('click', function (e) {
                            if (e.target.classList.contains('myvh-notice-remove')) {
                                e.target.closest('tr').remove();
                                reindex();
                                markDirty();
                            }
                        });

                        // Existing rows
                        initDateFields(wrapper);
                    });
                }

                // ─────────────────────────────────────────
                // Init
                // ─────────────────────────────────────────

                function init() {
                    form = document.querySelector('.myvh-settings-form');
                    if (!form) {
                        debugLog('settings form not found');
                        return;
                    }
                    debugLog('settings page init start');

                    initDirtyTracking();

                    // Ensure date pickers are initialized with a dirty-state callback.
                    if (window.MyvhFlatpickr) {
                        window.MyvhFlatpickr.initWithin(form, getDatePickerInitOptions());
                        debugLog('MyvhFlatpickr.initWithin called for form');
                    } else {
                        debugLog('MyvhFlatpickr unavailable during init');
                    }

                    initMediaFields();
                    initNoticesRepeater();

                    // Initial date hook (after Flatpickr init)
                    setTimeout(function () {
                        debugLog('running deferred initDateFields');
                        initDateFields(form);
                    }, 0);
                }

                return { init };

            })();

            SettingsPage.init();

        });
        </script>
        <?php

    }


    /**
     * Render individual field
     */
    private function render_field( mixed $name, mixed $rule, mixed $value) {

        $type = $rule['type'] ?? 'text';

        switch ($type) {

            case 'boolean':
                ?>
                <label>
                    <input type="checkbox"
                        name="<?php echo esc_attr($name); ?>"
                        value="1"
                        <?php checked($value, true); ?>>
                </label>
                <?php
                break;


            case 'integer':
                ?>
                <input type="number"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    class="small-text">
                <?php
                break;


            case 'textarea':
                ?>
                <textarea
                    name="<?php echo esc_attr($name); ?>"
                    rows="5"
                    class="large-text"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;


            case 'select':

                $options = $rule['options'] ?? [];

                ?>
                <select name="<?php echo esc_attr($name); ?>">

                    <?php foreach ($options as $key => $label): ?>

                        <option value="<?php echo esc_attr($key); ?>"
                            <?php selected($value, $key); ?>>

                            <?php echo esc_html($label); ?>

                        </option>

                    <?php endforeach; ?>

                </select>
                <?php
                break;


            case 'radio':

                $options = $rule['options'] ?? [];

                foreach ($options as $key => $label) {
                    ?>
                    <label style="display:block;margin-bottom:4px;">
                        <input type="radio"
                            name="<?php echo esc_attr($name); ?>"
                            value="<?php echo esc_attr($key); ?>"
                            <?php checked($value, $key); ?>>

                        <?php echo esc_html($label); ?>
                    </label>
                    <?php
                }

                break;


            case 'color':
                ?>
                <input type="color"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>">
                <?php
                break;


            case 'email':
                ?>
                <input type="email"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    class="regular-text">
                <?php
                break;


            case 'url':
                ?>
                <input type="url"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    class="regular-text">
                <?php
                break;


            case 'date':
                ?>
                <input type="text"
                    name="<?php echo esc_attr($name); ?>"
                    data-myvh-picker="date"
                    autocomplete="off"
                    value="<?php echo esc_attr($value); ?>">
                <?php
                break;


            case 'notices':
                $rows = is_array($value) ? $value : [];
                ?>
                <div class="myvh-notices-repeater" id="myvh-notices-repeater-<?php echo esc_attr($name); ?>">

                    <table class="myvh-notices-table widefat striped" style="margin-bottom:10px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Notice Text', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('Start Date', 'my-village-hall'); ?></th>
                                <th><?php esc_html_e('End Date', 'my-village-hall'); ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody class="myvh-notices-body">
                            <?php foreach ($rows as $i => $row): ?>
                            <tr class="myvh-notice-row">
                                <td>
                                    <textarea name="<?php echo esc_attr($name); ?>[<?php echo $i; ?>][message]"
                                              rows="2" style="width:100%;" required><?php echo esc_textarea($row['message'] ?? ''); ?></textarea>
                                </td>
                                <td>
                                    <input type="text"
                                           name="<?php echo esc_attr($name); ?>[<?php echo $i; ?>][start_date]"
                                           value="<?php echo esc_attr($row['start_date'] ?? ''); ?>"
                                           placeholder="<?php esc_attr_e('From now', 'my-village-hall'); ?>"
                                           data-myvh-picker="date"
                                           autocomplete="off"
                                           style="width:100%;">
                                </td>
                                <td>
                                    <input type="text"
                                           name="<?php echo esc_attr($name); ?>[<?php echo $i; ?>][end_date]"
                                           value="<?php echo esc_attr($row['end_date'] ?? ''); ?>"
                                           placeholder="<?php esc_attr_e('Forever', 'my-village-hall'); ?>"
                                           data-myvh-picker="date"
                                           autocomplete="off"
                                           style="width:100%;">
                                </td>
                                <td>
                                    <button type="button" class="button myvh-notice-remove">
                                        <?php esc_html_e('Remove', 'my-village-hall'); ?>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <button type="button" class="button myvh-notice-add-row"
                            data-field="<?php echo esc_attr($name); ?>"
                            data-placeholder-from="<?php esc_attr_e('From now', 'my-village-hall'); ?>"
                            data-placeholder-to="<?php esc_attr_e('Forever', 'my-village-hall'); ?>">
                        <?php esc_html_e('+ Add Notice', 'my-village-hall'); ?>
                    </button>

                </div>
                <?php
                break;


            case 'media':
                $media_url = is_string($value) ? $value : '';
                ?>
                <div class="myvh-media-field" data-myvh-media-field>
                    <input type="hidden"
                        name="<?php echo esc_attr($name); ?>"
                        value="<?php echo esc_attr($media_url); ?>"
                        data-myvh-media-input>

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
                <?php
                break;


            default:
                ?>
                <input type="text"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>"
                    class="regular-text">
                <?php

        }

    }

    private function filter_accessible_groups(array $groups): array {
        $user_id = (int) get_current_user_id();

        return array_filter(
            $groups,
            static function ($group_key) use ($user_id): bool {
                return SettingsRegistry::user_can_access_group($group_key, $user_id);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Save settings
     */
    public function save() {

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('myvh_settings_nonce');

        $group = sanitize_text_field($_POST['group'] ?? '');

        $settings = SettingsRegistry::get($group);

        if (!$settings) {
            wp_die('Invalid settings group');
        }

        if (!$settings->user_can_access()) {
            wp_die('Unauthorized settings group access');
        }

        $settings->save($_POST);

        wp_redirect(
            add_query_arg(
                [
                    'page' => 'myvh-settings',
                    'tab' => $group,
                    'updated' => 'true'
                ],
                admin_url('admin.php')
            )
        );

        exit;

    }

}
