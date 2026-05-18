<?php
/**
 * Invoice PDF Template
 *
 * Variables available (extracted from InvoiceService::get_detail array):
 *
 * @var string  $InvoiceNumber
 * @var string  $InvoiceDate
 * @var string  $DueDate
 * @var string  $Status
 * @var string  $CustomerName
 * @var string  $CustomerEmail
 * @var string  $BillingName
 * @var string  $BillingOrganisationName
 * @var string  $BillingAddressLine1
 * @var string  $BillingAddressLine2
 * @var string  $BillingTownCity
 * @var string  $BillingPostcode
 * @var string  $BillingEmail
 * @var string  $OrganisationName
 * @var string  $Notes
 * @var string  $SubTotal
 * @var string  $TaxAmount
 * @var string  $TotalAmount
 * @var array   $Items   Each item: Description, Quantity, UnitPrice, TaxRate, TaxAmount, TotalAmount
 */

$fmt_currency = static function (string|float $value): string {
    return '£' . number_format((float) $value, 2);
};

$fmt_date = static function (?string $date): string {
    if (!$date) {
        return '';
    }
    $ts = strtotime($date);
    return $ts !== false ? date('d M Y', $ts) : $date;
};
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
        font-family: DejaVu Sans, Arial, sans-serif;
        font-size: 11pt;
        color: #222;
        line-height: 1.4;
    }

    .page {
        padding: 36pt;
    }

    /* ── Header ────────────────────────────────────────── */
    .header {
        width: 100%;
        margin-bottom: 24pt;
    }

    .header td {
        vertical-align: top;
    }

    .invoice-title {
        font-size: 22pt;
        font-weight: bold;
        color: #1a4a8a;
    }

    .invoice-meta {
        font-size: 10pt;
        color: #555;
        margin-top: 4pt;
    }

    .invoice-meta strong {
        color: #222;
    }

    /* ── Addresses ─────────────────────────────────────── */
    .addresses {
        width: 100%;
        margin-bottom: 20pt;
    }

    .address-block {
        font-size: 10pt;
        line-height: 1.6;
    }

    .address-label {
        font-size: 8pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
        color: #888;
        margin-bottom: 4pt;
    }

    /* ── Status badge ──────────────────────────────────── */
    .status-badge {
        display: inline-block;
        padding: 2pt 8pt;
        border-radius: 3pt;
        font-size: 9pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5pt;
        background: #e8f0fb;
        color: #1a4a8a;
        border: 1pt solid #b0c4e8;
    }

    /* ── Line items table ──────────────────────────────── */
    .items-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16pt;
        font-size: 10pt;
    }

    .items-table th {
        background: #f0f4fb;
        color: #1a4a8a;
        font-size: 9pt;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.3pt;
        padding: 6pt 8pt;
        border-bottom: 1.5pt solid #b0c4e8;
        text-align: left;
    }

    .items-table th.num { text-align: right; }

    .items-table td {
        padding: 6pt 8pt;
        border-bottom: 0.5pt solid #dde3ef;
        vertical-align: top;
    }

    .item-subline {
        margin-top: 2pt;
        font-size: 8.5pt;
        color: #666;
    }

    .items-table td.num {
        text-align: right;
        white-space: nowrap;
    }

    .items-table tr:last-child td {
        border-bottom: none;
    }

    /* ── Totals ────────────────────────────────────────── */
    .totals-table {
        width: 240pt;
        border-collapse: collapse;
        font-size: 10pt;
        margin-left: auto;
        margin-bottom: 20pt;
    }

    .totals-table td {
        padding: 4pt 8pt;
    }

    .totals-table td.label {
        color: #555;
        text-align: right;
    }

    .totals-table td.amount {
        text-align: right;
        white-space: nowrap;
    }

    .totals-table tr.total-row td {
        font-size: 12pt;
        font-weight: bold;
        border-top: 1.5pt solid #1a4a8a;
        color: #1a4a8a;
        padding-top: 6pt;
    }

    /* ── Notes ─────────────────────────────────────────── */
    .notes-section {
        margin-top: 16pt;
        padding: 10pt;
        background: #f8f9fb;
        border-left: 3pt solid #b0c4e8;
        font-size: 10pt;
        color: #555;
    }

    .notes-label {
        font-weight: bold;
        color: #333;
        margin-bottom: 4pt;
    }

    /* ── Footer ────────────────────────────────────────── */
    .footer {
        margin-top: 28pt;
        border-top: 0.5pt solid #dde3ef;
        padding-top: 8pt;
        font-size: 8pt;
        color: #aaa;
        text-align: center;
    }
</style>
</head>
<body>
<div class="page">

    <!-- ── Header ─────────────────────────────────────── -->
    <table class="header">
        <tr>
            <td style="width:60%;">
                <div class="invoice-title">INVOICE</div>
                <div class="invoice-meta">
                    <strong># <?php echo htmlspecialchars($InvoiceNumber ?? '', ENT_QUOTES, 'UTF-8'); ?></strong>
                </div>
            </td>
            <td style="width:40%; text-align:right;">
                <div class="invoice-meta">
                    <strong>Issue date:</strong>
                    <?php echo htmlspecialchars($fmt_date($InvoiceDate ?? null), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php if (!empty($DueDate)): ?>
                <div class="invoice-meta">
                    <strong>Due date:</strong>
                    <?php echo htmlspecialchars($fmt_date($DueDate), ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
                <?php if (!empty($Status) && strtolower((string) $Status) !== 'draft'): ?>
                <div style="margin-top:8pt;">
                    <span class="status-badge">
                        <?php echo htmlspecialchars(ucwords(str_replace('-', ' ', $Status)), ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- ── Billing address ────────────────────────────── -->
    <table class="addresses">
        <tr>
            <td style="width:50%; padding-right:16pt;">
                <div class="address-label">Bill To</div>
                <div class="address-block">
                    <?php if (!empty($BillingOrganisationName)): ?>
                        <strong><?php echo htmlspecialchars($BillingOrganisationName, ENT_QUOTES, 'UTF-8'); ?></strong><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingName)): ?>
                        <?php echo htmlspecialchars($BillingName, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingAddressLine1)): ?>
                        <?php echo htmlspecialchars($BillingAddressLine1, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingAddressLine2)): ?>
                        <?php echo htmlspecialchars($BillingAddressLine2, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingTownCity)): ?>
                        <?php echo htmlspecialchars($BillingTownCity, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingPostcode)): ?>
                        <?php echo htmlspecialchars($BillingPostcode, ENT_QUOTES, 'UTF-8'); ?><br>
                    <?php endif; ?>
                    <?php if (!empty($BillingEmail)): ?>
                        <?php echo htmlspecialchars($BillingEmail, ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </div>
            </td>
            <td style="width:50%;">
                <?php if (!empty($BillingReference)): ?>
                <div class="address-label">Your Reference</div>
                <div class="address-block">
                    <?php echo htmlspecialchars($BillingReference, ENT_QUOTES, 'UTF-8'); ?>
                </div>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <!-- ── Line items ─────────────────────────────────── -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:50%;">Description</th>
                <th class="num" style="width:10%;">Qty</th>
                <th class="num" style="width:15%;">Unit price</th>
                <th class="num" style="width:10%;">Tax %</th>
                <th class="num" style="width:15%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($Items ?? [] as $item): ?>
            <tr>
                <td>
                    <?php echo htmlspecialchars($item['Description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    <?php
                    $has_booking_date = !empty($item['BookingId']) && !empty($item['StartDate']);
                    $item_type = strtolower((string) ($item['ItemType'] ?? 'charge'));
                    ?>
                    <?php if ($has_booking_date && $item_type !== 'deposit'): ?>
                        <div class="item-subline">
                            <?php echo htmlspecialchars('Booking date: ' . $fmt_date((string) $item['StartDate']), ENT_QUOTES, 'UTF-8'); ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td class="num"><?php echo htmlspecialchars(rtrim(rtrim(number_format((float)($item['Quantity'] ?? 1), 2), '0'), '.'), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars($fmt_currency($item['UnitPrice'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars(number_format((float)($item['TaxRate'] ?? 0), 2) . '%', ENT_QUOTES, 'UTF-8'); ?></td>
                <td class="num"><?php echo htmlspecialchars($fmt_currency($item['TotalAmount'] ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($Items)): ?>
            <tr>
                <td colspan="5" style="text-align:center;color:#aaa;font-style:italic;padding:16pt;">
                    No line items.
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <!-- ── Totals ──────────────────────────────────────── -->
    <table class="totals-table">
        <tr>
            <td class="label">Subtotal</td>
            <td class="amount"><?php echo htmlspecialchars($fmt_currency($SubTotal ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php if (!empty($TaxAmount) && (float) $TaxAmount > 0): ?>
        <tr>
            <td class="label">Tax</td>
            <td class="amount"><?php echo htmlspecialchars($fmt_currency($TaxAmount), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
        <?php endif; ?>
        <tr class="total-row">
            <td class="label">Total</td>
            <td class="amount"><?php echo htmlspecialchars($fmt_currency($TotalAmount ?? 0), ENT_QUOTES, 'UTF-8'); ?></td>
        </tr>
    </table>

    <!-- ── Notes ──────────────────────────────────────── -->
    <?php if (!empty($Notes)): ?>
    <div class="notes-section">
        <div class="notes-label">Notes</div>
        <?php echo nl2br(htmlspecialchars($Notes, ENT_QUOTES, 'UTF-8')); ?>
    </div>
    <?php endif; ?>

    <!-- ── Footer ─────────────────────────────────────── -->
    <div class="footer">
        Invoice <?php echo htmlspecialchars($InvoiceNumber ?? '', ENT_QUOTES, 'UTF-8'); ?>
        &mdash; Generated <?php echo date('d M Y'); ?>
    </div>

</div>
</body>
</html>
