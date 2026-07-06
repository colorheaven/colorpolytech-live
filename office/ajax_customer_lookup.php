<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function acl_cols($table){try{return table_exists($table)?table_columns($table):[];}catch(Throwable $e){return[];}}
function acl_pick($table,$cols){$all=acl_cols($table);foreach($cols as $c){if(in_array($c,$all,true))return$c;}return'';}
$q=trim((string)($_GET['q']??''));
if($q===''){echo json_encode([]);exit;}
if(!table_exists('customers')){echo json_encode([]);exit;}

$name=acl_pick('customers',['customer_name','company_name','name']);
$company=acl_pick('customers',['company','company_name','customer_name']);
$mobile=acl_pick('customers',['mobile','phone','contact_number','customer_mobile']);
$code=acl_pick('customers',['customer_code','code']);
$area=acl_pick('customers',['area','district']);
$due=acl_pick('customers',['due','current_due','balance','opening_balance']);
$status=acl_pick('customers',['status']);
$deleted=acl_pick('customers',['deleted_at']);
if(!$name){echo json_encode([]);exit;}

$where=[];$params=[];
foreach(array_unique(array_filter([$name,$company,$mobile,$code,$area])) as $c){$where[]="`$c` LIKE ?";$params[]="%$q%";}
$sql="SELECT * FROM customers WHERE (".implode(' OR ',$where).")";
if($status)$sql.=" AND (`$status` IS NULL OR `$status`='' OR LOWER(`$status`)='active')";
if($deleted)$sql.=" AND `$deleted` IS NULL";
$sql.=" ORDER BY `$name` LIMIT 20";

$out=[];
try{$st=db()->prepare($sql);$st->execute($params);foreach($st->fetchAll() as$r){$nameVal=(string)($r[$name]??'');$mobileVal=$mobile?(string)($r[$mobile]??''):'';$codeVal=$code?(string)($r[$code]??''):'';$dueVal=$due?(float)($r[$due]??0):0;$label=trim($nameVal.($mobileVal!==''?' · '.$mobileVal:'').($codeVal!==''?' · '.$codeVal:'').' · Due: '.number_format($dueVal,2));$out[]=['id'=>(int)$r['id'],'name'=>$nameVal,'mobile'=>$mobileVal,'code'=>$codeVal,'label'=>$label,'due'=>$dueVal];}}catch(Throwable $e){$out=[];}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
