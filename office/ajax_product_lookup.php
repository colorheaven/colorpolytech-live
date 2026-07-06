<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
header('Content-Type: application/json; charset=utf-8');

function apl_cols($table){try{return table_exists($table)?table_columns($table):[];}catch(Throwable $e){return[];}}
function apl_pick($table,$cols){$all=apl_cols($table);foreach($cols as $c){if(in_array($c,$all,true))return$c;}return'';}
function apl_unit_name($id){$id=(int)$id;if(!$id||!table_exists('units'))return'';$name=apl_pick('units',['name','unit_name','symbol']);if(!$name)return'';try{$st=db()->prepare("SELECT `$name` FROM units WHERE id=? LIMIT 1");$st->execute([$id]);return(string)($st->fetchColumn()?:'');}catch(Throwable $e){return'';}}

$q=trim((string)($_GET['q']??''));
if($q===''){echo json_encode([]);exit;}
if(!table_exists('products')){echo json_encode([]);exit;}

$name=apl_pick('products',['product_name','name','description']);
$code=apl_pick('products',['product_code','unique_code','code']);
$sku=apl_pick('products',['sku']);
$cat=apl_pick('products',['category_id','category']);
$unit=apl_pick('products',['unit_id']);
$price=apl_pick('products',['sales_rate','sale_price','price']);
$stock=apl_pick('products',['current_stock','stock']);
$status=apl_pick('products',['status']);
$deleted=apl_pick('products',['deleted_at']);
if(!$name){echo json_encode([]);exit;}

$where=[];$params=[];
foreach(array_filter([$name,$code,$sku]) as $c){$where[]="`$c` LIKE ?";$params[]="%$q%";}
$sql="SELECT * FROM products WHERE (".implode(' OR ',$where).")";
if($status)$sql.=" AND (`$status` IS NULL OR `$status`='' OR LOWER(`$status`)='active')";
if($deleted)$sql.=" AND `$deleted` IS NULL";
$sql.=" ORDER BY `$name` LIMIT 20";

$out=[];
try{$st=db()->prepare($sql);$st->execute($params);foreach($st->fetchAll() as$r){$unitId=$unit?(int)($r[$unit]??0):0;$unitName=apl_unit_name($unitId);$productName=(string)($r[$name]??'');$codeVal=$code?(string)($r[$code]??''):'';$skuVal=$sku?(string)($r[$sku]??''):'';$stockVal=$stock?(float)($r[$stock]??0):0;$priceVal=$price?(float)($r[$price]??0):0;$label=trim($productName.($codeVal!==''?' · '.$codeVal:'').($skuVal!==''?' · '.$skuVal:'').' · Stock: '.number_format($stockVal,2).($unitName?' '.$unitName:''));$out[]=['id'=>(int)$r['id'],'name'=>$productName,'code'=>$codeVal,'sku'=>$skuVal,'label'=>$label,'unit_id'=>$unitId,'unit_name'=>$unitName,'price'=>$priceVal,'stock'=>$stockVal];}}catch(Throwable $e){$out=[];}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
