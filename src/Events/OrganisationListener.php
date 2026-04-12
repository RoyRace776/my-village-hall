<?php
namespace MYVH\Events;

use MYVH\Email\EmailService;
use MYVH\Email\EmailTemplateRegistry;
use MYVH\Portal\ClientAdminService;

class OrganisationListener {
    private EmailService $email_service;
    private ClientAdminService $client_admin_service;

    public function __construct($email_service = null, $client_admin_service = null) {
        $this->email_service = $email_service ?: new EmailService();
        $this->client_admin_service = $client_admin_service ?: new ClientAdminService();
    }

    public function register(): void {
        add_action('myvh_event_' . OrganisationEvents::CREATED, [$this, 'handle_organisation_created']);
    }

    public function handle_organisation_created($payload): void {
        $payload = is_array($payload) ? $payload : [];

        $organisation_id = (int) ($payload['organisation_id'] ?? 0);
        $organisation_name = (string) ($payload['organisation_name'] ?? '');
        $contact_email = (string) ($payload['contact_email'] ?? '');
        $contact_phone = (string) ($payload['contact_phone'] ?? '');
        $created_by_user_id = (int) ($payload['created_by_user_id'] ?? 0);

        if ($organisation_id <= 0 || $organisation_name === '') {
            return;
        }

        $created_by_name = '';
        $created_by_email = '';
        if ($created_by_user_id > 0) {
            $user = get_userdata($created_by_user_id);
            if ($user) {
                $created_by_name = (string) ($user->display_name ?? $user->user_login ?? '');
                $created_by_email = (string) ($user->user_email ?? '');
            }
        }

        $branding = $this->email_service->get_branding();

        $template_vars = array_merge($branding, [
            'organisation_name' => $organisation_name,
            'organisation_id' => $organisation_id,
            'contact_email' => $contact_email,
            'contact_phone' => $contact_phone,
            'created_by_name' => $created_by_name,
            'created_by_email' => $created_by_email,
        ]);

        $subject_template = EmailTemplateRegistry::default_subject('organisation-created', 'New organisation created');
        $subject = strtr(
            $subject_template,
            EmailTemplateRegistry::replacement_map(
                'organisation-created',
                array_merge($this->email_service->get_branding(), $template_vars)
            )
        );
        $recipients = $this->get_client_admin_emails((int) get_current_blog_id());

        foreach ($recipients as $recipient) {
            $this->email_service->send([
                'to' => $recipient,
                'subject' => $subject,
                'template' => 'organisation-created',
                'template_vars' => $template_vars,
            ]);
        }
    }

    private function get_client_admin_emails(int $blog_id): array {
        $users = get_users([
            'blog_id' => $blog_id,
        ]);

        $emails = [];

        foreach ($users as $user) {
            $user_id = (int) ($user->ID ?? 0);
            $email = (string) ($user->user_email ?? '');

            if ($user_id <= 0 || $email === '') {
                continue;
            }

            if (!$this->client_admin_service->can_administer_blog($user_id, $blog_id)) {
                continue;
            }

            if (!is_email($email)) {
                continue;
            }

            $emails[] = $email;
        }

        $emails = array_values(array_unique($emails));

        if (empty($emails)) {
            $fallback = (string) get_option('admin_email');
            if ($fallback !== '' && is_email($fallback)) {
                return [$fallback];
            }
        }

        return $emails;
    }
}
