<?php
namespace MYVH\Portal\Support;

/**
 * AjaxResponse: Standardized AJAX response helper
 *
 * Provides consistent response format across all AJAX controllers.
 * All error responses follow the same structure with HTTP status codes.
 */
class AjaxResponse {

    /**
     * Send a standardized success response
     *
     * @param array $data The response data payload
     * @param string $message Optional success message
     * @return void
     */
    public static function success(array $data = [], string $message = ''): void {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($message)) {
            $response['message'] = $message;
        }

        wp_send_json($response);
    }

    /**
     * Send a standardized error response
     *
     * @param string $message Error message to display
     * @param int $code HTTP status code (default 400)
     * @param array $data Optional additional error data
     * @return void
     */
    public static function error(string $message, int $code = 400, array $data = []): void {
        $response = [
            'success' => false,
            'message' => $message,
            'code' => $code,
        ];

        if (!empty($data)) {
            $response['data'] = $data;
        }

        wp_send_json($response, $code);
    }

    /**
     * Send a validation error response
     *
     * @param array $errors Array of field => error_message pairs
     * @return void
     */
    public static function validation_error(array $errors): void {
        self::error(
            __('Validation failed', 'my-village-hall'),
            400,
            ['errors' => $errors]
        );
    }

    /**
     * Send an authentication error response
     *
     * @param string $message Optional custom message
     * @return void
     */
    public static function auth_error(string $message = ''): void {
        self::error(
            $message ?: __('Authentication required', 'my-village-hall'),
            401
        );
    }

    /**
     * Send a permission denied error response
     *
     * @param string $message Optional custom message
     * @return void
     */
    public static function permission_error(string $message = ''): void {
        self::error(
            $message ?: __('Permission denied', 'my-village-hall'),
            403
        );
    }

    /**
     * Send a not found error response
     *
     * @param string $message Optional custom message
     * @return void
     */
    public static function not_found(string $message = ''): void {
        self::error(
            $message ?: __('Resource not found', 'my-village-hall'),
            404
        );
    }

    /**
     * Send a server error response
     *
     * @param string $message Optional custom message
     * @return void
     */
    public static function server_error(string $message = ''): void {
        self::error(
            $message ?: __('An error occurred on the server', 'my-village-hall'),
            500
        );
    }
}
