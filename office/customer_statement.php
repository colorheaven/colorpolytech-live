<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('customers.view');

$title = 'Customer Statement';
$customerId = (int)($_GET['customer_id'] ?? $_GET['id'] ?? 0);
$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
$export = strtolower(trim((string)($_GET['export'] ?? '')));

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');
if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }

function statement_table_columns_safe(string $table): array {
    try { return table_exists($table) ? table_columns($table) : []; } catch (Throwable $e) { return []; }
}

function statement_has_col(string $table, string $col): bool {
    return in_array($col, statement_table_columns_safe($table), true);
}

function statement_pick_col(string $table, array $candidates): string {
    $cols = statement_table_columns_safe($table);
    foreach ($candidates as $c) if (in_array($c, $cols, true)) return $c;
    return '';
}

function statement_money($amount): string {
    return number_format((float)$amount, 2);
}

function statement_date($date): string {
    if (!$date) return '';
    $ts = strtotime((string)$date);
    return $ts ? date('d-M-y', $ts) : (string)$date;
}

function statement_customer(int $customerId): array {
    if (!$customerId || !table_exists('customers')) return [];
    $st = db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
    $st->execute([$customerId]);
    return $st->fetch() ?: [];
}

function statement_customer_name(array $c): string {
    return (string)($c['customer_name'] ?? $c['company_name'] ?? $c['name'] ?? 'Customer');
}

function statement_customer_address(array $c): string {
    return trim((string)($c['address'] ?? ''));
}

function statement_invoice_items(int $invoiceId): array {
    if (!$invoiceId || !table_exists('sales_invoice_items')) return [];
    $cols = statement_table_columns_safe('sales_invoice_items');
    $nameCol = statement_pick_col('sales_invoice_items', ['product_name','description','item_name']);
    $qtyCol = statement_pick_col('sales_invoice_items', ['quantity','qty']);
    $unitCol = statement_pick_col('sales_invoice_items', ['unit_name','unit']);
    $rateCol = statement_pick_col('sales_invoice_items', ['rate','unit_price','sales_rate']);
    $amountCol = statement_pick_col('sales_invoice_items', ['amount','line_total','total_amount']);
    $fkCol = statement_pick_col('sales_invoice_items', ['sales_invoice_id','invoice_id']);
    if (!$fkCol) return [];
    $st = db()->prepare("SELECT * FROM sales_invoice_items WHERE `$fkCol`=? ORDER BY id");
    $st->execute([$invoiceId]);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        $rows[] = [
            'name' => $nameCol ? (string)($r[$nameCol] ?? '') : '',
            'qty' => $qtyCol ? (float)($r[$qtyCol] ?? 0) : 0,
            'unit' => $unitCol ? (string)($r[$unitCol] ?? '') : '',
            'rate' => $rateCol ? (float)($r[$rateCol] ?? 0) : 0,
            'amount' => $amountCol ? (float)($r[$amountCol] ?? 0) : 0,
        ];
    }
    return $rows;
}

function statement_rows(int $customerId, string $from, string $to): array {
    $rows = [];

    if (table_exists('sales_invoices')) {
        $dateCol = statement_pick_col('sales_invoices', ['invoice_date','created_at']);
        $noCol = statement_pick_col('sales_invoices', ['invoice_no','voucher_no','sales_no']);
        $amountCol = statement_pick_col('sales_invoices', ['total_amount','grand_total','amount']);
        $statusCol = statement_has_col('sales_invoices','status') ? 'status' : '';
        if ($dateCol && statement_has_col('sales_invoices','customer_id')) {
            $where = "customer_id=? AND DATE(`$dateCol`) BETWEEN ? AND ?";
            $params = [$customerId, $from, $to];
            if ($statusCol) $where .= " AND `$statusCol` NOT IN ('Rejected','Cancelled')";
            $st = db()->prepare("SELECT * FROM sales_invoices WHERE $where ORDER BY `$dateCol`, id");
            $st->execute($params);
            foreach ($st->fetchAll() as $r) {
                $rows[] = [
                    'date' => $r[$dateCol] ?? '',
                    'side' => 'Cr',
                    'particulars' => 'Sales',
                    'vch_type' => 'Sales',
                    'vch_no' => $noCol ? ($r[$noCol] ?? $r['id']) : $r['id'],
                    'debit' => (float)($amountCol ? ($r[$amountCol] ?? 0) : 0),
                    'credit' => 0,
                    'items' => statement_invoice_items((int)$r['id']),
                    'bank_details' => '',
                ];
            }
        }
    }

    if (table_exists('collections')) {
        $dateCol = statement_pick_col('collections', ['receipt_date','collection_date','created_at']);
        $noCol = statement_pick_col('collections', ['receipt_no','voucher_no','collection_no']);
        $amountCol = statement_pick_col('collections', ['amount','received_amount','total_amount']);
        $methodCol = statement_pick_col('collections', ['payment_method','collection_method','mode']);
        $statusCol = statement_has_col('collections','status') ? 'status' : '';
        if ($dateCol && statement_has_col('collections','customer_id')) {
            $where = "customer_id=? AND DATE(`$dateCol`) BETWEEN ? AND ?";
            $params = [$customerId, $from, $to];
            if ($statusCol) $where .= " AND `$statusCol` NOT IN ('Rejected','Cancelled')";
            $st = db()->prepare("SELECT * FROM collections WHERE $where ORDER BY `$dateCol`, id");
            $st->execute($params);
            foreach ($st->fetchAll() as $r) {
                $method = $methodCol ? (string)($r[$methodCol] ?? '') : 'Receipt';
                $bankDetails = [];
                foreach (['bank_name'=>'Bank','cheque_bank_name'=>'Cheque Bank','cheque_number'=>'Cheque No','cheque_date'=>'Cheque Date','transaction_id'=>'Txn ID','bank_account_id'=>'Bank Account'] as $col=>$label) {
                    if (statement_has_col('collections',$col) && !empty($r[$col])) $bankDetails[] = $label.': '.$r[$col];
                }
                if (stripos($method, 'bank') !== false && !$bankDetails) $bankDetails[] = 'Bank collection';
                $rows[] = [
                    'date' => $r[$dateCol] ?? '',
                    'side' => 'Dr',
                    'particulars' => trim($method.' Receipt'),
                    'vch_type' => 'Receipt',
                    'vch_no' => $noCol ? ($r[$noCol] ?? $r['id']) : $r['id'],
                    'debit' => 0,
                    'credit' => (float)($amountCol ? ($r[$amountCol] ?? 0) : 0),
                    'items' => [],
                    'bank_details' => implode(' | ', $bankDetails),
                ];
            }
        }
    }

    usort($rows, fn($a,$b) => strcmp((string)$a['date'], (string)$b['date']) ?: strcmp((string)$a['vch_no'], (string)$b['vch_no']));
    return $rows;
}

function statement_opening_balance(int $customerId, string $from): float {
    $opening = 0;
    $c = statement_customer($customerId);
    if ($c) $opening += (float)($c['opening_balance'] ?? 0);

    if (table_exists('sales_invoices')) {
        $dateCol = statement_pick_col('sales_invoices', ['invoice_date','created_at']);
        $amountCol = statement_pick_col('sales_invoices', ['total_amount','grand_total','amount']);
        if ($dateCol && $amountCol && statement_has_col('sales_invoices','customer_id')) {
            $st = db()->prepare("SELECT COALESCE(SUM(`$amountCol`),0) FROM sales_invoices WHERE customer_id=? AND DATE(`$dateCol`) < ?" );
            $st->execute([$customerId, $from]);
            $opening += (float)$st->fetchColumn();
        }
    }
    if (table_exists('collections')) {
        $dateCol = statement_pick_col('collections', ['receipt_date','collection_date','created_at']);
        $amountCol = statement_pick_col('collections', ['amount','received_amount','total_amount']);
        if ($dateCol && $amountCol && statement_has_col('collections','customer_id')) {
            $st = db()->prepare("SELECT COALESCE(SUM(`$amountCol`),0) FROM collections WHERE customer_id=? AND DATE(`$dateCol`) < ?" );
            $st->execute([$customerId, $from]);
            $opening -= (float)$st->fetchColumn();
        }
    }
    return $opening;
}

function statement_build(int $customerId, string $from, string $to): array {
    $customer = statement_customer($customerId);
    $opening = statement_opening_balance($customerId, $from);
    $rows = statement_rows($customerId, $from, $to);
    $balance = $opening;
    $debitTotal = 0;
    $creditTotal = 0;
    foreach ($rows as &$r) {
        $debitTotal += $r['debit'];
        $creditTotal += $r['credit'];
        $balance += $r['debit'] - $r['credit'];
        $r['balance'] = $balance;
    }
    unset($r);
    return compact('customer','opening','rows','balance','debitTotal','creditTotal');
}

function statement_export_excel(array $data, string $from, string $to): void {
    $customerName = statement_customer_name($data['customer']);
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="customer_statement_'.date('Ymd_His').'.xls"');
    echo "<html><head><meta charset='utf-8'></head><body>";
    statement_render_html($data, $from, $to, true);
    echo "</body></html>";
    exit;
}

function statement_render_html(array $data, string $from, string $to, bool $export=false): void {
    $customer = $data['customer'];
    $customerName = statement_customer_name($customer);
    $customerAddress = statement_customer_address($customer);
    $runningSide = $data['opening'] >= 0 ? 'Dr' : 'Cr';
    ?>
    <style>
        .statement-wrap{background:#fff;color:#111;font-size:12px;max-width:1080px;margin:0 auto}.statement-head{text-align:center;line-height:1.35;margin-bottom:20px}.statement-head h3,.statement-head h4{margin:0}.statement-meta{text-align:center;margin:10px 0 20px}.statement-table{width:100%;border-collapse:collapse}.statement-table th{border-top:1px solid #444;border-bottom:1px solid #444;padding:6px;text-align:left}.statement-table td{padding:5px 6px;vertical-align:top}.statement-table .num{text-align:right;white-space:nowrap}.item-lines{padding-left:55px;color:#222}.item-line{display:flex;gap:16px}.totals td{border-top:1px solid #444;font-weight:bold}.bank-details{font-size:11px;color:#555;padding-left:55px}.print-actions{max-width:1080px;margin:0 auto 15px}@media print{.no-print,.sidebar,.topbar{display:none!important}.main,.content{margin:0!important;padding:0!important}.statement-wrap{max-width:none}.statement-table{font-size:11px}}
    </style>
    <div class="statement-wrap">
        <div class="statement-head">
            <h3><?=e(setting('company_name','Color Heaven'))?></h3>
            <div><?=e(setting('company_address','101/102, Horonath Gosh Road, Chawkbazar'))?></div>
            <div>E-Mail : <?=e(setting('company_email','colorheaven.bd@gmail.com'))?></div>
            <h4 style="margin-top:10px"><?=e($customerName)?></h4>
            <div>Ledger Account</div>
            <?php if ($customerAddress): ?><div><?=e($customerAddress)?></div><?php endif; ?>
        </div>
        <div class="statement-meta"><?=e(statement_date($from))?> to <?=e(statement_date($to))?></div>
        <table class="statement-table">
            <thead><tr><th>Date</th><th>Particulars</th><th>Vch Type</th><th>Vch No.</th><th class="num">Debit</th><th class="num">Credit</th><th class="num">Balance</th></tr></thead>
            <tbody>
                <tr><td><?=e(statement_date($from))?></td><td><strong>Opening Balance</strong></td><td></td><td></td><td class="num"><?= $data['opening']>=0 ? statement_money($data['opening']) : '' ?></td><td class="num"><?= $data['opening']<0 ? statement_money(abs($data['opening'])) : '' ?></td><td class="num"><?=statement_money(abs($data['opening']))?> <?=$runningSide?></td></tr>
                <?php foreach ($data['rows'] as $r): $side = $r['balance'] >= 0 ? 'Dr' : 'Cr'; ?>
                    <tr>
                        <td><?=e(statement_date($r['date']))?></td>
                        <td><?=e($r['side'])?> <strong><?=e($r['particulars'])?></strong></td>
                        <td><?=e($r['vch_type'])?></td>
                        <td><?=e($r['vch_no'])?></td>
                        <td class="num"><?= $r['debit'] ? statement_money($r['debit']) : '' ?></td>
                        <td class="num"><?= $r['credit'] ? statement_money($r['credit']) : '' ?></td>
                        <td class="num"><?=statement_money(abs($r['balance']))?> <?=$side?></td>
                    </tr>
                    <?php if (!empty($r['items'])): ?>
                        <tr><td></td><td colspan="6"><div class="item-lines">
                            <?php foreach ($r['items'] as $it): ?>
                                <div class="item-line"><span><?=e($it['name'])?></span><span><?=statement_money($it['qty'])?> <?=e($it['unit'])?></span><span><?=statement_money($it['rate'])?>/<?=e($it['unit'] ?: 'Unit')?></span><span><?=statement_money($it['amount'])?></span></div>
                            <?php endforeach; ?>
                        </div></td></tr>
                    <?php endif; ?>
                    <?php if (!empty($r['bank_details'])): ?>
                        <tr><td></td><td colspan="6"><div class="bank-details">Bank Details: <?=e($r['bank_details'])?></div></td></tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                <tr class="totals"><td colspan="4" class="num">Total</td><td class="num"><?=statement_money($data['debitTotal'] + max($data['opening'],0))?></td><td class="num"><?=statement_money($data['creditTotal'] + max(-$data['opening'],0))?></td><td></td></tr>
                <tr class="totals"><td colspan="4" class="num">Closing Balance</td><td colspan="2"></td><td class="num"><?=statement_money(abs($data['balance']))?> <?= $data['balance']>=0 ? 'Dr' : 'Cr' ?></td></tr>
            </tbody>
        </table>
    </div>
    <?php
}

$data = $customerId ? statement_build($customerId, $from, $to) : ['customer'=>[], 'rows'=>[], 'opening'=>0, 'balance'=>0, 'debitTotal'=>0, 'creditTotal'=>0];
if ($export === 'excel') statement_export_excel($data, $from, $to);

if ($export === 'pdf') {
    include __DIR__.'/includes/header.php';
    echo '<div class="print-actions no-print"><button class="btn btn-primary" onclick="window.print()">Print / Save as PDF</button> <a class="btn btn-light" href="customer_statement.php?customer_id='.$customerId.'&from='.e($from).'&to='.e($to).'">Back</a></div>';
    statement_render_html($data, $from, $to);
    echo '<script>setTimeout(function(){window.print();},500);</script>';
    include __DIR__.'/includes/footer.php';
    exit;
}

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print">
    <div><h3>Customer Statement</h3><p class="text-muted mb-0">Period-wise ledger statement with sales, receipt, item lines, balance and bank details.</p></div>
    <a class="btn btn-light" href="customers.php">Back to Customers</a>
</div>

<div class="card mb-3 no-print"><div class="card-body">
    <form class="row g-2 align-items-end" method="get">
        <input type="hidden" name="customer_id" value="<?=e($customerId)?>">
        <div class="col-md-4"><label class="form-label">Customer</label><input class="form-control" value="<?=e(statement_customer_name($data['customer']))?>" readonly></div>
        <div class="col-md-3"><label class="form-label">From</label><input class="form-control" type="date" name="from" value="<?=e($from)?>"></div>
        <div class="col-md-3"><label class="form-label">To</label><input class="form-control" type="date" name="to" value="<?=e($to)?>"></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div>
        <div class="col-12 d-flex gap-2 mt-3">
            <a class="btn btn-outline-danger" href="customer_statement.php?customer_id=<?=$customerId?>&from=<?=e($from)?>&to=<?=e($to)?>&export=pdf">PDF / Print</a>
            <a class="btn btn-outline-success" href="customer_statement.php?customer_id=<?=$customerId?>&from=<?=e($from)?>&to=<?=e($to)?>&export=excel">Export Excel</a>
        </div>
    </form>
</div></div>

<?php if (!$customerId || empty($data['customer'])): ?>
    <div class="alert alert-warning">Please select a valid customer from Customer List.</div>
<?php else: ?>
    <?php statement_render_html($data, $from, $to); ?>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
