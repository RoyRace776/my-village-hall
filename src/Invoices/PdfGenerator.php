<?php

declare(strict_types=1);

namespace MYVH\Invoices;

use Dompdf\Dompdf;
use Dompdf\Options;

if (!defined('ABSPATH')) exit;

/**
 * Wraps Dompdf to convert an HTML string into a raw PDF binary.
 */
class PdfGenerator {

    /**
     * Render the given HTML string to a PDF binary.
     *
     * @param string $html Valid UTF-8 HTML markup.
     * @return string Raw PDF binary.
     */
    public function render(string $html): string {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
