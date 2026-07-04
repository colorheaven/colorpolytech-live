<?php
/**
 * Shared printable voucher renderer for Sales Order, Delivery Note and Sales Invoice.
 * Sales Order and Delivery Note show quantity only.
 * Sales Invoice shows quantity, rate and amount.
 */

function vp_cols(string $table): array {
    try { return table_exists($table) ? table_columns($table) : []; } catch (Throwable $e) { return []; }
}
function vp_has(string $table, string $col): bool { return in_array($col, vp_cols($table), true); }
function vp_pick(string $table, array $cols): string { $all = vp_cols($table); foreach ($cols as $c) if (in_array($c, $all, true)) return $c; return ''; }
function vp_money($v): string { return number_format((float)$v, 2); }
function vp_qty($v): string { return rtrim(rtrim(number_format((float)$v, 4, '.', ''), '0'), '.') ?: '0'; }
function vp_date($v): string { $ts = strtotime((string)$v); return $ts ? date('d-M-y', $ts) : (string)$v; }
function vp_text($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

function vp_bdt_words($amount): string {
    $amount = (int)round((float)$amount);
    if ($amount === 0) return 'Bangladeshi Taka Zero Only';
    $ones = ['', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen'];
    $tens = ['', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];
    $under100 = function($n) use ($ones,$tens){ return $n < 20 ? $ones[$n] : trim($tens[intdiv($n,10)].' '.$ones[$n%10]); };
    $under1000 = function($n) use ($ones,$under100){ $h=intdiv($n,100); $r=$n%100; return trim(($h?$ones[$h].' Hundred ':'').($r?$under100($r):'')); };
    $parts = [];
    foreach ([10000000=>'Crore',100000=>'Lakh',1000=>'Thousand'] as $div=>$label) {
        if ($amount >= $div) { $parts[] = $under1000(intdiv($amount,$div)).' '.$label; $amount %= $div; }
    }
    if ($amount) $parts[] = $under1000($amount);
    return 'Bangladeshi Taka '.implode(' ', array_filter($parts)).' Only';
}

function vp_company(): array {
    $logo = setting('company_logo', '');
    if (!$logo) $logo = '/assets/images/logo.png';
    return [
        'name' => setting('company_name', 'Color Heaven'),
        'tagline' => setting('company_tagline', 'The Empire of Color'),
        'address' => setting('company_address', '101/102, Horonath Gosh Road, Chawkbazar'),
        'email' => setting('company_email', 'colorheaven.bd@gmail.com'),
        'logo' => $logo,
    ];
}

function vp_customer($customerId): array {
    if (!$customerId || !table_exists('customers')) return [];
    $st = db()->prepare('SELECT * FROM customers WHERE id=? LIMIT 1');
    $st->execute([(int)$customerId]);
    return $st->fetch() ?: [];
}
function vp_customer_name(array $c): string { return (string)($c['customer_name'] ?? $c['company_name'] ?? $c['name'] ?? ''); }
function vp_customer_address(array $c): string { return (string)($c['address'] ?? ''); }

function vp_product_name($productId): string {
    if (!$productId || !table_exists('products')) return '';
    $nameCol = vp_pick('products', ['product_name','name','description']);
    if (!$nameCol) return '';
    $st = db()->prepare("SELECT `$nameCol` FROM products WHERE id=? LIMIT 1");
    $st->execute([(int)$productId]);
    return (string)($st->fetchColumn() ?: '');
}

function vp_load_parent(string $table, int $id): array {
    if (!$id || !table_exists($table)) return [];
    $st = db()->prepare("SELECT * FROM `$table` WHERE id=? LIMIT 1");
    $st->execute([$id]);
    return $st->fetch() ?: [];
}

function vp_items(string $itemTable, string $fk, int $parentId): array {
    if (!table_exists($itemTable) || !vp_has($itemTable, $fk)) return [];
    $st = db()->prepare("SELECT * FROM `$itemTable` WHERE `$fk`=? ORDER BY id");
    $st->execute([$parentId]);
    $rows = [];
    $nameCol = vp_pick($itemTable, ['product_name','description','item_name','goods_name']);
    $productCol = vp_pick($itemTable, ['product_id']);
    $altCol = vp_pick($itemTable, ['alt_quantity','bag_quantity','bag_qty','pack_qty']);
    $altUnitCol = vp_pick($itemTable, ['alt_unit','alt_unit_name','pack_unit']);
    $qtyCol = vp_pick($itemTable, ['quantity','qty']);
    $unitCol = vp_pick($itemTable, ['unit_name','unit']);
    $rateCol = vp_pick($itemTable, ['rate','unit_price','sales_rate']);
    $perCol = vp_pick($itemTable, ['per','rate_unit','unit_name','unit']);
    $amountCol = vp_pick($itemTable, ['amount','line_total','total_amount']);
    foreach ($st->fetchAll() as $r) {
        $name = $nameCol ? (string)($r[$nameCol] ?? '') : '';
        if (!$name && $productCol) $name = vp_product_name((int)($r[$productCol] ?? 0));
        $rows[] = [
            'name' => $name,
            'alt_qty' => $altCol ? (float)($r[$altCol] ?? 0) : 0,
            'alt_unit' => $altUnitCol ? (string)($r[$altUnitCol] ?? '') : 'Bag',
            'qty' => $qtyCol ? (float)($r[$qtyCol] ?? 0) : 0,
            'unit' => $unitCol ? (string)($r[$unitCol] ?? '') : 'Kg',
            'rate' => $rateCol ? (float)($r[$rateCol] ?? 0) : 0,
            'per' => $perCol ? (string)($r[$perCol] ?? '') : ($unitCol ? (string)($r[$unitCol] ?? '') : 'Unit'),
            'amount' => $amountCol ? (float)($r[$amountCol] ?? 0) : 0,
        ];
    }
    return $rows;
}

function vp_doc_config(string $type): array {
    if ($type === 'order') return [
        'title'=>'SALES ORDER', 'table'=>'sales_orders', 'items'=>'sales_order_items', 'fk'=>'sales_order_id',
        'perm'=>'sales_orders.view', 'no'=>['order_no','voucher_no','sales_order_no'], 'date'=>['order_date','created_at'], 'show_money'=>false,
    ];
    if ($type === 'delivery') return [
        'title'=>'DELIVERY NOTE', 'table'=>'delivery_challans', 'items'=>'delivery_challan_items', 'fk'=>'delivery_challan_id',
        'perm'=>'delivery_challans.view', 'no'=>['challan_no','delivery_no','voucher_no'], 'date'=>['challan_date','delivery_date','created_at'], 'show_money'=>false,
    ];
    return [
        'title'=>'INVOICE', 'table'=>'sales_invoices', 'items'=>'sales_invoice_items', 'fk'=>'sales_invoice_id',
        'perm'=>'sales_invoices.view', 'no'=>['invoice_no','voucher_no','sales_invoice_no'], 'date'=>['invoice_date','created_at'], 'show_money'=>true,
    ];
}

function vp_render(string $type, int $id): void {
    $cfg = vp_doc_config($type);
    require_perm($cfg['perm']);
    $parent = vp_load_parent($cfg['table'], $id);
    if (!$parent) { echo '<div class="alert alert-warning">Voucher not found.</div>'; return; }
    $noCol = vp_pick($cfg['table'], $cfg['no']);
    $dateCol = vp_pick($cfg['table'], $cfg['date']);
    $customerCol = vp_pick($cfg['table'], ['customer_id']);
    $deliveryAddressCol = vp_pick($cfg['table'], ['delivery_address','address']);
    $paymentTermsCol = vp_pick($cfg['table'], ['payment_terms','terms']);
    $totalCol = vp_pick($cfg['table'], ['total_amount','grand_total','amount']);
    $discountCol = vp_pick($cfg['table'], ['discount']);
    $vatCol = vp_pick($cfg['table'], ['vat']);
    $subtotalCol = vp_pick($cfg['table'], ['subtotal']);
    $remarksCol = vp_pick($cfg['table'], ['remarks','notes']);
    $deliveryCol = vp_pick($cfg['table'], ['delivery_challan_id','delivery_note_id']);
    $orderCol = vp_pick($cfg['table'], ['sales_order_id','order_id']);
    $customer = vp_customer($customerCol ? (int)($parent[$customerCol] ?? 0) : 0);
    $items = vp_items($cfg['items'], $cfg['fk'], (int)$parent['id']);
    $company = vp_company();
    $docNo = $noCol ? ($parent[$noCol] ?? $parent['id']) : $parent['id'];
    $docDate = $dateCol ? ($parent[$dateCol] ?? '') : '';
    $subtotal = $subtotalCol ? (float)($parent[$subtotalCol] ?? 0) : array_sum(array_column($items, 'amount'));
    $discount = $discountCol ? (float)($parent[$discountCol] ?? 0) : 0;
    $vat = $vatCol ? (float)($parent[$vatCol] ?? 0) : 0;
    $total = $totalCol ? (float)($parent[$totalCol] ?? 0) : ($subtotal - $discount + $vat);
    $altTotal = array_sum(array_column($items, 'alt_qty'));
    $qtyTotal = array_sum(array_column($items, 'qty'));
    ?>
    <style>
        .voucher{max-width:900px;margin:0 auto;background:#fff;color:#111;font-size:12px}.voucher *{box-sizing:border-box}.v-title{text-align:center;font-weight:bold;font-size:20px;border-bottom:2px solid #111;margin-bottom:0}.v-grid{display:grid;grid-template-columns:1.35fr 1fr;border-left:1px solid #111;border-right:1px solid #111}.v-left,.v-right{min-height:175px}.v-left{border-right:1px solid #111}.company{display:flex;gap:12px;padding:10px;border-bottom:1px solid #111;min-height:105px}.company img{width:90px;max-height:90px;object-fit:contain}.company h3{margin:0;font-size:17px}.box{padding:9px;border-bottom:1px solid #111;min-height:85px}.box strong{display:block}.meta{display:grid;grid-template-columns:1fr 1fr}.meta div{min-height:44px;padding:6px;border-bottom:1px solid #111;border-right:1px solid #111}.meta div:nth-child(even){border-right:0}.goods{width:100%;border-collapse:collapse;border-left:1px solid #111;border-right:1px solid #111}.goods th,.goods td{border-bottom:1px solid #111;border-right:1px solid #111;padding:6px;vertical-align:top}.goods th:last-child,.goods td:last-child{border-right:0}.num{text-align:right;white-space:nowrap}.desc{font-weight:bold}.summary{display:grid;grid-template-columns:1fr 1fr;border:1px solid #111;border-top:0}.words{padding:10px;min-height:130px;border-right:1px solid #111}.totals{padding:10px}.totals-row{display:flex;justify-content:space-between;border-bottom:1px solid #ccc;padding:4px 0}.declaration{border-left:1px solid #111;border-right:1px solid #111;padding:10px;min-height:95px}.sign{display:grid;grid-template-columns:1fr 1fr 1fr;border:1px solid #111;min-height:80px}.sign>div{border-right:1px solid #111;padding:45px 8px 6px;text-align:center}.sign>div:last-child{border-right:0}.footer-note{text-align:center;margin-top:8px}.print-actions{max-width:900px;margin:0 auto 12px}@media print{.no-print,.sidebar,.topbar{display:none!important}.main,.content{margin:0!important;padding:0!important}.voucher{max-width:none}.voucher{font-size:11px}body{background:#fff!important}}
    </style>
    <div class="print-actions no-print"><button class="btn btn-primary" onclick="window.print()">Print / Save PDF</button> <a class="btn btn-light" href="javascript:history.back()">Back</a></div>
    <div class="voucher">
        <div class="v-title"><?=vp_text($cfg['title'])?></div>
        <div class="v-grid">
            <div class="v-left">
                <div class="company">
                    <img src="<?=vp_text($company['logo'])?>" alt="Logo">
                    <div><h3><?=vp_text($company['name'])?></h3><div><?=vp_text($company['address'])?></div><div>E-Mail : <?=vp_text($company['email'])?></div><small><?=vp_text($company['tagline'])?></small></div>
                </div>
                <div class="box"><span>Consignee (Ship to)</span><strong><?=vp_text(vp_customer_name($customer))?></strong><div><?=nl2br(vp_text($deliveryAddressCol ? ($parent[$deliveryAddressCol] ?? '') : vp_customer_address($customer)))?></div></div>
                <div class="box"><span>Buyer (Bill to)</span><strong><?=vp_text(vp_customer_name($customer))?></strong><div><?=nl2br(vp_text(vp_customer_address($customer)))?></div></div>
            </div>
            <div class="v-right meta">
                <div><span><?=vp_text($cfg['title']==='INVOICE'?'Invoice No.':($cfg['title']==='DELIVERY NOTE'?'Delivery Note No.':'Order No.'))?></span><br><strong><?=vp_text($docNo)?></strong></div>
                <div><span>Dated</span><br><strong><?=vp_text(vp_date($docDate))?></strong></div>
                <div><span>Delivery Note</span><br><?=vp_text($deliveryCol ? ($parent[$deliveryCol] ?? '') : '')?></div>
                <div><span>Mode/Terms of Payment</span><br><?=vp_text($paymentTermsCol ? ($parent[$paymentTermsCol] ?? '') : '')?></div>
                <div><span>Reference No. & Date.</span><br></div><div><span>Other References</span><br></div>
                <div><span>Buyer’s Order No.</span><br><?=vp_text($orderCol ? ($parent[$orderCol] ?? '') : '')?></div><div><span>Dated</span><br></div>
                <div><span>Dispatch Doc No.</span><br></div><div><span>Delivery Note Date</span><br></div>
                <div><span>Dispatched through</span><br></div><div><span>Destination</span><br></div>
                <div style="grid-column:1/3"><span>Terms of Delivery</span><br><?=vp_text($remarksCol ? ($parent[$remarksCol] ?? '') : '')?></div>
            </div>
        </div>
        <table class="goods">
            <thead><tr><th style="width:40px">Sl<br>No.</th><th>Description of Goods</th><th class="num">Alt. Quantity</th><th class="num">Quantity</th><?php if($cfg['show_money']): ?><th class="num">Rate</th><th>per</th><th class="num">Amount</th><?php endif; ?></tr></thead>
            <tbody>
                <?php foreach($items as $i=>$it): ?>
                    <tr><td><?=($i+1)?></td><td class="desc"><?=vp_text($it['name'])?></td><td class="num"><?=vp_qty($it['alt_qty'])?> <?=vp_text($it['alt_unit'])?></td><td class="num"><?=vp_qty($it['qty'])?> <?=vp_text($it['unit'])?></td><?php if($cfg['show_money']): ?><td class="num"><?=vp_money($it['rate'])?></td><td><?=vp_text($it['per'])?></td><td class="num"><?=vp_money($it['amount'])?></td><?php endif; ?></tr>
                <?php endforeach; ?>
                <?php if(!$items): ?><tr><td colspan="<?= $cfg['show_money'] ? 7 : 4 ?>" class="text-center">No item found.</td></tr><?php endif; ?>
                <tr><td></td><td class="num"><strong>Total</strong></td><td class="num"><strong><?=vp_qty($altTotal)?> Bag</strong></td><td class="num"><strong><?=vp_qty($qtyTotal)?> Kg</strong></td><?php if($cfg['show_money']): ?><td></td><td></td><td class="num"><strong>TK <?=vp_money($total)?></strong></td><?php endif; ?></tr>
            </tbody>
        </table>
        <?php if($cfg['show_money']): ?>
        <div class="summary"><div class="words"><div>Amount Chargeable (in words)</div><strong><?=vp_text(vp_bdt_words($total))?></strong><br><br><span>E. & O.E</span></div><div class="totals"><div class="totals-row"><span>Subtotal</span><strong><?=vp_money($subtotal)?></strong></div><div class="totals-row"><span>Discount</span><strong><?=vp_money($discount)?></strong></div><div class="totals-row"><span>VAT</span><strong><?=vp_money($vat)?></strong></div><div class="totals-row"><span>Total</span><strong>TK <?=vp_money($total)?></strong></div></div></div>
        <?php else: ?>
        <div class="declaration"><strong>Note</strong><br>This document shows goods and quantity only. Rate and amount are intentionally hidden.</div>
        <?php endif; ?>
        <div class="declaration"><strong>Declaration</strong><br>We declare that this document shows the actual details of the goods described and that all particulars are true and correct.</div>
        <div class="sign"><div>Customer’s Seal and Signature</div><div>Prepared by<br><br>Verified by</div><div>for <?=vp_text($company['name'])?><br><br>Authorised Signatory</div></div>
        <div class="footer-note">This is a Computer Generated <?=vp_text(ucwords(strtolower($cfg['title'])))?></div>
    </div>
    <?php
}
?>
