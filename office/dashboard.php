<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('dashboard.view');
$title='ERP Dashboard';

function dashboard_period_options(): array {
    return [
        'today' => 'Today',
        'week' => 'This Business Week',
        'month' => 'This Month',
        'year' => 'This Year',
        'all' => 'All Time',
        'custom' => 'Custom',
    ];
}

function dashboard_period_range(): array {
    $period = $_GET['period'] ?? 'today';
    $periods = dashboard_period_options();
    if (!isset($periods[$period])) $period = 'today';

    $today = today();
    $from = $today;
    $to = $today;

    if ($period === 'week') {
        // Bangladesh business week: Saturday to Thursday.
        $dow = (int)date('w'); // Sun=0 ... Sat=6
        $daysSinceSaturday = ($dow + 1) % 7;
        $from = date('Y-m-d', strtotime("-{$daysSinceSaturday} days"));
        $to = date('Y-m-d', strtotime($from.' +5 days'));
        if ($to > $today) $to = $today;
    } elseif ($period === 'month') {
        $from = date('Y-m-01');
        $to = $today;
    } elseif ($period === 'year') {
        $from = date('Y-01-01');
        $to = $today;
    } elseif ($period === 'all') {
        $from = '';
        $to = '';
    } elseif ($period === 'custom') {
        $from = trim((string)($_GET['from'] ?? $today));
        $to = trim((string)($_GET['to'] ?? $today));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = $today;
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = $today;
        if ($from > $to) { $tmp = $from; $from = $to; $to = $tmp; }
    }

    return [$period, $from, $to, $periods[$period]];
}

function dashboard_cols(string $table): array {
    try { return table_columns($table); } catch (Throwable $e) { return []; }
}

function dashboard_has_col(string $table, string $column): bool {
    return in_array($column, dashboard_cols($table), true);
}

function dashboard_pick_col(string $table, array $candidates): string {
    $cols = dashboard_cols($table);
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return '';
}

function dashboard_add_date_filter(string &$where, array &$params, string $table, string $alias, array $dateCandidates, string $from, string $to): void {
    if ($from === '' || $to === '') return;
    $col = dashboard_pick_col($table, $dateCandidates);
    if ($col === '') return;
    $where = $where ? "($where) AND (DATE(`$alias`.`$col`) BETWEEN ? AND ?)" : "DATE(`$alias`.`$col`) BETWEEN ? AND ?";
    $params[] = $from;
    $params[] = $to;
}

function dashboard_add_status_filter(string &$where, array &$params, string $table, string $alias, array $statuses): void {
    if (!$statuses || !dashboard_has_col($table, 'status')) return;
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $where = $where ? "($where) AND (`$alias`.`status` IN ($placeholders))" : "`$alias`.`status` IN ($placeholders)";
    foreach ($statuses as $s) $params[] = $s;
}

function dashboard_apply_scope(string &$where, array &$params, string $table, string $module, string $alias='p'): void {
    try { apply_scoped_where($where, $params, $table, $module, $alias); } catch (Throwable $e) {}
}

function dashboard_scalar(string $sql, array $params=[]): float {
    try {
        $st = db()->prepare($sql);
        $st->execute($params);
        return (float)$st->fetchColumn();
    } catch (Throwable $e) {
        return 0;
    }
}

function dashboard_base_where(string $table, string $module, array $dateCols, string $from, string $to, array $statuses=[]): array {
    $where = '1=1';
    $params = [];
    dashboard_add_date_filter($where, $params, $table, 'p', $dateCols, $from, $to);
    dashboard_add_status_filter($where, $params, $table, 'p', $statuses);
    dashboard_apply_scope($where, $params, $table, $module, 'p');
    return [$where, $params];
}

function dashboard_count_rows(string $table, string $module, array $dateCols, string $from, string $to, array $statuses=[]): int {
    if (!table_exists($table)) return 0;
    [$where, $params] = dashboard_base_where($table, $module, $dateCols, $from, $to, $statuses);
    return (int)dashboard_scalar("SELECT COUNT(*) FROM `$table` p WHERE $where", $params);
}

function dashboard_sum_parent(string $table, string $module, string $amountCol, array $dateCols, string $from, string $to, array $statuses=[]): float {
    if (!table_exists($table) || !dashboard_has_col($table, $amountCol)) return 0;
    [$where, $params] = dashboard_base_where($table, $module, $dateCols, $from, $to, $statuses);
    return dashboard_scalar("SELECT COALESCE(SUM(`p`.`$amountCol`),0) FROM `$table` p WHERE $where", $params);
}

function dashboard_sum_quantity(string $parentTable, string $module, string $itemTable, string $fk, array $dateCols, string $from, string $to, array $statuses=[]): float {
    if (!table_exists($parentTable) || !table_exists($itemTable)) return 0;
    if (!dashboard_has_col($itemTable, $fk) || !dashboard_has_col($itemTable, 'quantity')) return 0;
    [$where, $params] = dashboard_base_where($parentTable, $module, $dateCols, $from, $to, $statuses);
    return dashboard_scalar("SELECT COALESCE(SUM(i.quantity),0) FROM `$parentTable` p LEFT JOIN `$itemTable` i ON i.`$fk`=p.id WHERE $where", $params);
}

function dashboard_delivery_amount(array $dateCols, string $from, string $to, array $statuses=[]): float {
    if (!table_exists('delivery_challans') || !table_exists('sales_orders')) return 0;
    if (!dashboard_has_col('delivery_challans', 'sales_order_id') || !dashboard_has_col('sales_orders', 'total_amount')) return 0;
    [$where, $params] = dashboard_base_where('delivery_challans', 'delivery_challans', $dateCols, $from, $to, $statuses);
    return dashboard_scalar("SELECT COALESCE(SUM(so.total_amount),0) FROM delivery_challans p LEFT JOIN sales_orders so ON so.id=p.sales_order_id WHERE $where", $params);
}

function dashboard_due_metric(string $from, string $to): array {
    if (!table_exists('sales_invoices')) return ['no'=>0, 'qty'=>0, 'amount'=>0];
    $where = '1=1';
    $params = [];
    dashboard_add_date_filter($where, $params, 'sales_invoices', 'p', ['invoice_date','created_at'], $from, $to);
    if (dashboard_has_col('sales_invoices', 'status')) {
        $where .= " AND p.status='Approved'";
    }
    if (dashboard_has_col('sales_invoices', 'due_amount')) {
        $where .= " AND p.due_amount>0";
    }
    dashboard_apply_scope($where, $params, 'sales_invoices', 'sales_invoices', 'p');

    $countExpr = dashboard_has_col('sales_invoices', 'customer_id') ? 'COUNT(DISTINCT p.customer_id)' : 'COUNT(*)';
    $amountExpr = dashboard_has_col('sales_invoices', 'due_amount') ? 'COALESCE(SUM(p.due_amount),0)' : '0';

    return [
        'no' => (int)dashboard_scalar("SELECT $countExpr FROM sales_invoices p WHERE $where", $params),
        'qty' => 0,
        'amount' => dashboard_scalar("SELECT $amountExpr FROM sales_invoices p WHERE $where", $params),
    ];
}

function dashboard_metric(string $label, int $no, float $qty, float $amount, string $icon, string $tone='primary'): array {
    return ['label'=>$label,'no'=>$no,'qty'=>$qty,'amount'=>$amount,'icon'=>$icon,'tone'=>$tone];
}

function dashboard_order_metric(string $label, array $dateCols, string $from, string $to, array $statuses=[], string $tone='primary'): array {
    return dashboard_metric(
        $label,
        dashboard_count_rows('sales_orders','sales_orders',$dateCols,$from,$to,$statuses),
        dashboard_sum_quantity('sales_orders','sales_orders','sales_order_items','sales_order_id',$dateCols,$from,$to,$statuses),
        dashboard_sum_parent('sales_orders','sales_orders','total_amount',$dateCols,$from,$to,$statuses),
        'bi-cart-check',
        $tone
    );
}

function dashboard_collection_metric(string $label, array $dateCols, string $from, string $to, array $statuses=[], string $tone='success'): array {
    return dashboard_metric(
        $label,
        dashboard_count_rows('collections','collections',$dateCols,$from,$to,$statuses),
        0,
        dashboard_sum_parent('collections','collections','amount',$dateCols,$from,$to,$statuses),
        'bi-cash-coin',
        $tone
    );
}

function dashboard_delivery_metric(string $label, array $dateCols, string $from, string $to, array $statuses=[], string $tone='primary'): array {
    return dashboard_metric(
        $label,
        dashboard_count_rows('delivery_challans','delivery_challans',$dateCols,$from,$to,$statuses),
        dashboard_sum_quantity('delivery_challans','delivery_challans','delivery_challan_items','delivery_challan_id',$dateCols,$from,$to,$statuses),
        dashboard_delivery_amount($dateCols,$from,$to,$statuses),
        'bi-truck',
        $tone
    );
}

function dashboard_invoice_metric(string $label, array $dateCols, string $from, string $to, array $statuses=[], string $tone='success'): array {
    return dashboard_metric(
        $label,
        dashboard_count_rows('sales_invoices','sales_invoices',$dateCols,$from,$to,$statuses),
        dashboard_sum_quantity('sales_invoices','sales_invoices','sales_invoice_items','sales_invoice_id',$dateCols,$from,$to,$statuses),
        dashboard_sum_parent('sales_invoices','sales_invoices','total_amount',$dateCols,$from,$to,$statuses),
        'bi-receipt',
        $tone
    );
}

[$period, $from, $to, $periodLabel] = dashboard_period_range();
$role = role_slug_of_current_user();
$todayLabels = ($period === 'today');

$cards = [];
if (is_deliveryman()) {
    $cards[] = dashboard_delivery_metric('Pending Delivery', ['challan_date','delivery_date','created_at'], $from, $to, ['Pending','Assigned','In Transit'], 'primary');
    $cards[] = dashboard_delivery_metric($todayLabels ? 'Delivered Today' : 'Delivered', ['delivered_at','challan_date','created_at'], $from, $to, ['Delivered'], 'success');
} else {
    $cards[] = dashboard_order_metric($todayLabels ? "Today's Order" : 'Orders', ['order_date','created_at'], $from, $to, [], 'primary');
    $cards[] = dashboard_order_metric('Pending Order', ['order_date','created_at'], $from, $to, ['Pending Approval'], 'warning');
    $cards[] = dashboard_collection_metric('Pending Collection', ['receipt_date','created_at'], $from, $to, ['Pending'], 'warning');
    $cards[] = dashboard_delivery_metric('Pending Delivery', ['challan_date','delivery_date','created_at'], $from, $to, ['Pending','Assigned','In Transit'], 'primary');
    $cards[] = dashboard_delivery_metric($todayLabels ? 'Delivered Today' : 'Delivered', ['delivered_at','challan_date','created_at'], $from, $to, ['Delivered'], 'success');
    $cards[] = dashboard_invoice_metric($todayLabels ? 'Sales Today' : 'Sales', ['invoice_date','created_at'], $from, $to, ['Approved','Pending Approval'], 'success');
    $cards[] = dashboard_collection_metric($todayLabels ? 'Collection Today' : 'Collection', ['receipt_date','created_at'], $from, $to, ['Approved'], 'success');
    $due = dashboard_due_metric($from, $to);
    $cards[] = dashboard_metric('Total Customer Dues', $due['no'], $due['qty'], $due['amount'], 'bi-exclamation-circle', 'danger');
}

$activities = all_rows('activity_logs','1=1',[],10);
$quick = [
    ['customers.php?action=form','New Customer','customers.add'],
    ['sales_orders.php?action=form','New Order','sales_orders.add'],
    ['collections.php?action=form','Money Receipt','collections.add'],
    ['reports.php','Reports','reports.view'],
];

function dashboard_period_query(array $extra=[]): string {
    $base = $_GET;
    foreach ($extra as $k=>$v) $base[$k] = $v;
    return http_build_query($base);
}

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3>Dashboard</h3>
        <p class="text-muted mb-0"><?=e($role ? ucwords(str_replace('-',' ', $role)) : 'User')?> view, filtered by your permission and customer assignment.</p>
    </div>
    <?php if (can('approvals.view')): ?><a class="btn btn-outline-primary" href="approvals.php"><i class="bi bi-check2-square"></i> Approvals</a><?php endif; ?>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="get">
            <div class="col-md-3">
                <label class="form-label">Dashboard Period</label>
                <select class="form-select" name="period" onchange="this.form.submit()">
                    <?php foreach(dashboard_period_options() as $key=>$label): ?>
                        <option value="<?=e($key)?>" <?=$period===$key?'selected':''?>><?=e($label)?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">From</label>
                <input class="form-control" type="date" name="from" value="<?=e($from ?: today())?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">To</label>
                <input class="form-control" type="date" name="to" value="<?=e($to ?: today())?>">
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-primary" type="submit">Filter</button>
                <a class="btn btn-light" href="dashboard.php">Today</a>
            </div>
            <div class="col-12">
                <small class="text-muted">Showing: <?=e($periodLabel)?><?=($from && $to) ? ' — '.e($from).' to '.e($to) : ''?></small>
            </div>
        </form>
    </div>
</div>

<div class="row g-3 mb-4">
<?php foreach($cards as $c): ?>
    <div class="col-12 col-md-6 col-xl-3">
        <div class="card stat h-100">
            <div class="d-flex justify-content-between gap-2 mb-2">
                <div><small class="text-muted"><?=e($c['label'])?></small></div>
                <i class="bi <?=$c['icon']?> fs-2 text-<?=$c['tone']?>"></i>
            </div>
            <div class="row g-2 small">
                <div class="col-4">
                    <span class="text-muted d-block">No</span>
                    <strong class="fs-5"><?=number_format((int)$c['no'])?></strong>
                </div>
                <div class="col-4">
                    <span class="text-muted d-block">Quantity</span>
                    <strong class="fs-5"><?=money($c['qty'])?></strong>
                </div>
                <div class="col-4">
                    <span class="text-muted d-block">Amount</span>
                    <strong class="fs-5"><?=money($c['amount'])?></strong>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card mb-3"><div class="card-body">
            <h5>Quick Actions</h5>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach($quick as $q): if(!can($q[2])) continue; ?><a class="btn btn-primary" href="<?=$q[0]?>"><?=e($q[1])?></a><?php endforeach; ?>
            </div>
        </div></div>

        <?php if (!is_deliveryman()): ?>
        <div class="row g-3">
            <div class="col-md-6"><div class="card"><div class="card-body">
                <h5>Top Customers</h5>
                <?php
                $where = "si.status='Approved'";
                $params = [];
                if ($from && $to && dashboard_has_col('sales_invoices','invoice_date')) {
                    $where .= " AND DATE(si.invoice_date) BETWEEN ? AND ?";
                    $params[] = $from;
                    $params[] = $to;
                }
                apply_scoped_where($where,$params,'sales_invoices','sales_invoices','si');
                $sql = "SELECT c.customer_name, COALESCE(SUM(si.total_amount),0) total FROM sales_invoices si LEFT JOIN customers c ON c.id=si.customer_id WHERE $where GROUP BY si.customer_id ORDER BY total DESC LIMIT 5";
                $st=db()->prepare($sql); $st->execute($params);
                foreach($st->fetchAll() as $r): ?>
                    <div class="d-flex justify-content-between border-bottom py-2 small"><span><?=e($r['customer_name'] ?: 'Unknown')?></span><strong><?=money($r['total'])?></strong></div>
                <?php endforeach; ?>
            </div></div></div>
            <div class="col-md-6"><div class="card"><div class="card-body">
                <h5>Marketer-wise Sales</h5>
                <?php
                $rows = [];
                if (user_can_view_all_module('sales_invoices')) {
                    $where2 = "si.status='Approved'";
                    $params2 = [];
                    if ($from && $to && dashboard_has_col('sales_invoices','invoice_date')) {
                        $where2 .= " AND DATE(si.invoice_date) BETWEEN ? AND ?";
                        $params2[] = $from;
                        $params2[] = $to;
                    }
                    $st2 = db()->prepare("SELECT COALESCE(u.full_name,'Unassigned') marketer, COALESCE(SUM(si.total_amount),0) total FROM sales_invoices si LEFT JOIN sales_orders so ON so.id=si.sales_order_id LEFT JOIN users u ON u.id=so.marketer_id WHERE $where2 GROUP BY so.marketer_id ORDER BY total DESC LIMIT 5");
                    $st2->execute($params2);
                    $rows = $st2->fetchAll();
                }
                foreach($rows as $r): ?>
                    <div class="d-flex justify-content-between border-bottom py-2 small"><span><?=e($r['marketer'])?></span><strong><?=money($r['total'])?></strong></div>
                <?php endforeach; if(!$rows): ?><p class="text-muted small mb-0">Visible to Admin, Accounts and Manager.</p><?php endif; ?>
            </div></div></div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-4">
        <div class="card mb-3"><div class="card-body">
            <h5>Recent Activities</h5>
            <?php foreach($activities as $a):?><div class="border-bottom py-2 small"><strong><?=e($a['user_name'])?></strong> <?=e($a['action_type'])?> on <?=e($a['module'])?><br><span class="text-muted"><?=e($a['created_at'])?></span></div><?php endforeach;?>
            <?php if(!$activities): ?><p class="text-muted small mb-0">No recent activity.</p><?php endif; ?>
        </div></div>
        <div class="card"><div class="card-body">
            <h5>Approval Notifications</h5>
            <div class="d-flex justify-content-between small"><span>Orders</span><strong><?=dashboard_count_rows('sales_orders','sales_orders',['order_date','created_at'],$from,$to,['Pending Approval'])?></strong></div>
            <div class="d-flex justify-content-between small"><span>Collections</span><strong><?=dashboard_count_rows('collections','collections',['receipt_date','created_at'],$from,$to,['Pending'])?></strong></div>
        </div></div>
    </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
