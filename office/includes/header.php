<?php require_login(); $u=user(); $company=setting('company_name','Color Heaven'); ?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=e($title??'ERP')?> - <?=e($company)?></title><link rel="icon" href="/assets/images/favicon.png"><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet"><link href="/assets/css/erp.css" rel="stylesheet"></head><body><div class="layout"><aside class="sidebar"><div class="brand"><img src="/assets/images/logo.png" alt="Color Heaven"><div><strong><?=e($company)?></strong><small><?=e(setting('company_tagline','The Empire of Color'))?></small></div></div><nav>
<?php
// Removed/disabled modules are intentionally not listed here.
// Old data/tables are not touched.
$menu=[
    ['dashboard.php','bi-speedometer2','Dashboard','dashboard'],
    ['approvals.php','bi-check2-square','Approvals','approvals'],
    ['crm_leads.php','bi-bullseye','CRM Leads','crm_leads'],
    ['crm_followups.php','bi-telephone','Follow-ups','crm_followups'],
    ['quotations.php','bi-file-earmark-text','Quotations','quotations'],
    ['customers.php','bi-people','Customers','customers'],
    ['products.php','bi-box-seam','Products','products'],
    ['bulk_data.php','bi-file-earmark-spreadsheet','Bulk Import/Export','bulk_data'],
    ['sales_orders.php','bi-cart-check','Sales Orders','sales_orders'],
    ['delivery_challans.php','bi-truck','Delivery Challans','delivery_challans'],
    ['sales_invoices.php','bi-receipt','Sales Invoices','sales_invoices'],
    ['collections.php','bi-cash-coin','Collections','collections'],
    ['inventory_movements.php','bi-stack','Inventory','inventory_movements'],
    ['inventory_statement.php','bi-clipboard-data','Inventory Statement','inventory_movements'],
    ['ledger.php','bi-book','Ledger','ledger'],
    ['reports.php','bi-graph-up','Reports','reports'],
    ['settings.php','bi-gear','Settings','settings'],
    ['sms_settings.php','bi-chat-dots','SMS Settings','sms_settings'],
    ['users.php','bi-person-gear','Users','users'],
    ['roles.php','bi-shield-lock','Roles','roles'],
    ['activity_logs.php','bi-clock-history','Activity Log','activity_logs'],
    ['backup.php','bi-cloud-download','Backup','backup']
];
foreach($menu as $mi): if(!can($mi[3].'.view')) continue; ?><a href="<?=$mi[0]?>"><i class="bi <?=$mi[1]?>"></i><span><?=$mi[2]?></span></a><?php endforeach; ?></nav></aside><main class="main"><header class="topbar"><button class="btn btn-light sidebar-toggle no-print" type="button" data-sidebar-toggle aria-label="Open menu"><i class="bi bi-list"></i></button><div class="topbar-title"><h5 class="mb-0"><?=e($title??'Dashboard')?></h5><small class="text-muted">Bangladesh business week: Saturday to Thursday</small></div><form action="global_search.php" class="search"><input name="q" class="form-control" placeholder="Search customer, product, voucher..."></form><div class="userbar d-flex align-items-center gap-3"><a href="notifications.php" class="btn btn-light position-relative"><i class="bi bi-bell"></i><span class="badge rounded-pill text-bg-danger position-absolute top-0 start-100 translate-middle"><?=table_count('notifications','is_read=0')?></span></a><div class="text-end small"><strong><?=e($u['full_name'])?></strong><br><span><?=e($u['role_name'])?></span></div><a class="btn btn-outline-danger btn-sm" href="logout.php">Logout</a></div></header><section class="content">
