<?php
namespace MYVH\AutoInvoicing;

use MYVH\Portal\ClientAdminService;
use MYVH\Portal\Support\AjaxResponse;
use MYVH\Portal\Support\PortalAuth;

if (!defined('ABSPATH')) {
    exit;
}

class AutoInvoicingAjaxController {
    public function __construct(
        private AutoInvoice $auto_invoice,
        private ClientAdminService $client_admin_service
    ) {}

    public function register(): void {
        add_action('wp_ajax_myvh_run_auto_invoicing', [$this, 'run_admin']);
        add_action('wp_ajax_myvh_portal_run_auto_invoicing', [$this, 'run_portal']);
    }

    public function run_admin(): void {
        check_ajax_referer('myvh_auto_invoicing', 'nonce');

        if (!is_user_logged_in() || !current_user_can('manage_myvh')) {
            AjaxResponse::permission_error(__('Permission denied', 'my-village-hall'));
        }

        $this->run_and_respond();
    }

    public function run_portal(): void {
        PortalAuth::require_client_admin($this->client_admin_service);
        $this->run_and_respond();
    }

    private function run_and_respond(): void {
        try {
            $invoice_count = (int) $this->auto_invoice->generate();
        } catch (\Throwable $exception) {
            AjaxResponse::server_error(__('Auto-invoicing failed. Please try again.', 'my-village-hall'));
        }

        $message = sprintf(
            _n(
                'Auto-invoicing generated %d invoice.',
                'Auto-invoicing generated %d invoices.',
                $invoice_count,
                'my-village-hall'
            ),
            $invoice_count
        );

        AjaxResponse::success([
            'invoice_count' => $invoice_count,
        ], $message);
    }
}