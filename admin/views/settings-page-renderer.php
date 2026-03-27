<?php

class SettingsPageRenderer {

    private $settings;
    private $title;
    private $nonce;

    public function __construct($settings, $title, $nonce) {

        $this->settings = $settings;
        $this->title = $title;
        $this->nonce = $nonce;

    }

    public function render() {

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            check_admin_referer($this->nonce);

            $this->settings->save($_POST);

            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }

        $schema = $this->settings->schema();
        $values = $this->settings->all();

        ?>

        <div class="wrap">

        <h1><?php echo esc_html($this->title); ?></h1>

        <form method="post">

        <?php wp_nonce_field($this->nonce); ?>

        <table class="form-table">

        <?php foreach ($schema as $key => $field): ?>

        <tr>

        <th scope="row">
            <?php echo esc_html($field['label']); ?>
        </th>

        <td>

        <?php
        $value = $values[$key] ?? '';
        $type = $field['type'] ?? 'string';
        ?>

        <?php if ($type === 'integer'): ?>

            <input type="number"
                   name="<?php echo esc_attr($key); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   class="regular-text">

        <?php elseif ($type === 'boolean'): ?>

            <label>
                <input type="checkbox"
                       name="<?php echo esc_attr($key); ?>"
                       value="1"
                       <?php checked($value, true); ?>>
                <?php echo esc_html($field['description'] ?? ''); ?>
            </label>

        <?php elseif ($type === 'select'): ?>

            <select name="<?php echo esc_attr($key); ?>">

            <?php foreach ($field['options'] as $option => $label): ?>

                <option value="<?php echo esc_attr($option); ?>"
                        <?php selected($value, $option); ?>>

                    <?php echo esc_html($label); ?>

                </option>

            <?php endforeach; ?>

            </select>

        <?php else: ?>

            <input type="text"
                   name="<?php echo esc_attr($key); ?>"
                   value="<?php echo esc_attr($value); ?>"
                   class="regular-text">

        <?php endif; ?>

        <?php if (!empty($field['description']) && $type !== 'boolean'): ?>

        <p class="description">
            <?php echo esc_html($field['description']); ?>
        </p>

        <?php endif; ?>

        </td>

        </tr>

        <?php endforeach; ?>

        </table>

        <?php submit_button(); ?>

        </form>

        </div>

        <?php
    }

}