<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('reports.view');
$title = 'Ageing Report';

function ar_cols(string $table): array { try { return table_exists($table) ? table_columns($table) : []; } catch (Throwable $e) { return []; } }
function ar_has(string $table, string $col): bool { return in_array($col, ar_cols($table), true); }
function ar_pick(string $table, array $cols): string { $all=ar_cols($table); foreach($cols as $c){ if(in_array($c,$all,true)) return $c; } return ''; }
function ar_money($v): string { return number_format((float)$v, 2); }
function ar_date($v): string { $ts=strtotime((string)$v); return $ts?date('d-M-y',$ts):(string)$v; }
function ar_days(string $date, string $asOf): int { $d=strtotime($date); $a=strtotime($asOf); if(!$d || !$a) return 0; return max(0, (int)floor(($a-$d)/86400)); }
function ar_bucket(int $days): string { if($days<=0) return 'current'; if($days<=30) return 'd1_30'; if($days<=60) return 'd31_60'; if($days<=90) return 'd61_90'; if($days<=120) return 'd91_120'; return 'd120_plus'; }
function ar_bucket_labels(): array { return ['current'=>'Current','d1_30'=>'1-30','d31_60'=>'31-60','d61_90'=>'61-90','d91_120'=>'91-120','d120_plus'=>'120+']; }
function ar_empty_bucket(): array { return ['current'=>0,'d1_30'=>0,'d31_60'=>0,'d61_90'=>0,'d91_120'=>0,'d120_plus'=>0,'total'=>0]; }
function ar_table_first(array $tables): string { foreach($tables as $t){ if(table_exists($t)) return $t; } return ''; }

function ar_party_name(string $kind, $id): string {
    $id=(int)$id;
    if($kind==='customer' && table_exists('customers')){
        $col=ar_pick('customers',['customer_name','company_name','name']);
        if($id && $col){ try{$st=db()->prepare("SELECT `$col` FROM customers WHERE id=? LIMIT 1");$st->execute([$id]);$v=$st->fetchColumn();if($v)return (string)$v;}catch(Throwable $e){} }
    }
    if($kind==='supplier' && table_exists('suppliers')){
        $col=ar_pick('suppliers',['supplier_name','company_name','name']);
        if($id && $col){ try{$st=db()->prepare("SELECT `$col` FROM suppliers WHERE id=? LIMIT 1");$st->execute([$id]);$v=$st->fetchColumn();if($v)return (string)$v;}catch(Throwable $e){} }
    }
    return $id ? '#'.$id : 'Unassigned';
}

function ar_parties(string $kind): array {
    $table = $kind==='customer' ? 'customers' : 'suppliers';
    if(!table_exists($table)) return [];
    $name=ar_pick($table, $kind==='customer' ? ['customer_name','company_name','name'] : ['supplier_name','company_name','name']);
    if(!$name) return [];
    try{return db()->query("SELECT id, `$name` name FROM `$table` ORDER BY `$name` LIMIT 1000")->fetchAll();}catch(Throwable $e){return [];}
}

function ar_sales_rows(string $asOf, int $partyId=0): array {
    $table='sales_invoices';
    if(!table_exists($table)) return [];
    $dateCol=ar_pick($table,['due_date','invoice_date','date','created_at']);
    $ageDateCol=ar_pick($table,['due_date','invoice_date','date','created_at']);
    $invoiceDateCol=ar_pick($table,['invoice_date','date','created_at']);
    $noCol=ar_pick($table,['invoice_no','voucher_no','sales_invoice_no','bill_no']);
    $partyCol=ar_pick($table,['customer_id']);
    $amountCol=ar_pick($table,['due_amount','balance_amount','outstanding_amount','total_amount','grand_total','amount']);
    $statusCol=ar_pick($table,['status']);
    if(!$dateCol || !$amountCol) return [];
    $where="DATE(`$dateCol`)<=?"; $params=[$asOf];
    if($partyId && $partyCol){ $where.=" AND `$partyCol`=?"; $params[]=$partyId; }
    if($statusCol){ $where.=" AND (`$statusCol` IS NULL OR `$statusCol` NOT IN ('Rejected','Cancelled','Canceled','Void'))"; }
    $sql="SELECT * FROM `$table` WHERE $where ORDER BY `$dateCol`, id";
    $st=db()->prepare($sql); $st->execute($params);
    $rows=[];
    foreach($st->fetchAll() as $r){
        $amount=(float)($r[$amountCol]??0);
        if($amount<=0) continue;
        $ageDate=(string)($r[$ageDateCol]??$r[$dateCol]??$asOf);
        $days=ar_days($ageDate,$asOf);
        $rows[]=[
            'source'=>'Sales Invoice','party_kind'=>'customer','party_id'=>$partyCol?(int)($r[$partyCol]??0):0,'party'=>ar_party_name('customer',$partyCol?($r[$partyCol]??0):0),
            'voucher_no'=>$noCol?(string)($r[$noCol]??$r['id']):(string)$r['id'],'voucher_id'=>(int)$r['id'],
            'voucher_url'=>'voucher.php?module=sales_invoices&id='.(int)$r['id'],
            'invoice_date'=>$invoiceDateCol?(string)($r[$invoiceDateCol]??''):(string)($r[$dateCol]??''),'age_date'=>$ageDate,'days'=>$days,'amount'=>$amount,'bucket'=>ar_bucket($days),
        ];
    }
    return $rows;
}

function ar_purchase_table(): string { return ar_table_first(['purchase_invoices','purchases','purchase_orders']); }
function ar_purchase_rows(string $asOf, int $partyId=0): array {
    $table=ar_purchase_table();
    if(!$table) return [];
    $dateCol=ar_pick($table,['due_date','purchase_date','invoice_date','date','created_at']);
    $ageDateCol=ar_pick($table,['due_date','purchase_date','invoice_date','date','created_at']);
    $invoiceDateCol=ar_pick($table,['purchase_date','invoice_date','date','created_at']);
    $noCol=ar_pick($table,['purchase_no','invoice_no','voucher_no','bill_no']);
    $partyCol=ar_pick($table,['supplier_id']);
    $amountCol=ar_pick($table,['due_amount','balance_amount','outstanding_amount','total_amount','grand_total','amount']);
    $statusCol=ar_pick($table,['status']);
    if(!$dateCol || !$amountCol) return [];
    $where="DATE(`$dateCol`)<=?"; $params=[$asOf];
    if($partyId && $partyCol){ $where.=" AND `$partyCol`=?"; $params[]=$partyId; }
    if($statusCol){ $where.=" AND (`$statusCol` IS NULL OR `$statusCol` NOT IN ('Rejected','Cancelled','Canceled','Void'))"; }
    $st=db()->prepare("SELECT * FROM `$table` WHERE $where ORDER BY `$dateCol`, id"); $st->execute($params);
    $rows=[];
    foreach($st->fetchAll() as $r){
        $amount=(float)($r[$amountCol]??0);
        if($amount<=0) continue;
        $ageDate=(string)($r[$ageDateCol]??$r[$dateCol]??$asOf);
        $days=ar_days($ageDate,$asOf);
        $rows[]=[
            'source'=>'Purchase Bill','party_kind'=>'supplier','party_id'=>$partyCol?(int)($r[$partyCol]??0):0,'party'=>ar_party_name('supplier',$partyCol?($r[$partyCol]??0):0),
            'voucher_no'=>$noCol?(string)($r[$noCol]??$r['id']):(string)$r['id'],'voucher_id'=>(int)$r['id'],
            'voucher_url'=>'voucher.php?module='.$table.'&id='.(int)$r['id'],
            'invoice_date'=>$invoiceDateCol?(string)($r[$invoiceDateCol]??''):(string)($r[$dateCol]??''),'age_date'=>$ageDate,'days'=>$days,'amount'=>$amount,'bucket'=>ar_bucket($days),
        ];
    }
    return $rows;
}

function ar_summary(array $rows): array {
    $summary=[]; $grand=ar_empty_bucket();
    foreach($rows as $r){
        $key=$r['party_kind'].'|'.$r['party_id'].'|'.$r['party'];
        if(!isset($summary[$key])) $summary[$key]=array_merge(ar_empty_bucket(), ['party'=>$r['party'], 'party_kind'=>$r['party_kind'], 'party_id'=>$r['party_id']]);
        $b=$r['bucket']; $amount=(float)$r['amount'];
        $summary[$key][$b]+=$amount; $summary[$key]['total']+=$amount;
        $grand[$b]+=$amount; $grand['total']+=$amount;
    }
    usort($summary, fn($a,$b)=>$b['total']<=>$a['total']);
    return [$summary,$grand];
}

$asOf=trim((string)($_GET['as_of']??date('Y-m-d')));
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$asOf)) $asOf=date('Y-m-d');
$type=trim((string)($_GET['type']??'customers'));
$allowed=['customers','suppliers','sales','purchases','all'];
if(!in_array($type,$allowed,true)) $type='customers';
$partyId=(int)($_GET['party_id']??0);
$export=strtolower(trim((string)($_GET['export']??'')));

$rows=[];
if(in_array($type,['customers','sales','all'],true)) $rows=array_merge($rows, ar_sales_rows($asOf, $type==='customers'?$partyId:0));
if(in_array($type,['suppliers','purchases','all'],true)) $rows=array_merge($rows, ar_purchase_rows($asOf, $type==='suppliers'?$partyId:0));
[$summary,$grand]=ar_summary($rows);
$labels=ar_bucket_labels();

if($export==='csv'){
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="ageing_report_'.date('Ymd_His').'.csv"');
    $out=fopen('php://output','w');
    fputcsv($out,['Ageing Report','As Of',$asOf,'Type',$type]);
    fputcsv($out,[]);
    fputcsv($out,['Summary']);
    fputcsv($out,array_merge(['Party'],array_values($labels),['Total']));
    foreach($summary as $s){ fputcsv($out,[$s['party'],$s['current'],$s['d1_30'],$s['d31_60'],$s['d61_90'],$s['d91_120'],$s['d120_plus'],$s['total']]); }
    fputcsv($out,[]); fputcsv($out,['Details']);
    fputcsv($out,['Source','Party','Voucher No','Invoice Date','Age Date','Days','Amount','Bucket']);
    foreach($rows as $r){ fputcsv($out,[$r['source'],$r['party'],$r['voucher_no'],$r['invoice_date'],$r['age_date'],$r['days'],$r['amount'],$labels[$r['bucket']]??$r['bucket']]); }
    fclose($out); exit;
}

$partyList=[];
if($type==='customers') $partyList=ar_parties('customer');
if($type==='suppliers') $partyList=ar_parties('supplier');
include __DIR__.'/includes/header.php';
?>
<style>
.age-card{border:1px solid #e5e7eb;border-radius:12px;background:#fff}.age-title{text-align:center;line-height:1.35}.age-title h4{margin:0;font-weight:800}.age-kpi{border:1px solid #e5e7eb;border-radius:10px;padding:12px;background:#fbfbff}.age-kpi small{display:block;color:#6b7280}.age-kpi strong{font-size:18px}.age-table th,.age-table td{white-space:nowrap}.age-table .party-col{white-space:normal;min-width:220px}@media print{.no-print,.sidebar,.topbar{display:none!important}.main,.content{margin:0!important;padding:0!important}.age-card{border:0!important}.table-responsive{overflow:visible!important}.age-table{font-size:10px}.age-kpi{padding:8px}}
</style>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 no-print"><div><h3>Ageing Report</h3><p class="text-muted mb-0">Customer receivable, supplier payable, total sales and purchase ageing with professional ageing buckets.</p></div><div class="d-flex gap-2"><button class="btn btn-primary" onclick="window.print()">Print / PDF</button><a class="btn btn-outline-success" href="ageing_report.php?type=<?=e($type)?>&as_of=<?=e($asOf)?>&party_id=<?=$partyId?>&export=csv">Export CSV</a></div></div>
<div class="card mb-3 no-print"><div class="card-body"><form method="get" class="row g-2 align-items-end"><div class="col-md-3"><label class="form-label">Report Type</label><select class="form-select" name="type" onchange="this.form.submit()"><option value="customers" <?=$type==='customers'?'selected':''?>>Customer-wise Receivable Ageing</option><option value="suppliers" <?=$type==='suppliers'?'selected':''?>>Supplier-wise Payable Ageing</option><option value="sales" <?=$type==='sales'?'selected':''?>>Total Sales Ageing</option><option value="purchases" <?=$type==='purchases'?'selected':''?>>Total Purchase Ageing</option><option value="all" <?=$type==='all'?'selected':''?>>All Ageing Summary</option></select></div><div class="col-md-2"><label class="form-label">As of Date</label><input class="form-control" type="date" name="as_of" value="<?=e($asOf)?>"></div><?php if($type==='customers' || $type==='suppliers'): ?><div class="col-md-4"><label class="form-label"><?=$type==='customers'?'Customer':'Supplier'?></label><select class="form-select" name="party_id"><option value="0">All</option><?php foreach($partyList as $p): ?><option value="<?=$p['id']?>" <?=$partyId===(int)$p['id']?'selected':''?>><?=e($p['name'])?></option><?php endforeach; ?></select></div><?php endif; ?><div class="col-md-2"><button class="btn btn-primary w-100">Filter</button></div><div class="col-md-1"><a class="btn btn-light w-100" href="ageing_report.php">Reset</a></div></form></div></div>
<div class="age-card p-3"><div class="age-title mb-3"><h4><?=e(setting('company_name','Color Heaven'))?></h4><strong>Ageing Report</strong><br><span>As of <?=e(ar_date($asOf))?> | <?=e(ucwords(str_replace('_',' ',$type)))?></span></div><div class="row g-2 mb-3"><?php foreach($labels as $k=>$label): ?><div class="col-6 col-md-2"><div class="age-kpi"><small><?=e($label)?></small><strong><?=ar_money($grand[$k]??0)?></strong></div></div><?php endforeach; ?><div class="col-6 col-md-2"><div class="age-kpi"><small>Total</small><strong><?=ar_money($grand['total']??0)?></strong></div></div></div>
<h5>Party-wise Summary</h5><div class="table-responsive mb-4"><table class="table table-bordered table-sm age-table"><thead><tr><th>Party</th><?php foreach($labels as $label): ?><th class="text-end"><?=e($label)?></th><?php endforeach; ?><th class="text-end">Total</th></tr></thead><tbody><?php foreach($summary as $s): ?><tr><td class="party-col"><strong><?=e($s['party'])?></strong></td><?php foreach($labels as $k=>$label): ?><td class="text-end"><?=ar_money($s[$k]??0)?></td><?php endforeach; ?><td class="text-end"><strong><?=ar_money($s['total']??0)?></strong></td></tr><?php endforeach; ?><?php if(!$summary): ?><tr><td colspan="8" class="text-center text-muted">No ageing data found.</td></tr><?php endif; ?></tbody></table></div>
<h5>Voucher Details</h5><div class="table-responsive"><table class="table table-bordered table-sm age-table"><thead><tr><th>Source</th><th>Party</th><th>Voucher No</th><th>Invoice Date</th><th>Age Date</th><th class="text-end">Days</th><th>Bucket</th><th class="text-end">Amount</th><th class="no-print">Open</th></tr></thead><tbody><?php foreach($rows as $r): ?><tr><td><?=e($r['source'])?></td><td class="party-col"><?=e($r['party'])?></td><td><?=e($r['voucher_no'])?></td><td><?=e(ar_date($r['invoice_date']))?></td><td><?=e(ar_date($r['age_date']))?></td><td class="text-end"><?=e($r['days'])?></td><td><?=e($labels[$r['bucket']]??$r['bucket'])?></td><td class="text-end"><?=ar_money($r['amount'])?></td><td class="no-print"><a class="btn btn-sm btn-outline-secondary" href="<?=e($r['voucher_url'])?>">Voucher</a></td></tr><?php endforeach; ?><?php if(!$rows): ?><tr><td colspan="9" class="text-center text-muted">No voucher details found.</td></tr><?php endif; ?></tbody></table></div></div>
<?php include __DIR__.'/includes/footer.php'; ?>
