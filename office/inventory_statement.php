<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('inventory_movements.view');
$title = 'Inventory Statement';

function is_cols(string $table): array { try { return table_exists($table) ? table_columns($table) : []; } catch (Throwable $e) { return []; } }
function is_has(string $table, string $col): bool { return in_array($col, is_cols($table), true); }
function is_pick(string $table, array $cols): string { $all = is_cols($table); foreach($cols as $c) if(in_array($c,$all,true)) return $c; return ''; }
function is_qty($v): string { return number_format((float)$v, 3); }
function is_date($v): string { $ts=strtotime((string)$v); return $ts?date('d-M-y',$ts):(string)$v; }
function is_product_name($id): string { return vp_product_name((int)$id); }

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
$productId = (int)($_GET['product_id'] ?? 0);
$movementType = trim((string)($_GET['movement_type'] ?? ''));
$export = strtolower(trim((string)($_GET['export'] ?? '')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to=date('Y-m-d');
if($from>$to){$x=$from;$from=$to;$to=$x;}

$dateCol = is_pick('inventory_movements',['movement_date','date','created_at']);
$productCol = is_pick('inventory_movements',['product_id']);
$typeCol = is_pick('inventory_movements',['movement_type','type']);
$refTypeCol = is_pick('inventory_movements',['reference_type']);
$refIdCol = is_pick('inventory_movements',['reference_id']);
$qtyInCol = is_pick('inventory_movements',['quantity_in','qty_in']);
$qtyOutCol = is_pick('inventory_movements',['quantity_out','qty_out']);
$rateCol = is_pick('inventory_movements',['rate']);
$noteCol = is_pick('inventory_movements',['narration','remarks','notes']);
$warehouseCol = is_pick('inventory_movements',['warehouse_id']);

function is_opening_balance($productId, $from, $dateCol, $productCol, $qtyInCol, $qtyOutCol): float {
    if(!$productId || !$dateCol || !$productCol || !table_exists('inventory_movements')) return 0;
    $inExpr = $qtyInCol ? "COALESCE(SUM(`$qtyInCol`),0)" : '0';
    $outExpr = $qtyOutCol ? "COALESCE(SUM(`$qtyOutCol`),0)" : '0';
    $st = db()->prepare("SELECT ($inExpr - $outExpr) FROM inventory_movements WHERE `$productCol`=? AND DATE(`$dateCol`) < ?");
    $st->execute([$productId,$from]);
    return (float)$st->fetchColumn();
}

$where='1=1';$params=[];
if($dateCol){$where.=" AND DATE(`$dateCol`) BETWEEN ? AND ?";$params[]=$from;$params[]=$to;}
if($productId && $productCol){$where.=" AND `$productCol`=?";$params[]=$productId;}
if($movementType!=='' && $typeCol){$where.=" AND `$typeCol`=?";$params[]=$movementType;}
$rows=[];$totalIn=0;$totalOut=0;$opening=is_opening_balance($productId,$from,$dateCol,$productCol,$qtyInCol,$qtyOutCol);$balance=$opening;
if(table_exists('inventory_movements')){
    $order = $dateCol ? "`$dateCol`, id" : 'id';
    $st=db()->prepare("SELECT * FROM inventory_movements WHERE $where ORDER BY $order");
    $st->execute($params);
    foreach($st->fetchAll() as $r){
        $qin=$qtyInCol?(float)($r[$qtyInCol]??0):0;
        $qout=$qtyOutCol?(float)($r[$qtyOutCol]??0):0;
        $totalIn += $qin; $totalOut += $qout; $balance += $qin - $qout;
        $r['_qty_in']=$qin; $r['_qty_out']=$qout; $r['_balance']=$balance;
        $rows[]=$r;
    }
}

if($export==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="inventory_statement_'.date('Ymd_His').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['Date','Product','Movement Type','Reference','Qty In','Qty Out','Balance','Rate','Remarks']);
    foreach($rows as $r){fputcsv($out,[
        $dateCol?($r[$dateCol]??''):'',
        $productCol?is_product_name($r[$productCol]??0):'',
        $typeCol?($r[$typeCol]??''):'',
        trim(($refTypeCol?($r[$refTypeCol]??''):'').' '.($refIdCol?($r[$refIdCol]??''):'')),
        $r['_qty_in'],$r['_qty_out'],$r['_balance'],$rateCol?($r[$rateCol]??0):0,$noteCol?($r[$noteCol]??''):'']);}
    fclose($out);exit;
}

$products=[];
if(table_exists('products')){
    $pName=is_pick('products',['product_name','name','description']);
    if($pName){$products=db()->query("SELECT id, `$pName` name FROM products ORDER BY `$pName` LIMIT 500")->fetchAll();}
}
$types=[];
if($typeCol && table_exists('inventory_movements')){try{$types=db()->query("SELECT DISTINCT `$typeCol` t FROM inventory_movements WHERE `$typeCol` IS NOT NULL AND `$typeCol`<>'' ORDER BY `$typeCol`")->fetchAll();}catch(Throwable $e){$types=[];}}
include __DIR__.'/includes/header.php';
?>
<style>@media print{.no-print,.sidebar,.topbar{display:none!important}.main,.content{margin:0!important;padding:0!important}body{background:#fff!important}.card{border:0!important}.table{font-size:11px}}</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print"><div><h3>Inventory Statement</h3><p class="text-muted mb-0">Period-wise stock movement statement with running balance.</p></div><div class="d-flex gap-2"><button class="btn btn-primary" onclick="window.print()">Print / PDF</button><a class="btn btn-outline-success" href="inventory_statement.php?from=<?=e($from)?>&to=<?=e($to)?>&product_id=<?=$productId?>&movement_type=<?=urlencode($movementType)?>&export=csv">Export CSV</a><a class="btn btn-light" href="inventory_movements.php">Back</a></div></div>
<div class="card mb-3 no-print"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?=e($from)?>"></div><div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?=e($to)?>"></div><div class="col-md-4"><label class="form-label">Product</label><select class="form-select" name="product_id"><option value="0">All Products</option><?php foreach($products as $p):?><option value="<?=$p['id']?>" <?=$productId==(int)$p['id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach;?></select></div><div class="col-md-2"><label class="form-label">Movement Type</label><select class="form-select" name="movement_type"><option value="">All</option><?php foreach($types as $t):?><option value="<?=e($t['t'])?>" <?=$movementType===$t['t']?'selected':''?>><?=e($t['t'])?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div></form></div></div>
<div class="card"><div class="card-body"><div class="text-center mb-3"><h4><?=e(setting('company_name','Color Heaven'))?></h4><strong>Inventory Statement</strong><br><span><?=e(is_date($from))?> to <?=e(is_date($to))?></span><?php if($productId):?><br><strong><?=e(is_product_name($productId))?></strong><?php endif;?></div><div class="row g-2 mb-3"><div class="col-md-3"><div class="border p-2"><small>Opening</small><h5><?=is_qty($opening)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Total In</small><h5><?=is_qty($totalIn)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Total Out</small><h5><?=is_qty($totalOut)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Closing</small><h5><?=is_qty($balance)?></h5></div></div></div><div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead><tr><th>Date</th><th>Product</th><th>Movement Type</th><th>Reference</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th><th class="text-end">Balance</th><th class="text-end">Rate</th><th>Remarks</th><th class="no-print">Voucher</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($dateCol?is_date($r[$dateCol]??''):'')?></td><td><?=e($productCol?is_product_name($r[$productCol]??0):'')?></td><td><?=e($typeCol?($r[$typeCol]??''):'')?></td><td><?=e(trim(($refTypeCol?($r[$refTypeCol]??''):'').' '.($refIdCol?($r[$refIdCol]??''):'')))?></td><td class="text-end"><?=is_qty($r['_qty_in'])?></td><td class="text-end"><?=is_qty($r['_qty_out'])?></td><td class="text-end"><?=is_qty($r['_balance'])?></td><td class="text-end"><?=e($rateCol?number_format((float)($r[$rateCol]??0),2):'')?></td><td><?=e($noteCol?($r[$noteCol]??''):'')?></td><td class="no-print"><a class="btn btn-sm btn-outline-secondary" href="voucher.php?module=inventory_movements&id=<?=$r['id']?>">Voucher</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10" class="text-center text-muted">No stock movement found.</td></tr><?php endif;?></tbody></table></div></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
