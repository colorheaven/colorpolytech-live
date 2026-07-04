<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('inventory_movements.view');
$title = 'Inventory Statement';

function is_cols(string $table): array { try { return table_exists($table) ? table_columns($table) : []; } catch (Throwable $e) { return []; } }
function is_pick(string $table, array $cols): string { $all=is_cols($table); foreach($cols as $c){ if(in_array($c,$all,true)) return $c; } return ''; }
function is_qty($v): string { return number_format((float)$v, 3); }
function is_date($v): string { $ts=strtotime((string)$v); return $ts?date('d-M-y',$ts):(string)$v; }

function is_product_name_col(): string { return is_pick('products',['product_name','name','description']); }
function is_product_label($raw): string {
    $raw = trim((string)$raw);
    if ($raw === '') return '';
    if (!table_exists('products')) return $raw;
    $name = is_product_name_col();
    if (!$name) return $raw;
    $code = is_pick('products',['product_code','unique_code','code']);
    $sku = is_pick('products',['sku']);
    try {
        if (ctype_digit($raw)) {
            $st = db()->prepare("SELECT `$name` FROM products WHERE id=? LIMIT 1");
            $st->execute([(int)$raw]);
            $found = $st->fetchColumn();
            if ($found) return (string)$found;
        }
        $where = "`$name`=?"; $params = [$raw];
        if ($code) { $where .= " OR `$code`=?"; $params[] = $raw; }
        if ($sku) { $where .= " OR `$sku`=?"; $params[] = $raw; }
        $st = db()->prepare("SELECT `$name` FROM products WHERE ($where) LIMIT 1");
        $st->execute($params);
        $found = $st->fetchColumn();
        return $found ? (string)$found : $raw;
    } catch (Throwable $e) { return $raw; }
}

function is_matching_product_values(string $q): array {
    $q = trim($q);
    if ($q === '' || !table_exists('products')) return [];
    $name = is_product_name_col();
    if (!$name) return [];
    $code = is_pick('products',['product_code','unique_code','code']);
    $sku = is_pick('products',['sku']);
    $where = "`$name` LIKE ?"; $params = ["%$q%"];
    if ($code) { $where .= " OR `$code` LIKE ?"; $params[] = "%$q%"; }
    if ($sku) { $where .= " OR `$sku` LIKE ?"; $params[] = "%$q%"; }
    $values = [];
    try {
        $st = db()->prepare("SELECT id, `$name` name".($code?", `$code` code":"").($sku?", `$sku` sku":"")." FROM products WHERE ($where) LIMIT 30");
        $st->execute($params);
        foreach($st->fetchAll() as $r){
            $values[] = (string)$r['id'];
            if (!empty($r['name'])) $values[] = (string)$r['name'];
            if (!empty($r['code'])) $values[] = (string)$r['code'];
            if (!empty($r['sku'])) $values[] = (string)$r['sku'];
        }
    } catch (Throwable $e) {}
    return array_values(array_unique(array_filter($values, fn($v)=>trim((string)$v)!=='')));
}

function is_apply_product_filter(string &$where, array &$params, string $productCol, string $productSearch): void {
    $productSearch = trim($productSearch);
    if (!$productCol || $productSearch === '') return;
    $parts = ["`$productCol` LIKE ?"];
    $params[] = "%$productSearch%";
    $values = is_matching_product_values($productSearch);
    if ($values) {
        $parts[] = "`$productCol` IN (".implode(',', array_fill(0,count($values),'?')).")";
        foreach($values as $v) $params[] = $v;
    }
    $where .= ' AND ('.implode(' OR ', $parts).')';
}

function is_opening_balance(string $productSearch, string $from, string $dateCol, string $productCol, string $qtyInCol, string $qtyOutCol): float {
    if(!$dateCol || !$productCol || !table_exists('inventory_movements')) return 0;
    $where = "DATE(`$dateCol`) < ?"; $params = [$from];
    is_apply_product_filter($where, $params, $productCol, $productSearch);
    $inExpr = $qtyInCol ? "COALESCE(SUM(`$qtyInCol`),0)" : '0';
    $outExpr = $qtyOutCol ? "COALESCE(SUM(`$qtyOutCol`),0)" : '0';
    $st = db()->prepare("SELECT ($inExpr - $outExpr) FROM inventory_movements WHERE $where");
    $st->execute($params);
    return (float)$st->fetchColumn();
}

$from = trim((string)($_GET['from'] ?? date('Y-m-01')));
$to = trim((string)($_GET['to'] ?? date('Y-m-d')));
$productSearch = trim((string)($_GET['product_q'] ?? ''));
if ($productSearch === '' && !empty($_GET['product_id'])) $productSearch = trim((string)$_GET['product_id']);
$movementType = trim((string)($_GET['movement_type'] ?? ''));
$export = strtolower(trim((string)($_GET['export'] ?? '')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$from)) $from=date('Y-m-01');
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$to)) $to=date('Y-m-d');
if($from>$to){$x=$from;$from=$to;$to=$x;}

$dateCol = is_pick('inventory_movements',['movement_date','date','created_at']);
$productCol = is_pick('inventory_movements',['product_id','product_name','item_name']);
$typeCol = is_pick('inventory_movements',['movement_type','type']);
$refTypeCol = is_pick('inventory_movements',['reference_type']);
$refIdCol = is_pick('inventory_movements',['reference_id']);
$qtyInCol = is_pick('inventory_movements',['quantity_in','qty_in']);
$qtyOutCol = is_pick('inventory_movements',['quantity_out','qty_out']);
$rateCol = is_pick('inventory_movements',['rate']);
$noteCol = is_pick('inventory_movements',['narration','remarks','notes']);

$where='1=1';$params=[];
if($dateCol){$where.=" AND DATE(`$dateCol`) BETWEEN ? AND ?";$params[]=$from;$params[]=$to;}
is_apply_product_filter($where,$params,$productCol,$productSearch);
if($movementType!=='' && $typeCol){$where.=" AND `$typeCol`=?";$params[]=$movementType;}
$rows=[];$totalIn=0;$totalOut=0;$opening=is_opening_balance($productSearch,$from,$dateCol,$productCol,$qtyInCol,$qtyOutCol);$balance=$opening;
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
    foreach($rows as $r){fputcsv($out,[$dateCol?($r[$dateCol]??''):'',$productCol?is_product_label($r[$productCol]??''):'',$typeCol?($r[$typeCol]??''):'',trim(($refTypeCol?($r[$refTypeCol]??''):'').' '.($refIdCol?($r[$refIdCol]??''):'')),$r['_qty_in'],$r['_qty_out'],$r['_balance'],$rateCol?($r[$rateCol]??0):0,$noteCol?($r[$noteCol]??''):'']);}
    fclose($out);exit;
}

$types=[];
if($typeCol && table_exists('inventory_movements')){try{$types=db()->query("SELECT DISTINCT `$typeCol` t FROM inventory_movements WHERE `$typeCol` IS NOT NULL AND `$typeCol`<>'' ORDER BY `$typeCol`")->fetchAll();}catch(Throwable $e){$types=[];}}
include __DIR__.'/includes/header.php';
?>
<style>@media print{.no-print,.sidebar,.topbar{display:none!important}.main,.content{margin:0!important;padding:0!important}body{background:#fff!important}.card{border:0!important}.table{font-size:11px}}</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print"><div><h3>Inventory Statement</h3><p class="text-muted mb-0">Period-wise stock movement statement with running balance.</p></div><div class="d-flex gap-2"><button class="btn btn-primary" onclick="window.print()">Print / PDF</button><a class="btn btn-outline-success" href="inventory_statement.php?from=<?=e($from)?>&to=<?=e($to)?>&product_q=<?=urlencode($productSearch)?>&movement_type=<?=urlencode($movementType)?>&export=csv">Export CSV</a><a class="btn btn-light" href="inventory_movements.php">Back</a></div></div>
<div class="card mb-3 no-print"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-2"><label class="form-label">From</label><input type="date" class="form-control" name="from" value="<?=e($from)?>"></div><div class="col-md-2"><label class="form-label">To</label><input type="date" class="form-control" name="to" value="<?=e($to)?>"></div><div class="col-md-4"><label class="form-label">Product</label><input class="form-control" id="inventoryProductSearch" name="product_q" value="<?=e($productSearch)?>" list="inventoryProductSuggestions" autocomplete="off" placeholder="Type product name/code, e.g. White CMB"><datalist id="inventoryProductSuggestions"></datalist></div><div class="col-md-2"><label class="form-label">Movement Type</label><select class="form-select" name="movement_type"><option value="">All</option><?php foreach($types as $t):?><option value="<?=e($t['t'])?>" <?=$movementType===$t['t']?'selected':''?>><?=e($t['t'])?></option><?php endforeach;?></select></div><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div></form></div></div>
<div class="card"><div class="card-body"><div class="text-center mb-3"><h4><?=e(setting('company_name','Color Heaven'))?></h4><strong>Inventory Statement</strong><br><span><?=e(is_date($from))?> to <?=e(is_date($to))?></span><?php if($productSearch):?><br><strong><?=e(is_product_label($productSearch))?></strong><?php endif;?></div><div class="row g-2 mb-3"><div class="col-md-3"><div class="border p-2"><small>Opening</small><h5><?=is_qty($opening)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Total In</small><h5><?=is_qty($totalIn)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Total Out</small><h5><?=is_qty($totalOut)?></h5></div></div><div class="col-md-3"><div class="border p-2"><small>Closing</small><h5><?=is_qty($balance)?></h5></div></div></div><div class="table-responsive"><table class="table table-bordered table-sm align-middle"><thead><tr><th>Date</th><th>Product</th><th>Movement Type</th><th>Reference</th><th class="text-end">Qty In</th><th class="text-end">Qty Out</th><th class="text-end">Balance</th><th class="text-end">Rate</th><th>Remarks</th><th class="no-print">Voucher</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?=e($dateCol?is_date($r[$dateCol]??''):'')?></td><td><?=e($productCol?is_product_label($r[$productCol]??''):'')?></td><td><?=e($typeCol?($r[$typeCol]??''):'')?></td><td><?=e(trim(($refTypeCol?($r[$refTypeCol]??''):'').' '.($refIdCol?($r[$refIdCol]??''):'')))?></td><td class="text-end"><?=is_qty($r['_qty_in'])?></td><td class="text-end"><?=is_qty($r['_qty_out'])?></td><td class="text-end"><?=is_qty($r['_balance'])?></td><td class="text-end"><?=e($rateCol?number_format((float)($r[$rateCol]??0),2):'')?></td><td><?=e($noteCol?($r[$noteCol]??''):'')?></td><td class="no-print"><a class="btn btn-sm btn-outline-secondary" href="voucher.php?module=inventory_movements&id=<?=$r['id']?>">Voucher</a></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="10" class="text-center text-muted">No stock movement found.</td></tr><?php endif;?></tbody></table></div></div></div>
<script>(function(){const input=document.getElementById('inventoryProductSearch'),list=document.getElementById('inventoryProductSuggestions');let timer=null;if(!input||!list)return;input.addEventListener('input',function(){const q=input.value.trim();clearTimeout(timer);if(q.length<1){list.innerHTML='';return;}timer=setTimeout(function(){fetch('inventory_product_suggest.php?q='+encodeURIComponent(q),{credentials:'same-origin'}).then(r=>r.ok?r.json():[]).then(rows=>{list.innerHTML='';rows.forEach(row=>{const opt=document.createElement('option');opt.value=row.name;opt.label=row.label||row.name;list.appendChild(opt);});}).catch(()=>{list.innerHTML='';});},180);});})();</script>
<?php include __DIR__.'/includes/footer.php'; ?>
