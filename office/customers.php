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
function customers_existing_data(array $data): array { return array_intersect_key($data, array_flip(customers_cols())); }
function customers_clean_mobile(string $mobile): string { return preg_replace('/[^0-9+]/', '', trim($mobile)) ?: ''; }

$nameCol = customers_pick_col(['customer_name','name','company_name']);
$codeCol = customers_pick_col(['customer_code','code','customer_no']);
$addressCol = customers_pick_col(['address']);
$contactPersonCol = customers_pick_col(['contact_person','contact_name']);
$mobileCol = customers_pick_col(['contact_number','mobile','phone']);
$smsMobileCol = customers_pick_col(['sms_number','contact_number','mobile','phone']);
$emailCol = customers_pick_col(['email']);
$areaCol = customers_pick_col(['area','district']);
$statusCol = customers_pick_col(['status']);
$openingCol = customers_pick_col(['opening_balance']);

$action = $_GET['action'] ?? 'list';
$msg = '';
$error = '';

try {
    if ($action === 'delete') {
        check_csrf();
        require_perm('customers.delete');
        $id = (int)($_GET['id'] ?? 0);
        if (!$id) throw new Exception('Invalid customer.');

        $linkedTables = [
            'sales_orders' => 'customer_id',
            'delivery_challans' => 'customer_id',
            'sales_invoices' => 'customer_id',
            'collections' => 'customer_id',
            'crm_followups' => 'customer_id',
        ];
        foreach ($linkedTables as $table => $fk) {
            if (table_exists($table) && in_array($fk, table_columns($table), true) && table_count($table, "$fk=?", [$id]) > 0) {
                throw new Exception('This customer has transactions. Delete is blocked to keep old data safe. You can set status inactive instead.');
            }
        }

        $st = db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $old = $st->fetch();
        if (!$old) throw new Exception('Customer not found.');
        db()->prepare('DELETE FROM customers WHERE id=? LIMIT 1')->execute([$id]);
        log_action('Customers','delete',$old,'',$id);
        header('Location: customers.php?msg=deleted'); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        check_csrf();
        $id = (int)($_POST['id'] ?? 0);
        require_perm('customers.'.($id ? 'edit' : 'add'));
        if (!$nameCol) throw new Exception('Customer name column not found. Please run/review migration first.');

        $data = [];
        $data[$nameCol] = trim((string)($_POST['customer_name'] ?? ''));
        if ($data[$nameCol] === '') throw new Exception('Customer name is required.');
        if ($codeCol) $data[$codeCol] = trim((string)($_POST['customer_code'] ?? ''));
        if ($addressCol) $data[$addressCol] = trim((string)($_POST['address'] ?? ''));
        if ($contactPersonCol) $data[$contactPersonCol] = trim((string)($_POST['contact_person'] ?? ''));
        if ($mobileCol) $data[$mobileCol] = customers_clean_mobile((string)($_POST['contact_number'] ?? ''));
        if ($smsMobileCol) $data[$smsMobileCol] = customers_clean_mobile((string)($_POST['sms_number'] ?? ($_POST['contact_number'] ?? '')));
        if ($emailCol) $data[$emailCol] = trim((string)($_POST['email'] ?? ''));
        if ($areaCol) $data[$areaCol] = trim((string)($_POST['area'] ?? ''));
        if ($openingCol) $data[$openingCol] = (float)($_POST['opening_balance'] ?? 0);
        if ($statusCol) $data[$statusCol] = trim((string)($_POST['status'] ?? 'active'));
        if (customers_has_col('updated_at')) $data['updated_at'] = date('Y-m-d H:i:s');
        $data = customers_existing_data($data);

        if ($id) {
            $st = db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
            $st->execute([$id]);
            $old = $st->fetch();
            if (!$old) throw new Exception('Customer not found.');
            $sets = [];
            foreach ($data as $k=>$v) $sets[] = "`$k`=?";
            db()->prepare('UPDATE customers SET '.implode(',', $sets).' WHERE id=? LIMIT 1')->execute(array_merge(array_values($data), [$id]));
            log_action('Customers','edit',$old,$data,$id);
            header('Location: customers.php?msg=updated'); exit;
        } else {
            if (customers_has_col('created_at')) $data['created_at'] = date('Y-m-d H:i:s');
            $keys = array_keys($data);
            db()->prepare('INSERT INTO customers (`'.implode('`,`',$keys).'`) VALUES ('.implode(',', array_fill(0, count($keys), '?')).')')->execute(array_values($data));
            $id = (int)db()->lastInsertId();
            log_action('Customers','add','',$data,$id);
            header('Location: customers.php?msg=created'); exit;
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
}

$editCustomer = null;
if (in_array($action, ['add','edit'], true)) {
    require_perm('customers.'.($action === 'edit' ? 'edit' : 'add'));
    if ($action === 'edit') {
        $id = (int)($_GET['id'] ?? 0);
        $st = db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
        $st->execute([$id]);
        $editCustomer = $st->fetch();
        if (!$editCustomer) { $error = 'Customer not found.'; $action = 'list'; }
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;
$where = '1=1';
$params = [];
if ($q !== '') {
    $searchParts = [];
    foreach (array_filter([$nameCol,$codeCol,$mobileCol,$smsMobileCol,$emailCol,$areaCol,$contactPersonCol]) as $col) {
        $searchParts[] = "c.`$col` LIKE ?";
        $params[] = "%$q%";
    }
    if ($searchParts) $where .= ' AND ('.implode(' OR ', $searchParts).')';
}
try { apply_scoped_where($where, $params, 'customers', 'customers', 'c'); } catch (Throwable $e) {}

$total = 0;
$customers = [];
if (table_exists('customers')) {
    $st = db()->prepare("SELECT COUNT(*) FROM customers c WHERE $where");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $orderCol = $nameCol ?: 'id';
    $st = db()->prepare("SELECT c.* FROM customers c WHERE $where ORDER BY c.`$orderCol` LIMIT $limit OFFSET $offset");
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

function customer_val(?array $c, string $col, $default='') { return $c && $col ? ($c[$col] ?? $default) : $default; }

include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div><h3>Customers</h3><p class="text-muted mb-0">Customer details, SMS contact number, opening balance, statement and due view.</p></div>
    <?php if (can('customers.add')): ?><a class="btn btn-primary" href="customers.php?action=add"><i class="bi bi-plus-lg"></i> New Customer</a><?php endif; ?>
</div>
<?php if ($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
<?php if (!empty($_GET['msg'])): ?><div class="alert alert-success">Customer <?=e($_GET['msg'])?> successfully.</div><?php endif; ?>

<?php if (in_array($action, ['add','edit'], true)): ?>
<div class="card mb-3"><div class="card-body">
    <h5><?= $action === 'edit' ? 'Edit Customer' : 'New Customer' ?></h5>
    <form method="post" class="row g-3">
        <input type="hidden" name="csrf" value="<?=csrf()?>">
        <input type="hidden" name="id" value="<?=e($editCustomer['id'] ?? 0)?>">
        <div class="col-md-4"><label class="form-label">Customer Name *</label><input class="form-control" name="customer_name" value="<?=e(customer_val($editCustomer,$nameCol))?>" required></div>
        <div class="col-md-2"><label class="form-label">Customer Code</label><input class="form-control" name="customer_code" value="<?=e(customer_val($editCustomer,$codeCol))?>" placeholder="Auto/Manual"></div>
        <div class="col-md-3"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="<?=e(customer_val($editCustomer,$contactPersonCol))?>"></div>
        <div class="col-md-3"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number" value="<?=e(customer_val($editCustomer,$mobileCol))?>" placeholder="For call"></div>
        <div class="col-md-3"><label class="form-label">SMS Number</label><input class="form-control" name="sms_number" value="<?=e(customer_val($editCustomer,$smsMobileCol, customer_val($editCustomer,$mobileCol)))?>" placeholder="For SMS"></div>
        <div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e(customer_val($editCustomer,$emailCol))?>"></div>
        <div class="col-md-3"><label class="form-label">Area/District</label><input class="form-control" name="area" value="<?=e(customer_val($editCustomer,$areaCol))?>"></div>
        <div class="col-md-3"><label class="form-label">Opening Balance</label><input class="form-control" type="number" step="0.01" name="opening_balance" value="<?=e(customer_val($editCustomer,$openingCol,0))?>"></div>
        <div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=customer_val($editCustomer,$statusCol,'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=customer_val($editCustomer,$statusCol)==='inactive'?'selected':''?>>Inactive</option></select></div>
        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?=e(customer_val($editCustomer,$addressCol))?></textarea></div>
        <div class="col-12"><button class="btn btn-primary">Save Customer</button> <a class="btn btn-light" href="customers.php">Cancel</a></div>
    </form>
</div></div>
<?php endif; ?>

<div class="card mb-3"><div class="card-body">
    <form class="row g-2" method="get">
        <div class="col-md-10"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search customer name, code, contact person, mobile, SMS number, email, area..."></div>
        <div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div>
    </form>
</div></div>

<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
    <thead><tr><th>Code</th><th>Customer</th><th>Contact</th><th>Address</th><th class="text-end">Opening</th><th class="text-end">Due</th><th class="text-end">Action</th></tr></thead>
    <tbody>
    <?php foreach ($customers as $c): $cid=(int)$c['id']; ?>
        <tr>
            <td><?=e($codeCol ? ($c[$codeCol] ?? '') : $cid)?></td>
            <td><strong><?=e($nameCol ? ($c[$nameCol] ?? '') : ('Customer #'.$cid))?></strong><br><small class="text-muted"><?=e($statusCol ? ($c[$statusCol] ?? '') : '')?> <?=e($emailCol ? ($c[$emailCol] ?? '') : '')?></small></td>
            <td><?=e($contactPersonCol ? ($c[$contactPersonCol] ?? '') : '')?><br><small class="text-muted">Call: <?=e($mobileCol ? ($c[$mobileCol] ?? '') : '')?> | SMS: <?=e($smsMobileCol ? ($c[$smsMobileCol] ?? '') : '')?></small></td>
            <td><small><?=e($addressCol ? ($c[$addressCol] ?? '') : '')?></small></td>
            <td class="text-end"><?=money($openingCol ? ($c[$openingCol] ?? 0) : 0)?></td>
            <td class="text-end"><?=money(customer_due_amount($cid))?></td>
            <td class="text-end text-nowrap">
                <a class="btn btn-sm btn-outline-primary" href="customer_statement.php?customer_id=<?=$cid?>">Statement</a>
                <?php if (can('customers.edit')): ?><a class="btn btn-sm btn-outline-secondary" href="customers.php?action=edit&id=<?=$cid?>">Edit</a><?php endif; ?>
                <?php if (can('customers.delete')): ?><a class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this customer? Delete is blocked automatically if transaction exists.')" href="customers.php?action=delete&id=<?=$cid?>&csrf=<?=csrf()?>">Delete</a><?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (!$customers): ?><tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr><?php endif; ?>
    </tbody>
</table></div></div>

<?php if ($total > $limit): $pages = (int)ceil($total / $limit); ?>
<nav class="mt-3"><ul class="pagination">
    <?php for($i=1;$i<=$pages;$i++): ?><li class="page-item <?=$i===$page?'active':''?>"><a class="page-link" href="customers.php?q=<?=urlencode($q)?>&page=<?=$i?>"><?=$i?></a></li><?php endfor; ?>
</ul></nav>
<?php endif; ?>

<?php include __DIR__.'/includes/footer.php'; ?>
