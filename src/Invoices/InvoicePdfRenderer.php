<?php

declare(strict_types=1);

namespace MYVH\Invoices;

if (!defined('ABSPATH')) exit;

/**
 * Renders an invoice array as HTML using a PHP template file.
 * The templates directory path is injected so this class has no
 * dependency on WordPress path helpers itself.
 */
class InvoicePdfRenderer {

    public function __construct(private readonly string $templates_dir) {}

    /**
     * Render an invoice detail array (as returned by InvoiceService::get_detail)
     * to an HTML string suitable for PDF generation.
     *
     * @param array $invoice Full invoice detail including 'Items' sub-array.
     * @return string HTML markup.
     * @throws \RuntimeException When the template file cannot be found.
     */
    public function renderHtml(array $invoice): string {
        $template = $this->templates_dir . 'invoice-pdf.php';

        if (!file_exists($template)) {
            throw new \RuntimeException('Invoice PDF template not found: ' . $template);
        }

        ob_start();
        extract($invoice, EXTR_SKIP);
        include $template;

        return (string) ob_get_clean();
    }
}
