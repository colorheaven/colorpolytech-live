<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('customers.view');
$title='Customers';

function ccols(){try{return table_exists('customers')?table_columns('customers'):[];}catch(Throwable $e){return[];}}
function chas($c){return in_array($c,ccols(),true);} 
function cpick($a){$cols=ccols();foreach($a as $c){if(in_array($c,$cols,true))return$c;}return'';}
function cmobile($v){return preg_replace('/[^0-9+]/','',trim((string)$v))?:'';}
function cval($r,$c,$d=''){return($r&&$c)?($r[$c]??$d):$d;}

$nameCol=cpick(['customer_name','name','company_name']);
$codeCol=cpick(['customer_code','code','customer_no']);
$addressCol=cpick(['address']);
$contactCol=cpick(['contact_person','contact_name']);
$phoneCol=cpick(['contact_number','mobile','phone']);
$smsCol=cpick(['sms_number','contact_number','mobile','phone']);
$emailCol=cpick(['email']);
$areaCol=cpick(['area','district']);
$statusCol=cpick(['status']);
$openingCol=cpick(['opening_balance']);

function c_code_exists($col,$code,$id=0){
    if(!$col||$code==='')return false;
    $sql="SELECT id FROM customers WHERE `$col`=?";$p=[$code];
    if($id>0){$sql.=' AND id<>?';$p[]=$id;}
    $sql.=' LIMIT 1';$st=db()->prepare($sql);$st->execute($p);return(bool)$st->fetchColumn();
}
function c_next_code($col){
    if(!$col)return 'CUST-0001';
    $best=0;$prefix='CUST-';$width=4;
    try{$st=db()->query("SELECT `$col` v FROM customers WHERE `$col` IS NOT NULL AND `$col`<>'' ORDER BY id DESC LIMIT 1000");$rows=$st->fetchAll();}catch(Throwable $e){return 'CUST-0001';}
    foreach($rows as $r){$v=trim((string)$r['v']);if(preg_match('/^(.*?)(\d+)$/',$v,$m)){if((int)$m[2]>=$best){$best=(int)$m[2];$prefix=$m[1]!==''?$m[1]:'CUST-';$width=max(4,strlen($m[2]));}}}
    return $prefix.str_pad((string)($best+1),$width,'0',STR_PAD_LEFT);
}
function c_prepare_code($col,$posted,$id=0){
    if(!$col)return'';$code=trim((string)$posted);
    if($code===''){$code=c_next_code($col);$i=0;while(c_code_exists($col,$code,$id)&&$i<30){$code='CUST-'.date('ymdHis').str_pad((string)$i,2,'0',STR_PAD_LEFT);$i++;}}
    if(c_code_exists($col,$code,$id))throw new Exception('Customer code already exists. Please use a unique code.');
    return $code;
}
function c_due($id){
    if(!table_exists('sales_invoices'))return 0;$cols=table_columns('sales_invoices');
    if(!in_array('customer_id',$cols,true))return 0;$amount=in_array('due_amount',$cols,true)?'due_amount':(in_array('total_amount',$cols,true)?'total_amount':'');
    if(!$amount)return 0;$w='customer_id=?';$p=[$id];if(in_array('status',$cols,true))$w.=" AND status='Approved'";
    $st=db()->prepare("SELECT COALESCE(SUM(`$amount`),0) FROM sales_invoices WHERE $w");$st->execute($p);return(float)$st->fetchColumn();
}
function c_has_txn($id){
    foreach(['sales_orders','delivery_challans','sales_invoices','collections','crm_followups'] as $t){if(table_exists($t)&&in_array('customer_id',table_columns($t),true)&&table_count($t,'customer_id=?',[$id])>0)return true;}return false;
}

$action=$_GET['action']??'list';$error='';
try{
    if($action==='delete'){
        check_csrf();require_perm('customers.delete');$id=(int)($_GET['id']??0);if(!$id)throw new Exception('Invalid customer.');
        if(c_has_txn($id)){if($statusCol){db()->prepare("UPDATE customers SET `$statusCol`='inactive' WHERE id=? LIMIT 1")->execute([$id]);log_action('Customers','inactive','customer_id='.$id,'',$id);header('Location: customers.php?msg=inactive');exit;}throw new Exception('Customer has transactions, so remove is blocked.');}
        if($statusCol){db()->prepare("UPDATE customers SET `$statusCol`='inactive' WHERE id=? LIMIT 1")->execute([$id]);}else{throw new Exception('Status column missing; remove is blocked for safety.');}
        log_action('Customers','inactive','customer_id='.$id,'',$id);header('Location: customers.php?msg=inactive');exit;
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_csrf();$id=(int)($_POST['id']??0);require_perm('customers.'.($id?'edit':'add'));if(!$nameCol)throw new Exception('Customer name column missing.');
        $d=[];$d[$nameCol]=trim((string)($_POST['customer_name']??''));if($d[$nameCol]==='')throw new Exception('Customer name is required.');
        if($codeCol)$d[$codeCol]=c_prepare_code($codeCol,$_POST['customer_code']??'',$id);
        if($addressCol)$d[$addressCol]=trim((string)($_POST['address']??''));
        if($contactCol)$d[$contactCol]=trim((string)($_POST['contact_person']??''));
        if($phoneCol)$d[$phoneCol]=cmobile($_POST['contact_number']??'');
        if($smsCol)$d[$smsCol]=cmobile($_POST['sms_number']??($_POST['contact_number']??''));
        if($emailCol)$d[$emailCol]=trim((string)($_POST['email']??''));
        if($areaCol)$d[$areaCol]=trim((string)($_POST['area']??''));
        if($openingCol)$d[$openingCol]=(float)($_POST['opening_balance']??0);
        if($statusCol)$d[$statusCol]=trim((string)($_POST['status']??'active'));
        if(chas('updated_at'))$d['updated_at']=date('Y-m-d H:i:s');
        $d=array_intersect_key($d,array_flip(ccols()));
        if($id){$old=db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');$old->execute([$id]);$oldRow=$old->fetch();if(!$oldRow)throw new Exception('Customer not found.');$sets=[];foreach($d as $k=>$v)$sets[]="`$k`=?";db()->prepare('UPDATE customers SET '.implode(',',$sets).' WHERE id=? LIMIT 1')->execute(array_merge(array_values($d),[$id]));log_action('Customers','edit',$oldRow,$d,$id);header('Location: customers.php?msg=updated');exit;}
        if(chas('created_at'))$d['created_at']=date('Y-m-d H:i:s');$keys=array_keys($d);db()->prepare('INSERT INTO customers (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')')->execute(array_values($d));$id=(int)db()->lastInsertId();log_action('Customers','add','',$d,$id);header('Location: customers.php?msg=created');exit;
    }
}catch(Throwable $e){$error=$e->getMessage();}

$edit=null;if(in_array($action,['add','edit'],true)){require_perm('customers.'.($action==='edit'?'edit':'add'));if($action==='edit'){$st=db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');$st->execute([(int)($_GET['id']??0)]);$edit=$st->fetch();if(!$edit){$error='Customer not found.';$action='list';}}}
$q=trim((string)($_GET['q']??''));$page=max(1,(int)($_GET['page']??1));$limit=25;$offset=($page-1)*$limit;$where='1=1';$params=[];
if($q!==''){$parts=[];foreach(array_filter([$nameCol,$codeCol,$phoneCol,$smsCol,$emailCol,$areaCol,$contactCol])as$c){$parts[]="c.`$c` LIKE ?";$params[]="%$q%";}if($parts)$where.=' AND ('.implode(' OR ',$parts).')';}
try{apply_scoped_where($where,$params,'customers','customers','c');}catch(Throwable $e){}
$total=0;$rows=[];if(table_exists('customers')){$st=db()->prepare("SELECT COUNT(*) FROM customers c WHERE $where");$st->execute($params);$total=(int)$st->fetchColumn();$order=$nameCol?:'id';$st=db()->prepare("SELECT c.* FROM customers c WHERE $where ORDER BY c.`$order` LIMIT $limit OFFSET $offset");$st->execute($params);$rows=$st->fetchAll();}
include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3>Customers</h3><p class="text-muted mb-0">Customer details, unique code, SMS number, opening balance and statement.</p></div><?php if(can('customers.add')):?><a class="btn btn-primary" href="customers.php?action=add"><i class="bi bi-plus-lg"></i> New Customer</a><?php endif;?></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?><?php if(!empty($_GET['msg'])):?><div class="alert alert-success">Customer <?=e($_GET['msg'])?> successfully.</div><?php endif;?>
<?php if(in_array($action,['add','edit'],true)):?>
<div class="card mb-3"><div class="card-body"><h5><?=$action==='edit'?'Edit Customer':'New Customer'?></h5><form method="post" class="row g-3"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>"><div class="col-md-4"><label class="form-label">Customer Name *</label><input class="form-control" name="customer_name" value="<?=e(cval($edit,$nameCol))?>" required></div><div class="col-md-2"><label class="form-label">Customer Code</label><input class="form-control" name="customer_code" value="<?=e(cval($edit,$codeCol))?>" placeholder="Auto if blank"></div><div class="col-md-3"><label class="form-label">Contact Person</label><input class="form-control" name="contact_person" value="<?=e(cval($edit,$contactCol))?>"></div><div class="col-md-3"><label class="form-label">Contact Number</label><input class="form-control" name="contact_number" value="<?=e(cval($edit,$phoneCol))?>"></div><div class="col-md-3"><label class="form-label">SMS Number</label><input class="form-control" name="sms_number" value="<?=e(cval($edit,$smsCol,cval($edit,$phoneCol)))?>"></div><div class="col-md-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?=e(cval($edit,$emailCol))?>"></div><div class="col-md-3"><label class="form-label">Area/District</label><input class="form-control" name="area" value="<?=e(cval($edit,$areaCol))?>"></div><div class="col-md-3"><label class="form-label">Opening Balance</label><input class="form-control" type="number" step="0.01" name="opening_balance" value="<?=e(cval($edit,$openingCol,0))?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=cval($edit,$statusCol,'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=cval($edit,$statusCol)==='inactive'?'selected':''?>>Inactive</option></select></div><div class="col-12"><label class="form-label">Address</label><textarea class="form-control" name="address" rows="3"><?=e(cval($edit,$addressCol))?></textarea></div><div class="col-12"><button class="btn btn-primary">Save Customer</button> <a class="btn btn-light" href="customers.php">Cancel</a></div></form></div></div>
<?php endif;?>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="get"><div class="col-md-10"><input class="form-control" id="customerSearch" name="q" value="<?=e($q)?>" list="customerSuggestions" autocomplete="off" placeholder="Type any letter for customer name suggestion..."><datalist id="customerSuggestions"></datalist></div><div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div></form></div></div>
<div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Code</th><th>Customer</th><th>Contact</th><th>Address</th><th class="text-end">Opening</th><th class="text-end">Due</th><th class="text-end">Action</th></tr></thead><tbody><?php foreach($rows as$r):$cid=(int)$r['id'];?><tr><td><?=e($codeCol?($r[$codeCol]??''):$cid)?></td><td><strong><?=e($nameCol?($r[$nameCol]??''):('Customer #'.$cid))?></strong><br><small class="text-muted"><?=e($statusCol?($r[$statusCol]??''):'')?> <?=e($emailCol?($r[$emailCol]??''):'')?></small></td><td><?=e($contactCol?($r[$contactCol]??''):'')?><br><small class="text-muted">Call: <?=e($phoneCol?($r[$phoneCol]??''):'')?> | SMS: <?=e($smsCol?($r[$smsCol]??''):'')?></small></td><td><small><?=e($addressCol?($r[$addressCol]??''):'')?></small></td><td class="text-end"><?=money($openingCol?($r[$openingCol]??0):0)?></td><td class="text-end"><?=money(c_due($cid))?></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-primary" href="customer_statement.php?customer_id=<?=$cid?>">Statement</a> <?php if(can('customers.edit')):?><a class="btn btn-sm btn-outline-secondary" href="customers.php?action=edit&id=<?=$cid?>">Edit</a><?php endif;?> <?php if(can('customers.delete')):?><a class="btn btn-sm btn-outline-danger" onclick="return confirm('Remove this customer from active list? Existing transactions remain safe.')" href="customers.php?action=delete&id=<?=$cid?>&csrf=<?=csrf()?>">Delete</a><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="7" class="text-center text-muted py-4">No customers found.</td></tr><?php endif;?></tbody></table></div></div>
<?php if($total>$limit):$pages=(int)ceil($total/$limit);?><nav class="mt-3"><ul class="pagination"><?php for($i=1;$i<=$pages;$i++):?><li class="page-item <?=$i===$page?'active':''?>"><a class="page-link" href="customers.php?q=<?=urlencode($q)?>&page=<?=$i?>"><?=$i?></a></li><?php endfor;?></ul></nav><?php endif;?>
<script>(function(){const input=document.getElementById('customerSearch'),list=document.getElementById('customerSuggestions');let timer=null;if(!input||!list)return;input.addEventListener('input',function(){const q=input.value.trim();clearTimeout(timer);if(q.length<1){list.innerHTML='';return;}timer=setTimeout(function(){fetch('customer_suggest.php?q='+encodeURIComponent(q),{credentials:'same-origin'}).then(r=>r.ok?r.json():[]).then(rows=>{list.innerHTML='';rows.forEach(row=>{const opt=document.createElement('option');opt.value=row.name;opt.label=row.label||row.name;list.appendChild(opt);});}).catch(()=>{list.innerHTML='';});},180);});})();</script>
<?php include __DIR__.'/includes/footer.php'; ?>
