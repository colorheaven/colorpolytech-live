<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('reports.view');
$title='Reports';
$reports=[
    ['Customer Ledger','ledger.php','ledger.view'],
    ['Supplier Ledger','ledger.php?party_type=supplier','ledger.view'],
    ['Sales Report','sales_invoices.php','sales_invoices.view'],
    ['Collection Report','collections.php','collections.view'],
    ['Marketer Report','sales_orders.php','sales_orders.view'],
    ['Delivery Report','delivery_challans.php','delivery_challans.view'],
    ['Trial Balance','trial_balance.php','trial_balance.view'],
    ['Balance Sheet','balance_sheet.php','balance_sheet.view'],
];
include __DIR__.'/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <div><h3>Reports</h3><p class="text-muted mb-0">Role-filtered reports, ledgers, vouchers, print and export pages.</p></div>
</div>
<div class="row g-3">
<?php foreach($reports as $r): if(!can($r[2])) continue; ?>
    <div class="col-md-3"><a class="card card-body text-decoration-none h-100" href="<?=$r[1]?>"><h5><?=e($r[0])?></h5><small class="text-muted">Filter, print, export</small></a></div>
<?php endforeach; ?>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
