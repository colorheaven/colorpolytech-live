<?php
/**
 * Voucher print router.
 * Existing ERP print buttons use voucher.php?module=...&id=...
 * This file routes Order, Delivery Note and Sales Invoice to the Tally-style printable format.
 */
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_once __DIR__.'/includes/voucher_print.php';

$module = strtolower(trim((string)($_GET['module'] ?? '')));
$id = (int)($_GET['id'] ?? 0);

$map = [
    'sales_orders' => 'order',
    'sales_order' => 'order',
    'orders' => 'order',
    'order' => 'order',
    'delivery_challans' => 'delivery',
    'delivery_challan' => 'delivery',
    'delivery_notes' => 'delivery',
    'delivery_note' => 'delivery',
    'sales_invoices' => 'invoice',
    'sales_invoice' => 'invoice',
    'invoices' => 'invoice',
    'invoice' => 'invoice',
];

type:
$printType = $map[$module] ?? '';
$title = $printType ? ucwords(str_replace('_',' ', $module)).' Print' : 'Voucher Print';
include __DIR__.'/includes/header.php';

if (!$printType) {
    echo '<div class="alert alert-warning">This voucher module does not have a printable format yet.</div>';
    echo '<a class="btn btn-light" href="javascript:history.back()">Back</a>';
} else {
    vp_render($printType, $id);
}

include __DIR__.'/includes/footer.php';
