<?php
require_once __DIR__.'/includes/ch_backend.php';
chb_require_login();
header('Content-Type: application/json; charset=utf-8');
$q=trim((string)($_GET['q']??''));
$table='products';
if(!chb_table_exists($table)){foreach(['product','items','stock_items','materials','tbl_products'] as $t){if(chb_table_exists($t)){$table=$t;break;}}}
if($q===''||!chb_table_exists($table)){echo json_encode([]);exit;}
$name=chb_pick_col($table,['product_name','name','description','item_name']);
$code=chb_pick_col($table,['product_code','unique_code','code','sku']);
$sku=chb_pick_col($table,['sku']);
$unit=chb_pick_col($table,['unit_id','unit','unit_name']);
$grade=chb_pick_col($table,['grade','model','type']);
$price=chb_pick_col($table,['sales_rate','sale_price','price','rate']);
$stock=chb_pick_col($table,['current_stock','stock','opening_stock','qty']);
$status=chb_pick_col($table,['status']);
$deleted=chb_pick_col($table,['deleted_at']);
if(!$name){echo json_encode([]);exit;}
$where=[];$params=[];foreach(array_filter([$name,$code,$sku,$grade]) as$c){$where[]="`$c` LIKE ?";$params[]="%$q%";}
$sql='SELECT * FROM `'.$table.'` WHERE ('.implode(' OR ',$where).')';
if($status)$sql.=" AND (`$status` IS NULL OR `$status`='' OR LOWER(`$status`)='active')";
if($deleted)$sql.=" AND `$deleted` IS NULL";
$sql.=" ORDER BY `$name` LIMIT 20";
$out=[];try{$st=chb_db()->prepare($sql);$st->execute($params);foreach($st->fetchAll() as$r){$productName=(string)($r[$name]??'');$codeVal=$code?(string)($r[$code]??''):'';$skuVal=$sku?(string)($r[$sku]??''):'';$gradeVal=$grade?(string)($r[$grade]??''):'';$stockVal=$stock?(float)($r[$stock]??0):0;$priceVal=$price?(float)($r[$price]??0):0;$unitName=$unit?(string)($r[$unit]??''):'';$label=trim($productName.($codeVal?' · '.$codeVal:'').($skuVal?' · '.$skuVal:'').' · Stock: '.number_format($stockVal,2).($unitName?' '.$unitName:''));$out[]=['id'=>(int)($r['id']??0),'name'=>$productName,'code'=>$codeVal,'sku'=>$skuVal,'grade'=>$gradeVal,'label'=>$label,'unit_name'=>$unitName,'price'=>$priceVal,'stock'=>$stockVal];}}catch(Throwable $e){$out=[];}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
