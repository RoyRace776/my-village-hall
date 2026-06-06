<?php

declare(strict_types=1);

namespace MYVH\Application\Services;

use MYVH\Network\NetworkProvisioningSettings;
use MYVH\Network\SiteProvisioningService;

final class CreateSiteRenderer {
    private const DRAFT_COOKIE = 'myvh_setup_draft_token';
    private const DRAFT_PREFIX = 'myvh_setup_draft_';

    public function __construct(private SiteProvisioningService $service) {
    }

    public function render( array $attributes = [] ): string {
        $plugin_url = defined( 'MYVH_PLUGIN_URL' ) ? (string) constant( 'MYVH_PLUGIN_URL' ) : '';
        $plugin_version = defined( 'MYVH_VERSION' ) ? (string) constant( 'MYVH_VERSION' ) : null;

        wp_enqueue_style(
            'myvh-network-create-site',
            $plugin_url . 'assets/css/network-create-site.css',
            [],
            $plugin_version
        );
        wp_enqueue_script(
            'myvh-network-create-site',
            $plugin_url . 'assets/js/network-create-site.js',
            [],
            $plugin_version,
            true
        );

        $state = [
            'status' => '',
            'message' => '',
            'site_url' => '',
        ];
        $submitted = false;
        $verification_result = false;
        $verification_details = [];
        $verification_action = 'verify';
        $draft_token = $this->ensure_draft_token();
        $saved_draft = null;
        $setup_draft = [];

        if ( ! is_multisite() ) {
            return '<div class="myvh-site-request myvh-site-request--error">' . esc_html__( 'This shortcode requires WordPress multisite.', 'my-village-hall' ) . '</div>';
        }

        if ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['myvh_save_setup_draft'] ) ) {
            $this->handle_draft_save( $draft_token );
        }

        if ( ! empty( $_GET['myvh_site_cancel'] ) && ! empty( $_GET['token'] ) ) {
            $token = sanitize_text_field( wp_unslash( (string) $_GET['token'] ) );
            $result = $this->service->cancel_request( $token );
            $state['status'] = ! empty( $result['ok'] ) ? 'success' : 'error';
            $state['message'] = (string) ( $result['message'] ?? '' );
            $verification_result = true;
            $verification_details = is_array( $result['details'] ?? null ) ? $result['details'] : [];
            $verification_action = 'cancel';
        } elseif ( ! empty( $_GET['myvh_site_verify'] ) && ! empty( $_GET['token'] ) ) {
            $token = sanitize_text_field( wp_unslash( (string) $_GET['token'] ) );
            $result = $this->service->verify_and_provision( $token );
            $state['status'] = ! empty( $result['ok'] ) ? 'success' : 'error';
            $state['message'] = (string) ( $result['message'] ?? '' );
            $state['site_url'] = (string) ( $result['site_url'] ?? '' );
            $verification_result = true;
            $verification_details = is_array( $result['details'] ?? null ) ? $result['details'] : [];
            $verification_action = 'verify';
        } elseif ( $_SERVER['REQUEST_METHOD'] === 'POST' && ! empty( $_POST['myvh_create_site_action'] ) ) {
            if ( ! isset( $_POST['myvh_create_site_nonce'] ) || ! wp_verify_nonce( (string) $_POST['myvh_create_site_nonce'], 'myvh_create_site_request' ) ) {
                $state['status'] = 'error';
                $state['message'] = __( 'Invalid request nonce.', 'my-village-hall' );
            } else {
                $submit_payload = $_POST;
                $logo_upload = $this->handle_logo_upload( $_FILES['logo'] ?? null );

                if ( is_wp_error( $logo_upload ) ) {
                    $state['status'] = 'error';
                    $state['message'] = (string) $logo_upload->get_error_message();
                    $submitted = false;
                } else {
                    $submit_payload['logo_url'] = $logo_upload;

                    $result = $this->service->submit( $submit_payload );
                    $state['status'] = ! empty( $result['ok'] ) ? 'success' : 'error';
                    $state['message'] = (string) ( $result['message'] ?? '' );
                    $submitted = ! empty( $result['ok'] );
                }

                if ( $submitted ) {
                    delete_transient( self::DRAFT_PREFIX . $draft_token );
                }
            }
        }

        if ( ! $submitted && ! empty( $draft_token ) ) {
            $saved_draft = get_transient( self::DRAFT_PREFIX . $draft_token );
            if ( is_array( $saved_draft ) ) {
                $setup_draft = is_array( $saved_draft['setup_payload'] ?? null ) ? $saved_draft['setup_payload'] : [];
            }
        }

        $form_values = [
            'site_name' => sanitize_text_field( $_POST['site_name'] ?? '' ),
            'subdomain' => sanitize_title( $_POST['subdomain'] ?? '' ),
            'admin_email' => sanitize_email( $_POST['admin_email'] ?? '' ),
            'admin_first_name' => sanitize_text_field( $_POST['admin_first_name'] ?? '' ),
            'admin_last_name' => sanitize_text_field( $_POST['admin_last_name'] ?? '' ),
        ];

        if ( $_SERVER['REQUEST_METHOD'] !== 'POST' && ! empty( $saved_draft ) && is_array( $saved_draft ) ) {
            $form_values['site_name'] = sanitize_text_field( (string) ( $saved_draft['site_name'] ?? '' ) );
            $form_values['subdomain'] = sanitize_title( (string) ( $saved_draft['subdomain'] ?? '' ) );
            $form_values['admin_email'] = sanitize_email( (string) ( $saved_draft['admin_email'] ?? '' ) );
            $form_values['admin_first_name'] = sanitize_text_field( (string) ( $saved_draft['admin_first_name'] ?? '' ) );
            $form_values['admin_last_name'] = sanitize_text_field( (string) ( $saved_draft['admin_last_name'] ?? '' ) );
        }

        if ( ! empty( $verification_details ) ) {
            $form_values['site_name'] = sanitize_text_field( (string) ( $verification_details['site_name'] ?? $form_values['site_name'] ) );
            $form_values['subdomain'] = sanitize_title( (string) ( $verification_details['subdomain'] ?? $form_values['subdomain'] ) );
            $form_values['admin_email'] = sanitize_email( (string) ( $verification_details['admin_email'] ?? $form_values['admin_email'] ) );
            $form_values['admin_first_name'] = sanitize_text_field( (string) ( $verification_details['admin_first_name'] ?? $form_values['admin_first_name'] ) );
            $form_values['admin_last_name'] = sanitize_text_field( (string) ( $verification_details['admin_last_name'] ?? $form_values['admin_last_name'] ) );
        }

        $captcha_site_key = NetworkProvisioningSettings::captcha_site_key();
        $current_request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( (string) $_SERVER['REQUEST_URI'] ) : '/';
        $request_page_url = esc_url_raw( remove_query_arg( [ 'myvh_site_verify', 'myvh_site_cancel', 'token' ], home_url( $current_request_uri ) ) );

        $network = get_network();
        $network_domain = $network ? preg_replace( '/^www\./', '', (string) $network->domain ) : '';
        $is_subdomain = is_subdomain_install();
        $network_path = $network ? (string) $network->path : '/';

        ob_start();
        include MYVH_PLUGIN_DIR . 'templates/Network/create-site-form.php';

        return (string) ob_get_clean();
    }

    private function ensure_draft_token(): string {
        $cookie = isset( $_COOKIE[ self::DRAFT_COOKIE ] ) ? sanitize_text_field( wp_unslash( (string) $_COOKIE[ self::DRAFT_COOKIE ] ) ) : '';
        if ( $cookie !== '' ) {
            return $cookie;
        }

        $token = wp_generate_password( 24, false, false );
        $expiry = time() + ( 7 * 24 * 60 * 60 );
        $cookie_path = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
        $cookie_domain = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';
        setcookie( self::DRAFT_COOKIE, $token, $expiry, $cookie_path, $cookie_domain, is_ssl(), true );

        return $token;
    }

    private function handle_draft_save( string $draft_token ): void {
        if ( ! isset( $_POST['myvh_create_site_nonce'] ) || ! wp_verify_nonce( (string) $_POST['myvh_create_site_nonce'], 'myvh_create_site_request' ) ) {
            wp_send_json_error( [ 'message' => __( 'Invalid request nonce.', 'my-village-hall' ) ], 403 );
        }

        $setup_payload = [];
        $raw_setup_payload = wp_unslash( (string) ( $_POST['setup_payload'] ?? '' ) );
        if ( $raw_setup_payload !== '' ) {
            $decoded = json_decode( $raw_setup_payload, true );
            if ( ! is_array( $decoded ) ) {
                wp_send_json_error( [ 'message' => __( 'Setup payload is invalid.', 'my-village-hall' ) ], 400 );
            }
            $setup_payload = $decoded;
        }

        set_transient(
            self::DRAFT_PREFIX . $draft_token,
            [
                'site_name' => sanitize_text_field( $_POST['site_name'] ?? '' ),
                'subdomain' => sanitize_title( $_POST['subdomain'] ?? '' ),
                'admin_email' => sanitize_email( $_POST['admin_email'] ?? '' ),
                'admin_first_name' => sanitize_text_field( $_POST['admin_first_name'] ?? '' ),
                'admin_last_name' => sanitize_text_field( $_POST['admin_last_name'] ?? '' ),
                'setup_payload' => $setup_payload,
                'updated_at' => current_time( 'mysql' ),
            ],
            7 * 24 * 60 * 60
        );

        wp_send_json_success( [ 'saved' => true ] );
    }

    private function handle_logo_upload( mixed $file ): string|\WP_Error {
        if ( ! is_array( $file ) || empty( $file['name'] ) ) {
            return '';
        }

        $upload_error = isset( $file['error'] ) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ( $upload_error === UPLOAD_ERR_NO_FILE ) {
            return '';
        }

        if ( $upload_error !== UPLOAD_ERR_OK ) {
            return new \WP_Error( 'myvh_logo_upload_failed', __( 'Logo upload failed. Please try again.', 'my-village-hall' ) );
        }

        if ( ! function_exists( 'wp_handle_upload' ) ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $result = wp_handle_upload( $file, [ 'test_form' => false ] );

        if ( ! is_array( $result ) || ! empty( $result['error'] ) ) {
            return new \WP_Error( 'myvh_logo_upload_failed', __( 'Logo upload failed. Please choose a valid image file and try again.', 'my-village-hall' ) );
        }

        return esc_url_raw( (string) ( $result['url'] ?? '' ) );
    }
}