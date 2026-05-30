<?php
namespace MYVH\Payments;

if (!defined('ABSPATH')) {
    exit;
}

class PaymentController {
    private PaymentService $service;

    public function __construct(PaymentService $service) {
        $this->service = $service;
    }

    public function create(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_record_payment');

        $payload = wp_unslash($_POST);
        $send_receipt = $this->should_send_receipt($payload);
        $result = $this->service->create($payload);

        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-payments'), 'error', $result->get_error_message(), $this->get_admin_redirect_args());
            exit;
        }

        if (!$send_receipt) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-payments'), 'updated', '1', $this->get_admin_redirect_args());
            exit;
        }

        $receipt_result = $this->service->send_receipt((int) $result);
        if (is_wp_error($receipt_result)) {
            $this->redirect_with_message(
                $this->get_admin_page_slug('myvh-payments'),
                'updated',
                '1',
                array_merge($this->get_admin_redirect_args(), ['receipt_error' => $receipt_result->get_error_message()])
            );
            exit;
        }

        $this->redirect_with_message(
            $this->get_admin_page_slug('myvh-payments'),
            'updated',
            '1',
            array_merge($this->get_admin_redirect_args(), ['receipt_sent' => '1'])
        );
        exit;
    }

    public function delete(): void {
        if (!current_user_can('manage_myvh')) {
            wp_die(__('Permission denied', 'my-village-hall'));
        }

        check_admin_referer('myvh_delete_payment');

        $payment_id = \intval($_REQUEST['payment_id'] ?? $_REQUEST['id'] ?? 0);
        $result = $this->service->delete($payment_id);

        if (is_wp_error($result)) {
            $this->redirect_with_message($this->get_admin_page_slug('myvh-payments'), 'error', $result->get_error_message(), $this->get_admin_redirect_args());
            exit;
        }

        $this->redirect_with_message($this->get_admin_page_slug('myvh-payments'), 'deleted', '1', $this->get_admin_redirect_args());
        exit;
    }

    private function get_admin_redirect_args(): array {
        $args = [];

        $invoice_id = \intval($_REQUEST['invoice_id'] ?? 0);
        if ($invoice_id > 0) {
            $args['invoice_id'] = $invoice_id;
        }

        $redirect_view = \intval($_REQUEST['redirect_view'] ?? 0);
        if ($redirect_view > 0) {
            $args['view'] = $redirect_view;
        }

        return $args;
    }

    private function get_admin_page_slug(string $fallback): string {
        $page = sanitize_key($_REQUEST['redirect_page'] ?? $_REQUEST['page'] ?? $fallback);
        $allowed_pages = ['myvh-invoices', 'myvh-payments'];

        return in_array($page, $allowed_pages, true) ? $page : $fallback;
    }

    private function redirect_with_message(string $page, string $key, string $value, array $extra_args = []): void {
        $args = array_merge(['page' => $page, $key => $value], $extra_args);
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
    }

    private function should_send_receipt(array $payload): bool {
        $raw = $payload['send_receipt'] ?? '';
        $value = is_scalar($raw) ? strtolower(trim((string) $raw)) : '';

        return in_array($value, ['1', 'true', 'yes', 'on'], true);
    }
}
