<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('ledger','Ledger');
?>
<div class="ch-stats mb-4"><?php ch_stat_card('Customer Receivable','৳ 72.66L','bi-arrow-up-right','danger'); ch_stat_card('Supplier Payable','৳ 31.40L','bi-building','warning'); ch_stat_card('Ledger Entries','1,245','bi-book','primary'); ch_stat_card('Reports Ready','Yes','bi-check2-circle','success'); ?></div>
<?php ch_module_list('Customer / Supplier Ledger','Customer/Supplier'); ch_end_app(); ?>