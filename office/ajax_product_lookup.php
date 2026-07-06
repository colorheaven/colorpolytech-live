<?php
require_once __DIR__.'/includes/ch_backend.php';
chb_require_login();
header('Content-Type: application/json; charset=utf-8');
$q=trim((string)($_GET['q']??''));
if($q===''||!chb_table_exists('products')){echo json_encode([]);exit;}
$name=chb_pick_col('products',['product_name','name','description']);
$code=chb_pick_col('products',['product_code','unique_code','code']);
$sku=chb_pick_col('products',['sku']);
$unit=chb_pick_col('products',['unit_id','unit']);
$price=chb_pick_col('products',['sales_rate','sale_price','price','rate']);
$stock=chb_pick_col('products',['current_stock','stock','opening_stock']);
$status=chb_pick_col('products',['status']);
$deleted=chb_pick_col('products',['deleted_at']);
if(!$name){echo json_encode([]);exit;}
$where=[];$params=[];foreach(array_filter([$name,$code,$sku]) as$c){$where[]="`$c` LIKE ?";$params[]="%$q%";}
$sql='SELECT * FROM products WHERE ('.implode(' OR ',$where).')';
if($status)$sql.=" AND (`$status` IS NULL OR `$status`='' OR LOWER(`$status`)='active')";
if($deleted)$sql.=" AND `$deleted` IS NULL";
$sql.=" ORDER BY `$name` LIMIT 20";
$out=[];try{$st=chb_db()->prepare($sql);$st->execute($params);foreach($st->fetchAll() as$r){$productName=(string)($r[$name]??'');$codeVal=$code?(string)($r[$code]??''):'';$skuVal=$sku?(string)($r[$sku]??''):'';$stockVal=$stock?(float)($r[$stock]??0):0;$priceVal=$price?(float)($r[$price]??0):0;$unitName=$unit?(string)($r[$unit]??''):'';$label=trim($productName.($codeVal?' · '.$codeVal:'').($skuVal?' · '.$skuVal:'').' · Stock: '.number_format($stockVal,2).($unitName?' '.$unitName:''));$out[]=['id'=>(int)$r['id'],'name'=>$productName,'code'=>$codeVal,'sku'=>$skuVal,'label'=>$label,'unit_name'=>$unitName,'price'=>$priceVal,'stock'=>$stockVal];}}catch(Throwable $e){$out=[];}
echo json_encode($out,JSON_UNESCAPED_UNICODE);
