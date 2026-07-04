<?php
require_once __DIR__.'/includes/bootstrap.php';
require_login();
require_once __DIR__.'/includes/voucher_print.php';
$title = 'Sales Invoice Print';
include __DIR__.'/includes/header.php';
vp_render('invoice', (int)($_GET['id'] ?? 0));
include __DIR__.'/includes/footer.php';
