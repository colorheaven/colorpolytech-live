<?php
require_once __DIR__.'/includes/ch_backend.php';
require_once __DIR__.'/includes/ch_ui_components.php';
chb_require_login();
$groups = [
  'Customers' => ['customers','customer','tbl_customers','clients','parties'],
  'Products' => ['products','product','items','stock_items','materials','tbl_products'],
  'Suppliers' => ['suppliers','supplier','vendors','tbl_suppliers','parties'],
  'Sales Orders' => ['sales_orders'],
  'Sales Order Items' => ['sales_order_items'],
];
$rows=[];$errors=[];
try{
  chb_db();
  foreach($groups as $label=>$tables){
    foreach($tables as $t){
      if(chb_table_exists($t)){
        $cnt=0;try{$cnt=(int)chb_db()->query("SELECT COUNT(*) FROM `$t`")->fetchColumn();}catch(Throwable $e){}
        $rows[]=['label'=>$label,'table'=>$t,'count'=>$cnt,'columns'=>implode(', ', array_slice(chb_columns($t),0,12))];
        continue 2;
      }
    }
    $rows[]=['label'=>$label,'table'=>'Not found','count'=>'-','columns'=>''];
  }
}catch(Throwable $e){$errors[]=$e->getMessage();}
ch_begin_app('settings','Data Check');
foreach($errors as $e) echo '<div class="alert alert-danger">'.chb_e($e).'</div>';
?>
<div class="ch-card"><div class="ch-card-header"><div><h3>ERP Data Check</h3><p>Shows detected live database tables and row counts. No password or secret is displayed.</p></div><span class="badge text-bg-info">Diagnostic</span></div>
<div class="table-responsive"><table class="table ch-table"><thead><tr><th>Module</th><th>Detected Table</th><th class="text-end">Rows</th><th>Sample Columns</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><strong><?=chb_e($r['label'])?></strong></td><td><?=chb_e($r['table'])?></td><td class="text-end"><?=chb_e($r['count'])?></td><td><small><?=chb_e($r['columns'])?></small></td></tr><?php endforeach; ?></tbody></table></div>
<a class="btn btn-ch-primary" href="customers.php">Customers</a> <a class="btn btn-outline-primary" href="products.php">Products</a> <a class="btn btn-outline-secondary" href="sales_orders.php">Sales Orders</a>
</div><?php ch_end_app(); ?>
