<?php
require_once __DIR__.'/includes/ch_backend.php';
require_once __DIR__.'/includes/ch_ui_components.php';
chb_require_login();
$messages=[];$errors=[];
function so_setup_ready(): bool { return chb_table_exists('sales_orders') && chb_table_exists('sales_order_items'); }
function so_setup_run(): void {
    $pdo=chb_db();
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_orders (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        order_no VARCHAR(50) NOT NULL,
        order_date DATE NOT NULL,
        customer_id BIGINT UNSIGNED NULL,
        customer_name VARCHAR(190) NOT NULL,
        customer_mobile VARCHAR(50) NULL,
        customer_address TEXT NULL,
        previous_due DECIMAL(18,2) NOT NULL DEFAULT 0,
        marketer_id BIGINT UNSIGNED NULL,
        delivery_date DATE NULL,
        delivery_address TEXT NULL,
        delivery_person VARCHAR(150) NULL,
        vehicle_no VARCHAR(100) NULL,
        payment_terms VARCHAR(150) NULL,
        subtotal DECIMAL(18,2) NOT NULL DEFAULT 0,
        discount_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        transport_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        vat_amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        grand_total DECIMAL(18,2) NOT NULL DEFAULT 0,
        remarks TEXT NULL,
        status VARCHAR(40) NOT NULL DEFAULT 'Draft',
        submitted_by BIGINT UNSIGNED NULL,
        submitted_at DATETIME NULL,
        approved_by BIGINT UNSIGNED NULL,
        approved_at DATETIME NULL,
        rejected_by BIGINT UNSIGNED NULL,
        rejected_at DATETIME NULL,
        approval_note TEXT NULL,
        created_by BIGINT UNSIGNED NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_by BIGINT UNSIGNED NULL,
        updated_at DATETIME NULL,
        deleted_by BIGINT UNSIGNED NULL,
        deleted_at DATETIME NULL,
        delete_reason TEXT NULL,
        UNIQUE KEY uq_sales_orders_order_no (order_no),
        KEY idx_sales_orders_customer (customer_id),
        KEY idx_sales_orders_date (order_date),
        KEY idx_sales_orders_status (status),
        KEY idx_sales_orders_deleted (deleted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_order_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        sales_order_id BIGINT UNSIGNED NOT NULL,
        product_id BIGINT UNSIGNED NULL,
        product_name VARCHAR(190) NOT NULL,
        grade VARCHAR(120) NULL,
        bag_qty DECIMAL(18,3) NOT NULL DEFAULT 0,
        kg_per_bag DECIMAL(18,3) NOT NULL DEFAULT 25,
        total_kg DECIMAL(18,3) NOT NULL DEFAULT 0,
        rate DECIMAL(18,2) NOT NULL DEFAULT 0,
        line_total DECIMAL(18,2) NOT NULL DEFAULT 0,
        sort_order INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NULL,
        deleted_at DATETIME NULL,
        KEY idx_sales_order_items_order (sales_order_id),
        KEY idx_sales_order_items_product (product_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
try { chb_db(); } catch(Throwable $e) { $errors[]=$e->getMessage(); }
if($_SERVER['REQUEST_METHOD']==='POST'){
    try{
        chb_verify_csrf();
        so_setup_run();
        chb_audit('sales_orders','setup',null,['tables'=>'created_if_missing']);
        chb_flash('success','Sales Order backend tables are ready.');
        header('Location: sales_orders.php'); exit;
    }catch(Throwable $e){ $errors[]=$e->getMessage(); }
}
$ready=false; try{$ready=so_setup_ready();}catch(Throwable $e){}
ch_begin_app('order','Sales Order Setup');
foreach($errors as $e) echo '<div class="alert alert-danger">'.chb_e($e).'</div>';
?>
<div class="ch-card">
  <div class="ch-card-header">
    <div>
      <h3>Sales Order Backend Setup</h3>
      <p>Create required Sales Order database tables safely. No existing data will be deleted.</p>
    </div>
    <span class="badge text-bg-<?=$ready?'success':'warning'?>"><?=$ready?'Ready':'Action Required'?></span>
  </div>
  <?php if($ready): ?>
    <div class="alert alert-success"><strong>Ready.</strong> Sales Order backend tables already exist.</div>
    <a class="btn btn-ch-primary" href="sales_orders.php">Go to Sales Orders</a>
  <?php else: ?>
    <div class="alert alert-warning"><strong>Backend table missing.</strong> Click the button below to create <code>sales_orders</code> and <code>sales_order_items</code>.</div>
    <form method="post">
      <input type="hidden" name="csrf_token" value="<?=chb_e(chb_csrf_token())?>">
      <button class="btn btn-ch-primary" data-confirm="Create Sales Order backend tables now?">Create Sales Order Tables</button>
      <a class="btn btn-outline-secondary" href="sales_orders.php">Back</a>
    </form>
  <?php endif; ?>
</div>
<?php ch_end_app(); ?>
