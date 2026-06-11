<?php

namespace MYVH\Settings;

if (!defined('ABSPATH')) {
    exit;
}

class EmailServiceSettings extends SettingsBase {
    protected $option_name = 'myvh_email_service_settings';
    protected $required_capability = '';
    protected $hide_from_client_admin = false;

    private const FIELD_TO_SETTING_KEY = [
        'email_from_address' => 'email.from_address',
        'email_from_name' => 'email.from_name',
        'email_reply_to' => 'email.reply_to',
        'mail_transport' => 'mail.transport',
        'smtp_host' => 'smtp.host',
        'smtp_port' => 'smtp.port',
        'smtp_username' => 'smtp.username',
        'smtp_password' => 'smtp.password',
        'smtp_encryption' => 'smtp.encryption',
        'api_provider' => 'api.provider',
        'api_key' => 'api.key',
        'api_domain' => 'api.domain',
        'api_endpoint' => 'api.endpoint',
        'api_fallback_to_wp_mail' => 'api.fallback_to_wp_mail',
    ];

    protected $schema = [
        'sender' => [
            'title' => 'Email Sender',
            'fields' => [
                'email_from_address' => [
                    'type' => 'text',
                    'label' => 'From Address',
                    'default' => '',
                    'sanitize' => 'sanitize_email',
                    'description' => 'Sender email address used for outgoing mail.',
                ],
                'email_from_name' => [
                    'type' => 'text',
                    'label' => 'From Name',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                'email_reply_to' => [
                    'type' => 'text',
                    'label' => 'Reply-To',
                    'default' => '',
                    'sanitize' => 'sanitize_email',
                ],
            ],
        ],
        'transport' => [
            'title' => 'Transport',
            'fields' => [
                'mail_transport' => [
                    'type' => 'select',
                    'label' => 'Mail Transport',
                    'default' => 'wp_mail',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'wp_mail' => 'WordPress (wp_mail)',
                        'smtp' => 'SMTP',
                        'api' => 'API Provider',
                    ],
                ],
            ],
        ],
        'smtp' => [
            'title' => 'SMTP',
            'fields' => [
                'smtp_host' => [
                    'type' => 'text',
                    'label' => 'SMTP Host',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                'smtp_port' => [
                    'type' => 'number',
                    'label' => 'SMTP Port',
                    'default' => '587',
                    'sanitize' => 'absint',
                ],
                'smtp_username' => [
                    'type' => 'text',
                    'label' => 'SMTP Username',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                'smtp_password' => [
                    'type' => 'text',
                    'label' => 'SMTP Password',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                'smtp_encryption' => [
                    'type' => 'select',
                    'label' => 'SMTP Encryption',
                    'default' => 'tls',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'tls' => 'TLS',
                        'ssl' => 'SSL',
                    ],
                ],
            ],
        ],
        'api' => [
            'title' => 'API Provider',
            'fields' => [
                'api_provider' => [
                    'type' => 'select',
                    'label' => 'Provider',
                    'default' => 'mailgun',
                    'sanitize' => 'sanitize_key',
                    'options' => [
                        'mailgun' => 'Mailgun',
                        'sendgrid' => 'SendGrid',
                        'custom_api' => 'Custom API Endpoint',
                    ],
                ],
                'api_key' => [
                    'type' => 'text',
                    'label' => 'API Key',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                ],
                'api_domain' => [
                    'type' => 'text',
                    'label' => 'API Domain',
                    'default' => '',
                    'sanitize' => 'sanitize_text_field',
                    'description' => 'Required for Mailgun.',
                ],
                'api_endpoint' => [
                    'type' => 'text',
                    'label' => 'Custom API Endpoint',
                    'default' => '',
                    'sanitize' => 'esc_url_raw',
                    'description' => 'Used when provider is Custom API Endpoint.',
                ],
                'api_fallback_to_wp_mail' => [
                    'type' => 'boolean',
                    'label' => 'Fallback to wp_mail on API failure',
                    'default' => true,
                    'sanitize' => 'boolval',
                ],
            ],
        ],
    ];

    public function tab(): array {
        return [
            'key' => 'email-service',
            'label' => 'Email Service',
        ];
    }
}
