<?php
require_once __DIR__.'/includes/ch_backend.php';
require_once __DIR__.'/includes/ch_ui_components.php';
chb_require_login();
$errors=[];$rows=[];$table='suppliers';
try{
    chb_db();
    if(!chb_table_exists($table)){
        foreach(['supplier','vendors','tbl_suppliers','parties'] as $t){ if(chb_table_exists($t)){ $table=$t; break; } }
    }
    if(chb_table_exists($table)){
        $name=chb_pick_col($table,['supplier_name','company_name','name','party_name']);
        $mobile=chb_pick_col($table,['mobile','phone','contact_number','supplier_mobile']);
        $email=chb_pick_col($table,['email','supplier_email']);
        $address=chb_pick_col($table,['address','supplier_address']);
        $balance=chb_pick_col($table,['balance','current_balance','payable','opening_balance']);
        $status=chb_pick_col($table,['status']);
        $deleted=chb_pick_col($table,['deleted_at']);
        $where='1=1';$params=[];$q=trim($_GET['q']??'');
        if($deleted)$where.=" AND `$deleted` IS NULL";
        if($q!=='' && $name){$parts=[];foreach(array_filter([$name,$mobile,$email]) as $c){$parts[]="`$c` LIKE ?";$params[]="%$q%";}if($parts)$where.=' AND ('.implode(' OR ',$parts).')';}
        $order=$name?:'id';$st=chb_db()->prepare("SELECT * FROM `$table` WHERE $where ORDER BY `$order` ASC LIMIT 200");$st->execute($params);$rows=$st->fetchAll();
    } else { $errors[]='Supplier table not found in connected database.'; }
}catch(Throwable $e){$errors[]=$e->getMessage();}
ch_begin_app('suppliers','Suppliers');
foreach($errors as $e) echo '<div class="alert alert-warning">'.chb_e($e).'</div>';
?>
<div class="ch-card"><div class="ch-card-header"><div><h3>Supplier List</h3><p>Existing database suppliers are shown below.</p></div><span class="badge text-bg-success">Real Data</span></div>
<form class="row g-2 mb-3" method="get"><div class="col-md-9"><input name="q" class="form-control" placeholder="Search supplier, mobile, email" value="<?=chb_e($_GET['q']??'')?>"></div><div class="col-md-3"><button class="btn btn-ch-primary w-100">Search</button></div></form>
<div class="table-responsive"><table class="table ch-table align-middle"><thead><tr><th>ID</th><th>Supplier</th><th>Mobile</th><th>Email</th><th class="text-end">Balance</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr><td><?=chb_e($r['id']??'')?></td><td><strong><?=chb_e($name?($r[$name]??''):'')?></strong><br><small><?=chb_e($address?($r[$address]??''):'')?></small></td><td><?=chb_e($mobile?($r[$mobile]??''):'')?></td><td><?=chb_e($email?($r[$email]??''):'')?></td><td class="text-end"><?=ch_money($balance?($r[$balance]??0):0)?></td><td><span class="badge text-bg-<?=strtolower((string)($status?($r[$status]??'active'):'active'))==='active'?'success':'secondary'?>"><?=chb_e($status?($r[$status]??'Active'):'Active')?></span></td><td class="text-end"><button class="btn btn-sm btn-light">View</button><button class="btn btn-sm btn-outline-primary">Edit</button></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="7" class="text-center text-muted py-4">No supplier data found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php ch_end_app(); ?>
