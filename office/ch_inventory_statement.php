<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('inventory_statement','Inventory Statement');
?>
<div class="ch-stats mb-4"><?php ch_stat_card('Opening Stock','1,250 Kg','bi-box','primary'); ch_stat_card('Total In','780 Kg','bi-arrow-down-circle','success'); ch_stat_card('Issued Stock','420 Kg','bi-arrow-up-circle','danger'); ch_stat_card('Closing Stock','1,610 Kg','bi-stack','secondary'); ?></div>
<?php ch_module_list('Inventory Statement','Product'); ch_end_app(); ?>