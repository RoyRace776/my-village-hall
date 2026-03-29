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
            __('Settings', 'my-village-hall'),
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

        $active_tab = $_GET['tab'] ?? array_key_first($groups);

        ?>

        <div class="wrap myvh-settings-page">

            <h1><?php esc_html_e('My Village Hall Settings', 'my-village-hall'); ?></h1>
            <p class="myvh-settings-intro"><?php esc_html_e('Configure system behavior, booking defaults, and display preferences for this site.', 'my-village-hall'); ?></p>

            <?php if (!empty($_GET['updated'])): ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e('Settings saved.', 'my-village-hall'); ?></p></div>
            <?php endif; ?>

            <h2 class="nav-tab-wrapper myvh-settings-tabs">

                <?php foreach ($groups as $key => $group): ?>

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
        (function () {
            var form    = document.querySelector('.myvh-settings-page .myvh-settings-form');
            var btn     = document.getElementById('submit');
            var isDirty = false;

            if (!form || !btn) return;

            btn.disabled = true;

            function markDirty() {
                if (!isDirty) {
                    isDirty = true;
                    btn.disabled = false;
                }
            }

            form.addEventListener('change', markDirty);
            form.addEventListener('input',  markDirty);

            form.addEventListener('submit', function () {
                isDirty = false;
            });

            window.addEventListener('beforeunload', function (e) {
                if (isDirty) {
                    e.preventDefault();
                    e.returnValue = '';
                }
            });
        })();
        </script>

        <?php

    }


    /**
     * Render individual field
     */
    private function render_field($name, $rule, $value) {

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
                <input type="date"
                    name="<?php echo esc_attr($name); ?>"
                    value="<?php echo esc_attr($value); ?>">
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