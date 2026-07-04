<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_perm('products.view');
$title='Product Register';

function pcols($t='products'){try{return table_exists($t)?table_columns($t):[];}catch(Throwable $e){return[];}}
function phas($c,$t='products'){return in_array($c,pcols($t),true);} 
function ppick($a,$t='products'){$cols=pcols($t);foreach($a as $c){if(in_array($c,$cols,true))return$c;}return'';}
function pval($r,$c,$d=''){return($r&&$c)?($r[$c]??$d):$d;}
function poptions($table,$nameCandidates){if(!table_exists($table))return[];$name=ppick($nameCandidates,$table);if(!$name)return[];try{return db()->query("SELECT id, `$name` name FROM `$table` ORDER BY `$name`")->fetchAll();}catch(Throwable $e){return[];}}

$nameCol=ppick(['product_name','name','description']);
$codeCol=ppick(['product_code','unique_code','code']);
$skuCol=ppick(['sku']);
$supplierCol=ppick(['supplier_id']);
$supplierGradeCol=ppick(['supplier_grade']);
$categoryCol=ppick(['category_id']);
$unitCol=ppick(['unit_id']);
$altUnitCol=ppick(['alternative_unit_id','alt_unit_id']);
$altUnitQtyCol=ppick(['alternative_unit_qty','alt_unit_qty']);
$baseQtyCol=ppick(['base_qty_per_alternative_unit','base_qty_per_alt_unit','conversion_qty']);
$purchaseRateCol=ppick(['purchase_rate']);
$salesRateCol=ppick(['sales_rate','price']);
$openingStockCol=ppick(['opening_stock']);
$currentStockCol=ppick(['current_stock']);
$minimumStockCol=ppick(['minimum_stock_level','minimum_stock']);
$descriptionCol=ppick(['description']);
$applicationCol=ppick(['application']);
$imageCol=ppick(['image','image_path']);
$statusCol=ppick(['status']);

function p_reference_values(array $product): array {
    global $nameCol,$codeCol,$skuCol;
    $values = [(string)($product['id'] ?? '')];
    foreach ([$nameCol,$codeCol,$skuCol] as $col) {
        if ($col && !empty($product[$col])) $values[] = (string)$product[$col];
    }
    return array_values(array_unique(array_filter($values, fn($v)=>trim((string)$v)!=='')));
}
function p_has_reference(array $product): bool {
    $values = p_reference_values($product);
    if (!$values) return false;
    $tables = ['sales_order_items','delivery_challan_items','sales_invoice_items','quotation_items','inventory_movements'];
    $cols = ['product_id','product_name','item_name'];
    foreach($tables as $table){
        if(!table_exists($table)) continue;
        $available = array_values(array_filter($cols, fn($c)=>phas($c,$table)));
        foreach($available as $col){
            $sql = "SELECT 1 FROM `$table` WHERE `$col` IN (".implode(',',array_fill(0,count($values),'?')).") LIMIT 1";
            try{$st=db()->prepare($sql);$st->execute($values);if($st->fetchColumn())return true;}catch(Throwable $e){}
        }
    }
    return false;
}
function p_delete_products(array $ids): array {
    $deleted=0; $skipped=0; $missing=0;
    foreach(array_values(array_unique(array_map('intval',$ids))) as $id){
        if($id<=0) continue;
        $st=db()->prepare('SELECT * FROM products WHERE id=? LIMIT 1');$st->execute([$id]);$old=$st->fetch();
        if(!$old){$missing++;continue;}
        if(p_has_reference($old)){ $skipped++; continue; }
        try{db()->prepare('DELETE FROM products WHERE id=? LIMIT 1')->execute([$id]);log_action('Products','delete',$old,'',$id);$deleted++;}
        catch(Throwable $e){$skipped++;}
    }
    return ['deleted'=>$deleted,'skipped'=>$skipped,'missing'=>$missing];
}

$action=$_GET['action']??'list';$error='';
try{
    if($action==='delete'){
        check_csrf(); require_perm('products.delete');
        $result=p_delete_products([(int)($_GET['id']??0)]);
        header('Location: products.php?msg=delete_done&deleted='.$result['deleted'].'&skipped='.$result['skipped']);exit;
    }
    if($_SERVER['REQUEST_METHOD']==='POST'){
        check_csrf();
        $postAction=$_POST['post_action']??'save';
        if($postAction==='bulk_delete'){
            require_perm('products.delete');
            $ids=$_POST['ids']??[]; if(!is_array($ids))$ids=[];
            $result=p_delete_products($ids);
            header('Location: products.php?msg=bulk_delete_done&deleted='.$result['deleted'].'&skipped='.$result['skipped'].'&missing='.$result['missing']);exit;
        }
        $id=(int)($_POST['id']??0);require_perm('products.'.($id?'edit':'add'));
        if(!$nameCol)throw new Exception('Product name column missing.');
        $d=[];$d[$nameCol]=trim((string)($_POST['product_name']??''));if($d[$nameCol]==='')throw new Exception('Product name is required.');
        if($codeCol)$d[$codeCol]=trim((string)($_POST['product_code']??''));
        if($skuCol)$d[$skuCol]=trim((string)($_POST['sku']??''));
        if($supplierCol)$d[$supplierCol]=(int)($_POST['supplier_id']??0)?:null;
        if($supplierGradeCol)$d[$supplierGradeCol]=trim((string)($_POST['supplier_grade']??''));
        if($categoryCol)$d[$categoryCol]=(int)($_POST['category_id']??0)?:null;
        if($unitCol)$d[$unitCol]=(int)($_POST['unit_id']??0)?:null;
        if($altUnitCol)$d[$altUnitCol]=(int)($_POST['alternative_unit_id']??0)?:null;
        if($altUnitQtyCol)$d[$altUnitQtyCol]=(float)($_POST['alternative_unit_qty']??1);
        if($baseQtyCol)$d[$baseQtyCol]=(float)($_POST['base_qty_per_alternative_unit']??1);
        if($purchaseRateCol)$d[$purchaseRateCol]=(float)($_POST['purchase_rate']??0);
        if($salesRateCol)$d[$salesRateCol]=(float)($_POST['sales_rate']??0);
        if($openingStockCol)$d[$openingStockCol]=(float)($_POST['opening_stock']??0);
        if($currentStockCol)$d[$currentStockCol]=(float)($_POST['current_stock']??0);
        if($minimumStockCol)$d[$minimumStockCol]=(float)($_POST['minimum_stock_level']??0);
        if($descriptionCol)$d[$descriptionCol]=trim((string)($_POST['description']??''));
        if($applicationCol)$d[$applicationCol]=trim((string)($_POST['application']??''));
        if($imageCol)$d[$imageCol]=trim((string)($_POST['image']??''));
        if($statusCol)$d[$statusCol]=trim((string)($_POST['status']??'active'));
        if(phas('updated_at'))$d['updated_at']=date('Y-m-d H:i:s');
        $d=array_intersect_key($d,array_flip(pcols()));
        if($id){$old=db()->prepare('SELECT * FROM products WHERE id=? LIMIT 1');$old->execute([$id]);$oldRow=$old->fetch();if(!$oldRow)throw new Exception('Product not found.');$sets=[];foreach($d as$k=>$v)$sets[]="`$k`=?";db()->prepare('UPDATE products SET '.implode(',',$sets).' WHERE id=? LIMIT 1')->execute(array_merge(array_values($d),[$id]));log_action('Products','edit',$oldRow,$d,$id);header('Location: products.php?msg=updated');exit;}
        if(phas('created_at'))$d['created_at']=date('Y-m-d H:i:s');$keys=array_keys($d);db()->prepare('INSERT INTO products (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_fill(0,count($keys),'?')).')')->execute(array_values($d));$id=(int)db()->lastInsertId();log_action('Products','add','',$d,$id);header('Location: products.php?msg=created');exit;
    }
}catch(Throwable $e){$error=$e->getMessage();}

$edit=null;if(in_array($action,['add','form','edit'],true)){require_perm('products.'.((!empty($_GET['id']))?'edit':'add'));if(!empty($_GET['id'])){$st=db()->prepare('SELECT * FROM products WHERE id=? LIMIT 1');$st->execute([(int)$_GET['id']]);$edit=$st->fetch();if(!$edit)$error='Product not found.';}}
$q=trim((string)($_GET['q']??''));$where='1=1';$params=[];if($q!==''){$parts=[];foreach(array_filter([$nameCol,$codeCol,$skuCol,$supplierGradeCol])as$c){$parts[]="`$c` LIKE ?";$params[]="%$q%";}if($parts)$where.=' AND ('.implode(' OR ',$parts).')';}
$rows=[];if(table_exists('products')){$order=$nameCol?:'id';$st=db()->prepare("SELECT * FROM products WHERE $where ORDER BY `$order` LIMIT 100");$st->execute($params);$rows=$st->fetchAll();}
$units=poptions('units',['name','unit_name','symbol']);$cats=poptions('product_categories',['name','category_name']);$suppliers=poptions('suppliers',['supplier_name','company_name','name']);
include __DIR__.'/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3"><div><h3>Product Register</h3><p class="text-muted mb-0">Manage products, base unit and alternative unit conversion.</p></div><a class="btn btn-primary" href="products.php?action=add">+ Add New</a></div>
<?php if($error):?><div class="alert alert-danger"><?=e($error)?></div><?php endif;?>
<?php if(!empty($_GET['msg'])):?><div class="alert alert-success">Product <?=e(str_replace('_',' ',$_GET['msg']))?>. Deleted: <?=e($_GET['deleted']??'0')?>, skipped because used in transactions: <?=e($_GET['skipped']??'0')?></div><?php endif;?>
<?php if(in_array($action,['add','form','edit'],true)):?>
<div class="card mb-3"><div class="card-body"><div class="d-flex justify-content-between"><h4><?=!empty($edit)?'Edit Product':'Add Product'?></h4><a class="btn btn-light" href="products.php">Back</a></div><form method="post" class="row g-3"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="id" value="<?=e($edit['id']??0)?>"><div class="col-md-5"><label class="form-label">Product Name *</label><input class="form-control" name="product_name" value="<?=e(pval($edit,$nameCol))?>" required></div><div class="col-md-3"><label class="form-label">Unique Code</label><input class="form-control" name="product_code" value="<?=e(pval($edit,$codeCol))?>"></div><div class="col-md-4"><label class="form-label">SKU</label><input class="form-control" name="sku" value="<?=e(pval($edit,$skuCol))?>"></div><div class="col-md-4"><label class="form-label">Supplier</label><select class="form-select" name="supplier_id"><option value="0">-- Select Supplier --</option><?php foreach($suppliers as$s):?><option value="<?=$s['id']?>" <?=((int)pval($edit,$supplierCol)==(int)$s['id'])?'selected':''?>><?=e($s['name'])?></option><?php endforeach;?></select></div><div class="col-md-4"><label class="form-label">Supplier Grade</label><input class="form-control" name="supplier_grade" value="<?=e(pval($edit,$supplierGradeCol))?>"></div><div class="col-md-4"><label class="form-label">Category</label><select class="form-select" name="category_id"><option value="0">-- Select Category --</option><?php foreach($cats as$c):?><option value="<?=$c['id']?>" <?=((int)pval($edit,$categoryCol)==(int)$c['id'])?'selected':''?>><?=e($c['name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Base Unit</label><select class="form-select" name="unit_id"><option value="0">-- Select Unit --</option><?php foreach($units as$u):?><option value="<?=$u['id']?>" <?=((int)pval($edit,$unitCol)==(int)$u['id'])?'selected':''?>><?=e($u['name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Alternative Unit</label><select class="form-select" name="alternative_unit_id"><option value="0">-- Select Alternative Unit --</option><?php foreach($units as$u):?><option value="<?=$u['id']?>" <?=((int)pval($edit,$altUnitCol)==(int)$u['id'])?'selected':''?>><?=e($u['name'])?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">Alternative Unit Qty</label><input class="form-control" type="number" step="0.0001" name="alternative_unit_qty" value="<?=e(pval($edit,$altUnitQtyCol,1))?>" placeholder="Example: 1 Bag"></div><div class="col-md-3"><label class="form-label">Base Qty per Alt Unit</label><input class="form-control" type="number" step="0.0001" name="base_qty_per_alternative_unit" value="<?=e(pval($edit,$baseQtyCol,25))?>" placeholder="Example: 25 Kg"></div><div class="col-md-3"><label class="form-label">Purchase Rate</label><input class="form-control text-end" type="number" step="0.01" name="purchase_rate" value="<?=e(pval($edit,$purchaseRateCol,0))?>"></div><div class="col-md-3"><label class="form-label">Sales Rate / Price</label><input class="form-control text-end" type="number" step="0.01" name="sales_rate" value="<?=e(pval($edit,$salesRateCol,0))?>"></div><div class="col-md-3"><label class="form-label">Opening Stock</label><input class="form-control text-end" type="number" step="0.001" name="opening_stock" value="<?=e(pval($edit,$openingStockCol,0))?>"></div><div class="col-md-3"><label class="form-label">Current Stock</label><input class="form-control text-end" type="number" step="0.001" name="current_stock" value="<?=e(pval($edit,$currentStockCol,0))?>"></div><div class="col-md-3"><label class="form-label">Minimum Stock</label><input class="form-control text-end" type="number" step="0.001" name="minimum_stock_level" value="<?=e(pval($edit,$minimumStockCol,0))?>"></div><div class="col-md-3"><label class="form-label">Status</label><select class="form-select" name="status"><option value="active" <?=pval($edit,$statusCol,'active')==='active'?'selected':''?>>Active</option><option value="inactive" <?=pval($edit,$statusCol)==='inactive'?'selected':''?>>Inactive</option></select></div><div class="col-md-6"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3"><?=e(pval($edit,$descriptionCol))?></textarea></div><div class="col-md-6"><label class="form-label">Application</label><textarea class="form-control" name="application" rows="3"><?=e(pval($edit,$applicationCol))?></textarea></div><div class="col-12"><label class="form-label">Image Path</label><input class="form-control" name="image" value="<?=e(pval($edit,$imageCol))?>" placeholder="uploads/products/example.webp"></div><div class="col-12"><button class="btn btn-primary">Save Product</button> <a class="btn btn-light" href="products.php">Cancel</a></div></form></div></div>
<?php endif;?>
<div class="card mb-3"><div class="card-body"><form class="row g-2" method="get"><div class="col-md-10"><input class="form-control" name="q" value="<?=e($q)?>" placeholder="Search product name, code, SKU, grade..."></div><div class="col-md-2"><button class="btn btn-primary w-100">Search</button></div></form></div></div>
<form method="post" id="bulkProductForm"><input type="hidden" name="csrf" value="<?=csrf()?>"><input type="hidden" name="post_action" value="bulk_delete"><div class="d-flex gap-2 align-items-center mb-2"><?php if(can('products.delete')):?><button class="btn btn-outline-danger btn-sm" type="submit" onclick="return confirm('Delete selected unused products? Products used in transactions will be skipped.')">Delete Selected</button><?php endif;?><small class="text-muted">Tick rows for bulk delete.</small></div><div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th style="width:36px"><input type="checkbox" id="selectAllProducts"></th><th>Product</th><th>Code/SKU</th><th>Base Unit</th><th>Alt Unit</th><th class="text-end">Current Stock</th><th class="text-end">Sales Rate</th><th>Status</th><th class="text-end">Action</th></tr></thead><tbody><?php foreach($rows as$r):?><tr><td><input type="checkbox" name="ids[]" value="<?=$r['id']?>" class="product-check"></td><td><strong><?=e(pval($r,$nameCol))?></strong><br><small class="text-muted"><?=e(pval($r,$supplierGradeCol))?></small></td><td><?=e(pval($r,$codeCol))?><br><small><?=e(pval($r,$skuCol))?></small></td><td><?=e(pval($r,$unitCol))?></td><td><?=e(pval($r,$altUnitCol))?> <small><?=e(pval($r,$baseQtyCol))?> base/unit</small></td><td class="text-end"><?=money(pval($r,$currentStockCol,0))?></td><td class="text-end"><?=money(pval($r,$salesRateCol,0))?></td><td><?=e(pval($r,$statusCol))?></td><td class="text-end text-nowrap"><a class="btn btn-sm btn-outline-secondary" href="products.php?action=form&id=<?=$r['id']?>">Edit</a> <?php if(can('products.delete')):?><a class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this unused product? If it is used in transactions it will be skipped.')" href="products.php?action=delete&id=<?=$r['id']?>&csrf=<?=csrf()?>">Delete</a><?php endif;?></td></tr><?php endforeach;?><?php if(!$rows):?><tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr><?php endif;?></tbody></table></div></div></form>
<script>(function(){const all=document.getElementById('selectAllProducts');if(!all)return;all.addEventListener('change',function(){document.querySelectorAll('.product-check').forEach(cb=>cb.checked=all.checked);});})();</script>
<?php include __DIR__.'/includes/footer.php'; ?>
