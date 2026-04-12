<?php
namespace MYVH\Email;

class EmailTemplateRegistry {
    public static function all(): array {
        return [
            'password-setup' => [
                'label' => 'Password Setup',
                'description' => 'Sent when a customer account is created and they need to set a password.',
                'default_subject' => 'Set up your password',
                'placeholders' => [
                    'customer_name' => 'Customer full name.',
                    'reset_url' => 'One-time password setup link.',
                    'site_name' => 'Current site name.',
                    'site_url' => 'Current site URL.',
                    'logo_url' => 'Current site icon URL.',
                ],
                'default_html' => '<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;"><tr><td style="padding:28px 32px 12px 32px;text-align:center;">{{logo_html}}<h2 style="margin:0 0 8px 0;color:#222;">Set up your password</h2><p style="margin:0 0 20px 0;color:#444;">Hello {{customer_name}}, your account has been created on {{site_name}}.</p><p style="margin:0 0 24px 0;"><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Set up password</a></p><p style="color:#888;font-size:13px;margin:0;">This link expires in 1 hour.</p></td></tr><tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">&copy; {{year}} {{site_name}}</td></tr></table>',
                'sample_vars' => [
                    'customer_name' => 'Alex Morgan',
                    'reset_url' => 'https://example.com/login/?myvh_reset=1&uid=42&token=abc123',
                ],
            ],
            'password-reset' => [
                'label' => 'Password Reset',
                'description' => 'Sent when a customer requests a password reset.',
                'default_subject' => 'Reset your password',
                'placeholders' => [
                    'customer_name' => 'Customer full name.',
                    'reset_url' => 'One-time password reset link.',
                    'site_name' => 'Current site name.',
                    'site_url' => 'Current site URL.',
                    'logo_url' => 'Current site icon URL.',
                ],
                'default_html' => '<table style="width:100%;max-width:560px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;"><tr><td style="padding:28px 32px 12px 32px;text-align:center;">{{logo_html}}<h2 style="margin:0 0 8px 0;color:#222;">Reset your password</h2><p style="margin:0 0 20px 0;color:#444;">Hi {{customer_name}}, we received a request to reset your {{site_name}} password.</p><p style="margin:0 0 24px 0;"><a href="{{reset_url}}" style="display:inline-block;padding:12px 24px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;font-weight:bold;">Reset password</a></p><p style="color:#888;font-size:13px;margin:0;">If you did not request this, you can ignore this email.</p></td></tr><tr><td style="padding:0 32px 24px 32px;text-align:center;color:#bbb;font-size:12px;">&copy; {{year}} {{site_name}}</td></tr></table>',
                'sample_vars' => [
                    'customer_name' => 'Alex Morgan',
                    'reset_url' => 'https://example.com/login/?myvh_reset=1&uid=42&token=def456',
                ],
            ],
            'booking-confirmed' => [
                'label' => 'Booking Confirmed',
                'description' => 'Sent when a booking is confirmed.',
                'default_subject' => 'Booking confirmed: {{booking_ref}}',
                'placeholders' => [
                    'customer_name' => 'Customer full name.',
                    'customer_address' => 'Customer address (single line).',
                    'booking_ref' => 'Booking reference/ID.',
                    'booking_description' => 'Booking description.',
                    'booking_date' => 'Booking date.',
                    'booking_time' => 'Booking start/end time.',
                    'venue_name' => 'Venue name.',
                    'room_name' => 'Room name.',
                    'booking_amount' => 'Total booking amount.',
                    'site_name' => 'Current site name.',
                    'site_url' => 'Current site URL.',
                    'logo_url' => 'Current site icon URL.',
                ],
                'default_html' => '<table style="width:100%;max-width:620px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;"><tr><td style="padding:28px 32px 12px 32px;">{{logo_html}}<h2 style="margin:0 0 8px 0;color:#222;">Booking confirmed</h2><p style="margin:0 0 16px 0;color:#444;">Hi {{customer_name}}, your booking is confirmed.</p><p style="margin:0 0 10px 0;"><strong>Reference:</strong> {{booking_ref}}<br><strong>Date:</strong> {{booking_date}}<br><strong>Time:</strong> {{booking_time}}<br><strong>Venue/Room:</strong> {{venue_name}} / {{room_name}}<br><strong>Amount:</strong> {{booking_amount}}</p><p style="margin:16px 0 0 0;color:#666;">{{booking_description}}</p></td></tr><tr><td style="padding:0 32px 24px 32px;color:#999;font-size:12px;">{{site_name}} • {{site_url}}</td></tr></table>',
                'sample_vars' => [
                    'customer_name' => 'Alex Morgan',
                    'customer_address' => '1 High Street, Exampletown',
                    'booking_ref' => '#BK-1042',
                    'booking_description' => 'Weekly badminton session.',
                    'booking_date' => '15 Apr 2026',
                    'booking_time' => '18:00 - 20:00',
                    'venue_name' => 'Village Hall',
                    'room_name' => 'Main Hall',
                    'booking_amount' => 'GBP 52.00',
                ],
            ],
            'invoice' => [
                'label' => 'Invoice Notification',
                'description' => 'Sent when an invoice is created or shared with the customer.',
                'default_subject' => 'Invoice {{invoice_ref}} from {{site_name}}',
                'placeholders' => [
                    'customer_name' => 'Customer full name.',
                    'customer_address' => 'Customer address (single line).',
                    'invoice_ref' => 'Invoice reference/number.',
                    'invoice_total' => 'Invoice total amount.',
                    'invoice_due_date' => 'Invoice payment due date.',
                    'invoice_status' => 'Current invoice status.',
                    'invoice_url' => 'Direct link to invoice view.',
                    'organisation_name' => 'Organisation linked to the invoice.',
                    'booking_details' => 'Summary of the invoiced booking(s).',
                    'site_name' => 'Current site name.',
                    'site_url' => 'Current site URL.',
                    'logo_url' => 'Current site icon URL.',
                ],
                'default_html' => '<table style="width:100%;max-width:620px;margin:auto;font-family:sans-serif;background:#ffffff;border-radius:8px;box-shadow:0 2px 8px #0001;"><tr><td style="padding:28px 32px 12px 32px;">{{logo_html}}<h2 style="margin:0 0 8px 0;color:#222;">Invoice {{invoice_ref}}</h2><p style="margin:0 0 14px 0;color:#444;">Hi {{customer_name}}, your invoice is now available.</p><p style="margin:0 0 10px 0;"><strong>Total:</strong> {{invoice_total}}<br><strong>Due date:</strong> {{invoice_due_date}}<br><strong>Status:</strong> {{invoice_status}}<br><strong>Organisation:</strong> {{organisation_name}}</p><p style="margin:16px 0 16px 0;color:#666;">{{booking_details}}</p><p style="margin:0;"><a href="{{invoice_url}}" style="display:inline-block;padding:10px 20px;background:#2a7ae2;color:#fff;text-decoration:none;border-radius:4px;">View invoice</a></p></td></tr><tr><td style="padding:0 32px 24px 32px;color:#999;font-size:12px;">{{site_name}} • {{site_url}}</td></tr></table>',
                'sample_vars' => [
                    'customer_name' => 'Alex Morgan',
                    'customer_address' => '1 High Street, Exampletown',
                    'invoice_ref' => 'INV-2026-041',
                    'invoice_total' => 'GBP 180.00',
                    'invoice_due_date' => '30 Apr 2026',
                    'invoice_status' => 'Unpaid',
                    'invoice_url' => 'https://example.com/portal/#invoice-view?id=41',
                    'organisation_name' => 'Example FC',
                    'booking_details' => 'Includes 3 bookings in April 2026.',
                ],
            ],
        ];
    }

    public static function get(string $slug): array {
        $all = self::all();
        return $all[$slug] ?? [];
    }

    public static function has(string $slug): bool {
        return isset(self::all()[$slug]);
    }

    public static function default_subject(string $slug, string $fallback = ''): string {
        $template = self::get($slug);
        return (string) ($template['default_subject'] ?? $fallback);
    }

    public static function default_html(string $slug): string {
        $template = self::get($slug);
        return (string) ($template['default_html'] ?? '');
    }

    public static function sample_vars(string $slug, array $branding = []): array {
        $template = self::get($slug);
        $sample = is_array($template['sample_vars'] ?? null) ? $template['sample_vars'] : [];

        return array_merge([
            'site_name' => $branding['site_name'] ?? get_bloginfo('name'),
            'site_url' => $branding['site_url'] ?? home_url('/'),
            'logo_url' => $branding['logo_url'] ?? get_site_icon_url(128),
            'year' => date('Y'),
        ], $sample);
    }

    public static function replacement_map(string $slug, array $vars): array {
        $template = self::get($slug);
        $placeholder_defs = is_array($template['placeholders'] ?? null) ? $template['placeholders'] : [];

        $map = [];

        foreach ($placeholder_defs as $token => $_label) {
            $map['{{' . $token . '}}'] = '';
        }

        foreach ($vars as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }

            $map['{{' . $key . '}}'] = (string) ($value ?? '');
        }

        $logo_url = (string) ($vars['logo_url'] ?? '');
        $site_name = (string) ($vars['site_name'] ?? get_bloginfo('name'));
        $map['{{year}}'] = (string) date('Y');
        $map['{{logo_html}}'] = $logo_url !== ''
            ? '<p style="margin:0 0 16px 0;"><img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-width:120px;"></p>'
            : '';

        return $map;
    }
}
