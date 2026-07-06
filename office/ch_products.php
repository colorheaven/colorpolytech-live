<?php
require_once __DIR__.'/includes/ch_backend.php';
require_once __DIR__.'/includes/ch_ui_components.php';
chb_require_login();
$errors=[];$rows=[];$table='products';
try{
    chb_db();
    if(!chb_table_exists($table)){
        foreach(['product','items','stock_items','materials','tbl_products'] as $t){ if(chb_table_exists($t)){ $table=$t; break; } }
    }
    if(chb_table_exists($table)){
        $name=chb_pick_col($table,['product_name','name','description','item_name']);
        $code=chb_pick_col($table,['product_code','unique_code','code','sku']);
        $category=chb_pick_col($table,['category','category_name','category_id']);
        $grade=chb_pick_col($table,['grade','model','type']);
        $unit=chb_pick_col($table,['unit','unit_name','unit_id']);
        $stock=chb_pick_col($table,['current_stock','stock','opening_stock','qty']);
        $rate=chb_pick_col($table,['sales_rate','sale_price','price','rate']);
        $status=chb_pick_col($table,['status']);
        $deleted=chb_pick_col($table,['deleted_at']);
        $where='1=1';$params=[];$q=trim($_GET['q']??'');
        if($deleted)$where.=" AND `$deleted` IS NULL";
        if($q!=='' && $name){$parts=[];foreach(array_filter([$name,$code,$category,$grade]) as $c){$parts[]="`$c` LIKE ?";$params[]="%$q%";}if($parts)$where.=' AND ('.implode(' OR ',$parts).')';}
        $order=$name?:'id';$st=chb_db()->prepare("SELECT * FROM `$table` WHERE $where ORDER BY `$order` ASC LIMIT 300");$st->execute($params);$rows=$st->fetchAll();
    } else { $errors[]='Product table not found in connected database.'; }
}catch(Throwable $e){$errors[]=$e->getMessage();}
ch_begin_app('products','Products');
foreach($errors as $e) echo '<div class="alert alert-warning">'.chb_e($e).'</div>';
?>
<div class="ch-card"><div class="ch-card-header"><div><h3>Product List</h3><p>Existing database products are shown below. Product search in Sales Order uses this same data.</p></div><span class="badge text-bg-success">Real Data</span></div>
<form class="row g-2 mb-3" method="get"><div class="col-md-9"><input name="q" class="form-control" placeholder="Search product, code, grade, category" value="<?=chb_e($_GET['q']??'')?>"></div><div class="col-md-3"><button class="btn btn-ch-primary w-100">Search</button></div></form>
<div class="table-responsive"><table class="table ch-table align-middle"><thead><tr><th>ID</th><th>Product</th><th>Code</th><th>Category</th><th>Grade</th><th>Unit</th><th class="text-end">Stock</th><th class="text-end">Sales Rate</th><th>Status</th></tr></thead><tbody>
<?php foreach($rows as $r): ?>
<tr><td><?=chb_e($r['id']??'')?></td><td><strong><?=chb_e($name?($r[$name]??''):'')?></strong></td><td><?=chb_e($code?($r[$code]??''):'')?></td><td><?=chb_e($category?($r[$category]??''):'')?></td><td><?=chb_e($grade?($r[$grade]??''):'')?></td><td><?=chb_e($unit?($r[$unit]??''):'')?></td><td class="text-end"><?=ch_money($stock?($r[$stock]??0):0)?></td><td class="text-end"><?=ch_money($rate?($r[$rate]??0):0)?></td><td><span class="badge text-bg-<?=strtolower((string)($status?($r[$status]??'active'):'active'))==='active'?'success':'secondary'?>"><?=chb_e($status?($r[$status]??'Active'):'Active')?></span></td></tr>
<?php endforeach; if(!$rows): ?><tr><td colspan="9" class="text-center text-muted py-4">No product data found.</td></tr><?php endif; ?>
</tbody></table></div></div>
<?php ch_end_app(); ?>
