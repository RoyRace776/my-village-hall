<?php
namespace MYVH\Settings;

use MYVH\Email\EmailTemplateRegistry;

class EmailTemplateSettings extends SettingsBase {

    protected $option_name = 'myvh_email_templates';

    public function get_template(string $slug): array {
        if (!EmailTemplateRegistry::has($slug)) {
            return [
                'slug' => $slug,
                'subject' => '',
                'html_body' => '',
                'is_customized' => false,
            ];
        }

        $all = $this->all_templates();
        $saved = is_array($all[$slug] ?? null) ? $all[$slug] : [];

        $subject = isset($saved['subject']) ? (string) $saved['subject'] : EmailTemplateRegistry::default_subject($slug, '');
        $html_body = isset($saved['html_body']) ? (string) $saved['html_body'] : EmailTemplateRegistry::default_html($slug);
        $is_customized = !empty($saved['is_customized']);

        return [
            'slug' => $slug,
            'subject' => $subject,
            'html_body' => $html_body,
            'is_customized' => (bool) $is_customized,
        ];
    }

    public function all_templates(): array {
        $saved = $this->get_option([]);

        if (!is_array($saved)) {
            return [];
        }

        return $saved;
    }

    public function get_all(): array {
        $templates = [];

        foreach (EmailTemplateRegistry::all() as $slug => $definition) {
            $templates[$slug] = array_merge($definition, $this->get_template((string) $slug));
        }

        return $templates;
    }

    public function save_template(string $slug, string $subject, string $html_body): void {
        if (!EmailTemplateRegistry::has($slug)) {
            return;
        }

        $saved = $this->all_templates();
        $saved[$slug] = [
            'subject' => $subject,
            'html_body' => $html_body,
            'is_customized' => true,
            'updated_at' => current_time('mysql'),
            'updated_by' => (int) get_current_user_id(),
        ];

        $this->update_option($saved);
        $this->settings = null;
    }

    public function reset_template(string $slug): void {
        if (!EmailTemplateRegistry::has($slug)) {
            return;
        }

        $saved = $this->all_templates();
        unset($saved[$slug]);

        $this->update_option($saved);
        $this->settings = null;
    }
}
