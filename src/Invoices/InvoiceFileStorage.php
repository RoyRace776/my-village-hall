<?php

declare(strict_types=1);

namespace MYVH\Invoices;

if (!defined('ABSPATH')) exit;

/**
 * Handles filesystem storage and URL resolution of generated invoice PDFs.
 *
 * PDFs are stored under:
 *   {wp-uploads}/myvh-invoices/{YYYY}/{MM}/invoice-{InvoiceNumber}.pdf
 *
 * The absolute file path is persisted separately in the database via
 * InvoiceRepository::set_pdf_path() / get_pdf_path().
 * This class therefore only manages the filesystem side.
 */
class InvoiceFileStorage {

    private const UPLOAD_SUBDIR = 'myvh-invoices';

    public function __construct(private readonly InvoiceRepository $repo) {}

    /**
     * Write a PDF binary to the appropriate upload folder for an invoice.
     * Creates subdirectories as needed.
     * Does NOT persist the path to the database — the caller is responsible.
     *
     * @param int    $invoiceId  Invoice ID used to look up number and date.
     * @param string $pdfBinary  Raw PDF binary returned by PdfGenerator::render().
     * @return string Absolute filesystem path to the saved file.
     * @throws \RuntimeException On missing invoice or filesystem failure.
     */
    public function save(int $invoiceId, string $pdfBinary): string {
        $invoice = $this->repo->get_by_id($invoiceId);

        if (!$invoice) {
            throw new \RuntimeException("InvoiceFileStorage: invoice #{$invoiceId} not found.");
        }

        $path = $this->build_path($invoice['InvoiceNumber'], $invoice['InvoiceDate']);
        $dir  = dirname($path);

        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            throw new \RuntimeException("InvoiceFileStorage: could not create directory {$dir}.");
        }

        if (file_put_contents($path, $pdfBinary) === false) {
            throw new \RuntimeException("InvoiceFileStorage: could not write PDF to {$path}.");
        }

        return $path;
    }

    /**
     * Return true if the stored PdfPath for the invoice points to an existing file.
     *
     * @param int $invoiceId
     * @return bool
     */
    public function exists(int $invoiceId): bool {
        $stored = $this->repo->get_pdf_path($invoiceId);

        return $stored !== null && $stored !== '' && file_exists($stored);
    }

    /**
     * Return the absolute path for an invoice's PDF, or null if it does not exist.
     *
     * @param int $invoiceId
     * @return string|null
     */
    public function getPath(int $invoiceId): ?string {
        $stored = $this->repo->get_pdf_path($invoiceId);

        if ($stored === null || $stored === '') {
            return null;
        }

        return file_exists($stored) ? $stored : null;
    }

    /**
     * Return a publicly accessible URL for an invoice's PDF, or null if unavailable.
     *
     * @param int $invoiceId
     * @return string|null
     */
    public function getUrl(int $invoiceId): ?string {
        $path = $this->getPath($invoiceId);

        if ($path === null) {
            return null;
        }

        return $this->path_to_url($path);
    }

    /**
     * Delete an invoice PDF file if it exists.
     *
     * @param int $invoiceId
     * @return bool True when the file is deleted or did not exist.
     */
    public function delete(int $invoiceId): bool {
        $path = $this->getPath($invoiceId);

        if ($path === null) {
            return true;
        }

        return @unlink($path);
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    /**
     * Build the expected absolute filesystem path for an invoice PDF.
     *
     * @param string $invoiceNumber  e.g. "INV-000042"
     * @param string $invoiceDate    e.g. "2026-04-17"
     * @return string
     */
    private function build_path(string $invoiceNumber, string $invoiceDate): string {
        $upload_dir = wp_upload_dir();
        $year       = substr($invoiceDate, 0, 4);
        $month      = substr($invoiceDate, 5, 2);
        $filename   = 'invoice-' . sanitize_file_name($invoiceNumber) . '.pdf';

        return trailingslashit($upload_dir['basedir'])
            . self::UPLOAD_SUBDIR . '/'
            . $year . '/'
            . $month . '/'
            . $filename;
    }

    /**
     * Convert an absolute upload-dir path to its equivalent public URL.
     *
     * @param string $absolute_path
     * @return string
     */
    private function path_to_url(string $absolute_path): string {
        $upload_dir = wp_upload_dir();
        $base_dir   = trailingslashit($upload_dir['basedir']);
        $base_url   = trailingslashit($upload_dir['baseurl']);

        return str_replace($base_dir, $base_url, $absolute_path);
    }
}
