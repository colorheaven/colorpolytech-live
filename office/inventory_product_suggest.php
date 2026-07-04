<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('inventory_movements.view');
header('Content-Type: application/json; charset=utf-8');

function ips_cols($table){try{return table_exists($table)?table_columns($table):[];}catch(Throwable $e){return[];}}
function ips_pick($table,$cols){$all=ips_cols($table);foreach($cols as $c){if(in_array($c,$all,true))return$c;}return'';}
$q=trim((string)($_GET['q']??''));
if($q===''){echo json_encode([]);exit;}
$out=[];$seen=[];
if(table_exists('products')){
    $name=ips_pick('products',['product_name','name','description']);$code=ips_pick('products',['product_code','unique_code','code']);$sku=ips_pick('products',['sku']);
    if($name){$where="`$name` LIKE ?";$p=["%$q%"];if($code){$where.=" OR `$code` LIKE ?";$p[]="%$q%";}if($sku){$where.=" OR `$sku` LIKE ?";$p[]="%$q%";}$st=db()->prepare("SELECT id, `$name` name".($code?", `$code` code":"")." FROM products WHERE ($where) ORDER BY `$name` LIMIT 10");$st->execute($p);foreach($st->fetchAll() as$r){$nameVal=(string)$r['name'];$key=strtolower($nameVal);if(isset($seen[$key]))continue;$seen[$key]=1;$out[]=['name'=>$nameVal,'label'=>$nameVal.(!empty($r['code'])?' — '.$r['code']:'')];}}
}
if(table_exists('inventory_movements')){
    $productCol=ips_pick('inventory_movements',['product_id','product_name','item_name']);
    if($productCol){$st=db()->prepare("SELECT DISTINCT `$productCol` name FROM inventory_movements WHERE `$productCol` LIKE ? ORDER BY `$productCol` LIMIT 10");$st->execute(["%$q%"]);foreach($st->fetchAll() as$r){$nameVal=trim((string)$r['name']);if($nameVal==='')continue;$key=strtolower($nameVal);if(isset($seen[$key]))continue;$seen[$key]=1;$out[]=['name'=>$nameVal,'label'=>$nameVal];}}
}
echo json_encode(array_slice($out,0,12), JSON_UNESCAPED_UNICODE);
