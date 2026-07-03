<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('customers.view');
$title = 'Customers';

function customers_cols(): array {
    try { return table_exists('customers') ? table_columns('customers') : []; } catch (Throwable $e) { return []; }
}
function customers_has_col(string $col): bool { return in_array($col, customers_cols(), true); }
function customers_pick_col(array $candidates): string { foreach($candidates as $c) if(customers_has_col($c)) return $c; return ''; }

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$nameCol = customers_pick_col(['customer_name','company_name','name']);
$mobileCol = customers_pick_col(['mobile','phone','contact_number']);
$emailCol = customers_pick_col(['email']);
$areaCol = customers_pick_col(['area','district']);
$statusCol = customers_pick_col(['status']);

$where = '1=1';
$params = [];
if ($q !== '') {
    $searchParts = [];
    foreach (array_filter([$nameCol,$mobileCol,$emailCol,$areaCol]) as $col) {
        $searchParts[] = "`$col` LIKE ?";
        $params[] = "%$q%";
    }
    if ($searchParts) $where .= ' AND ('.implode(' OR ', $searchParts).')';
}
try { apply_scoped_where($where, $params, 'customers', 'customers', 'c'); } catch (Throwable $e) {}

$total = 0;
$customers = [];
if (table_exists('customers')) {
    $countSql = "SELECT COUNT(*) FROM customers c WHERE $where";
    $st = db()->prepare($countSql);
    $st->execute($params);
    $total = (int)$st->fetchColumn();

    $orderCol = $nameCol ?: 'id';
    $sql = "SELECT c.* FROM customers c WHERE $where ORDER BY c.`$orderCol` LIMIT $limit OFFSET $offset";
    $st = db()->prepare($sql);
    $st->execute($params);
    $customers = $st->fetchAll();
}

function customer_due_amount(int $customerId): float {
    if (!table_exists('sales_invoices')) return 0;
    $cols = table_columns('sales_invoices');
    if (!in_array('customer_id', $cols, true)) return 0;
    $amountCol = in_array('due_amount', $cols, true) ? 'due_amount' : (in_array('total_amount', $cols, true) ? 'total_amount' : '');
    if (!$amountCol) return 0;
    $where = "customer_id=?";
    $params = [$customerId];
    if (in_array('status', $cols, true)) $where .= " AND status='Approved'";
    $st = db()->prepare("SELECT COALESCE(SUM(`$amountCol`),0) FROM sales_invoices WHERE $where");
    $st->execute($params);
    return (float)$st->fetchColumn();
}

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3>Customers</h3><p class="text-muted mb-0">Customer list with statement, ledger and due view.</p></div>
    <?php if (can('customers.add')): ?><a class="btn btn-primary" href="module_form.php?m=customers"><i class="bi bi-plus-lg"></i> New Customer</a><?php endif; ?>
</div>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-md-10"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search customer, mobile, email, area..."></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Customer</th><th>Mobile</th><th>Area</th><th>Status</th><th class="text-end">Due</th><th class="text-end">Action</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): $cid=(int)$c['id']; ?>
        <tr>
            <td><strong><?=e($nameCol ? ($c[$nameCol] ?? '') : ('Customer #'.$cid))?></strong><br><small class="text-muted"><?=e($emailCol ? ($c[$emailCol] ?? '') : '')?></small></td>
            <td><?=e($mobileCol ? ($c[$mobileCol] ?? '') : '')?></td>
            <td><?=e($areaCol ? ($c[$areaCol] ?? '') : '')?></td>
            <td><?=e($statusCol ? ($c[$statusCol] ?? '') : '')?></td>
            <td class="text-end"><?=money(customer_due_amount($cid))?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="customer_statement.php?customer_id=<?=$cid?>">Statement</a>
                <?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="module_form.php?m=customers&id=<?=$cid?>">Edit</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$customers): ?><tr><td colspan="6" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php if ($total > $limit): $pages = (int)ceil($total / $limit); ?>
<nav class="mt-3"><ul class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?><li class="page-item <?=$i===$page?'active':''?>"><a class="page-link" href="customers.php?q=<?=urlencode($q)?>&page=<?=$i?>"><?=$i?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
