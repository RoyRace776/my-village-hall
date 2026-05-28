<?php
namespace MYVH\Settings;

class BookingSettings extends SettingsBase {

    protected $option_name = 'myvh_booking_settings';
    protected $hide_from_client_admin = false;

    public function tab(): array {
        return
            ['key' => 'booking',
            'label' => 'Booking'];
    }

    protected $schema = [

        'general' => [
            'title' => 'General Booking Rules',

            'fields' => [

                'max_booking_days' => [
                    'label' => 'Maximum booking days ahead',
                    'type' => 'number',
                    'default' => 365,
                    'sanitize' => 'intval',
                    'description' => 'How far ahead users can book.'
                ],

                'min_notice_hours' => [
                    'label' => 'Minimum notice',
                    'type' => 'number',
                    'default' => 24,
                    'sanitize' => 'intval'
                ],

                'require_approval' => [
                    'label' => 'Require approval',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval',
                    'description' => 'If enabled, new bookings will require admin approval before they are confirmed.'
                ],

                'cancellation_allowed_days_before' => [
                    'label' => 'Cancellations allowed up to (days before booking start)',
                    'type' => 'number',
                    'default' => 7,
                    'sanitize' => 'intval',
                    'description' => 'How many days before the booking start time users are allowed to cancel their booking.'
                ],

                'booking_terms_text' => [
                    'label' => 'Booking terms and conditions text',
                    'type' => 'textarea',
                    'default' => '',
                    'sanitize' => [self::class, 'sanitize_booking_terms_text'],
                    'description' => 'Shown on booking forms with a required checkbox when set. HTML links are allowed, for example: <a href="https://example.com/terms.pdf">Read terms</a>.'
                ],

            ]
        ],

        'buffers' => [
            'title' => 'Buffers',

            'fields' => [

                'set_up_minutes' => [
                    'label' => 'Minutes allowed for set up before a booking',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'How many free minutes are allowed for set up before a booking.'
                ],
                'tidy_up_minutes' => [
                    'label' => 'Minutes allowed for tidy up after a booking',
                    'type' => 'number',
                    'default' => 0,
                    'sanitize' => 'intval',
                    'description' => 'How many free minutes are allowed for tidy up after a booking.'
                ],
                'show_buffer_times_separately' => [
                    'label' => 'Show buffer times in calendar as separate events',
                    'type' => 'boolean',
                    'default' => true,
                    'sanitize' => 'boolval'
                ],
                'buffer_text' => [
                    'label' => 'Buffer event text',
                    'type' => 'text',
                    'default' => 'Buffer',
                    'sanitize' => 'sanitize_text_field'
                ]
            ]
        ]

    ];

    public static function sanitize_booking_terms_text(mixed $value): string {
        return wp_kses((string) $value, self::booking_terms_allowed_html());
    }

    public static function render_booking_terms_text(string $value): string {
        $sanitized = self::sanitize_booking_terms_text($value);

        if ($sanitized === '') {
            return '';
        }

        $with_target = preg_replace_callback('/<a\b[^>]*>/i', static function (array $matches): string {
            $tag = $matches[0];
            $tag = preg_replace('/\s+target\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $tag);
            $tag = preg_replace('/\s+rel\s*=\s*("[^"]*"|\'[^\']*\')/i', '', $tag);

            return rtrim($tag, '>') . ' target="_blank" rel="noopener noreferrer">';
        }, $sanitized);

        return wp_kses((string) $with_target, self::booking_terms_allowed_html());
    }

    private static function booking_terms_allowed_html(): array {
        return [
            'a' => [
                'href' => true,
                'target' => true,
                'rel' => true,
                'title' => true,
            ],
            'p' => [],
            'br' => [],
            'strong' => [],
            'em' => [],
            'ul' => [],
            'ol' => [],
            'li' => [],
            'span' => [],
        ];
    }

}