<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('ageing','Ageing Report');
?>
<div class="ch-stats mb-4"><?php ch_stat_card('0-30 Days','৳ 12.30L','bi-hourglass','success'); ch_stat_card('31-60 Days','৳ 18.70L','bi-hourglass-split','warning'); ch_stat_card('61-90 Days','৳ 9.20L','bi-clock-history','warning'); ch_stat_card('120+ Days','৳ 32.46L','bi-exclamation-circle','danger'); ?></div>
<?php ch_module_list('Customer / Supplier Ageing','Party'); ch_end_app(); ?>