<?php
require_once __DIR__.'/includes/ch_backend.php';
chb_require_login();
header('Content-Type: application/json; charset=utf-8');
$q=trim((string)($_GET['q']??''));
$table='customers';
if(!chb_table_exists($table)){foreach(['customer','tbl_customers','clients','parties'] as $t){if(chb_table_exists($t)){$table=$t;break;}}}
if($q===''||!chb_table_exists($table)){echo json_encode([]);exit;}
$name=chb_pick_col($table,['customer_name','company_name','name','party_name']);
$company=chb_pick_col($table,['company','company_name','customer_name']);
$mobile=chb_pick_col($table,['mobile','phone','contact_number','customer_mobile']);
$code=chb_pick_col($table,['customer_code','code']);
$area=chb_pick_col($table,['area','district','zone']);
$due=chb_pick_col($table,['due','current_due','balance','opening_balance']);
$status=chb_pick_col($table,['status']);
$deleted=chb_pick_col($table,['deleted_at']);
$addressCol=chb_pick_col($table,['address','customer_address']);
if(!$name){echo json_encode([]);exit;}
$where=[];$params=[];foreach(array_unique(array_filter([$name,$company,$mobile,$code,$area])) as$c){$where[]="`$c` LIKE ?";$params[]="%$q%";}
$sql='SELECT * FROM `'.$table.'` WHERE ('.implode(' OR ',$where).')';
if($status)$sql.=" AND (`$status` IS NULL OR `$status`='' OR LOWER(`$status`)='active')";
if($deleted)$sql.=" AND `$deleted` IS NULL";
$sql.=" ORDER BY `$name` LIMIT 20";
$out=[];try{$st=chb_db()->prepare($sql);$st->execute($params);foreach($st->fetchAll() as$r){$nameVal=(string)($r[$name]??'');$mobileVal=$mobile?(string)($r[$mobile]??''):'';$codeVal=$code?(string)($r[$code]??''):'';$dueVal=$due?(float)($r[$due]??0):0;$address=$addressCol?(string)($r[$addressCol]??''):'';$label=trim($nameVal.($mobileVal?' · '.$mobileVal:'').($codeVal?' · '.$codeVal:'').' · Due: '.number_format($dueVal,2));$out[]=['id'=>(int)($r['id']??0),'name'=>$nameVal,'mobile'=>$mobileVal,'code'=>$codeVal,'address'=>$address,'label'=>$label,'due'=>$dueVal];}}catch(Throwable $e){$out=[];}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
