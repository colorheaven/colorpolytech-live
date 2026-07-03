<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('dashboard.view');
$title='ERP Dashboard';

function metric_card($label,$value,$icon,$tone='primary'){
    return ['label'=>$label,'value'=>$value,'icon'=>$icon,'tone'=>$tone];
}

$today = today();
$role = role_slug_of_current_user();
$cards = [];

if (is_deliveryman()) {
    $cards[] = metric_card('Assigned Delivery', scoped_table_count('delivery_challans','delivery_challans',"status IN ('Pending','Assigned','In Transit')"), 'bi-truck', 'primary');
    $cards[] = metric_card('Delivered Today', scoped_table_count('delivery_challans','delivery_challans',"status='Delivered' AND DATE(delivered_at)=?",[$today]), 'bi-check2-circle', 'success');
    $cards[] = metric_card('Returned / Failed', scoped_table_count('delivery_challans','delivery_challans',"status IN ('Returned','Cancelled')"), 'bi-arrow-counterclockwise', 'warning');
} else {
    $cards[] = metric_card("Today's Orders", scoped_table_count('sales_orders','sales_orders','order_date=?',[$today]), 'bi-cart-check', 'primary');
    $cards[] = metric_card('Pending Order Approvals', scoped_table_count('sales_orders','sales_orders',"status='Pending Approval'"), 'bi-hourglass-split', 'warning');
    $cards[] = metric_card('Pending Collections', scoped_table_count('collections','collections',"status='Pending'"), 'bi-cash-coin', 'warning');
    $cards[] = metric_card('Pending Delivery', scoped_table_count('delivery_challans','delivery_challans',"status IN ('Pending','Assigned','In Transit')"), 'bi-truck', 'primary');
    $cards[] = metric_card('Delivered Today', scoped_table_count('delivery_challans','delivery_challans',"status='Delivered' AND DATE(delivered_at)=?",[$today]), 'bi-check2-circle', 'success');
    $cards[] = metric_card('Sales Today', scoped_sum_col('sales_invoices','sales_invoices','total_amount','invoice_date=? AND status IN (\'Approved\',\'Pending Approval\')',[$today]), 'bi-receipt', 'success');
    $cards[] = metric_card('Collection Today', scoped_sum_col('collections','collections','amount','receipt_date=? AND status=\'Approved\'',[$today]), 'bi-wallet2', 'success');
    $cards[] = metric_card('Total Due', scoped_sum_col('sales_invoices','sales_invoices','due_amount',"status='Approved'"), 'bi-exclamation-circle', 'danger');}

$activities = all_rows('activity_logs','1=1',[],10);
$quick = [
    ['customers.php?action=form','New Customer','customers.add'],
    ['sales_orders.php?action=form','New Order','sales_orders.add'],
    ['collections.php?action=form','Money Receipt','collections.add'],    ['reports.php','Reports','reports.view'],
];

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h3>Dashboard</h3>
        <p class="text-muted mb-0"><?=e($role ? ucwords(str_replace('-',' ', $role)) : 'User')?> view, filtered by your permission and customer assignment.</p>
    </div>
    <?php if (can('approvals.view')): ?><a class="btn btn-outline-primary" href="approvals.php"><i class="bi bi-check2-square"></i> Approvals</a><?php endif; ?>
</div>

<div class="row g-3 mb-4">
<?php foreach($cards as $c): ?>
    <div class="col-6 col-lg-3">
        <div class="card stat h-100">
            <div class="d-flex justify-content-between gap-2">
                <div><small class="text-muted"><?=e($c['label'])?></small><h3><?=is_numeric($c['value'])?money($c['value']):e($c['value'])?></h3></div>
                <i class="bi <?=$c['icon']?> fs-2 text-<?=$c['tone']?>"></i>
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
                    $rows = db()->query("SELECT COALESCE(u.full_name,'Unassigned') marketer, COALESCE(SUM(si.total_amount),0) total FROM sales_invoices si LEFT JOIN sales_orders so ON so.id=si.sales_order_id LEFT JOIN users u ON u.id=so.marketer_id WHERE si.status='Approved' GROUP BY so.marketer_id ORDER BY total DESC LIMIT 5")->fetchAll();
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
            <div class="d-flex justify-content-between small"><span>Orders</span><strong><?=scoped_table_count('sales_orders','sales_orders',"status='Pending Approval'")?></strong></div>
            <div class="d-flex justify-content-between small"><span>Collections</span><strong><?=scoped_table_count('collections','collections',"status='Pending'")?></strong></div>
        </div></div>
    </div>
</div>
<?php include __DIR__.'/includes/footer.php'; ?>
