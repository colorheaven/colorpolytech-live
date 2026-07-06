<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('sms','SMS Settings');
ch_quick_form('SMS Gateway Settings',[
['label'=>'Enable SMS','type'=>'select','options'=>['Active','Inactive']],
['label'=>'Gateway Name','value'=>'FastSMSBD'],
['label'=>'API URL'],
['label'=>'Sender ID'],
['label'=>'Username / API Key'],
['label'=>'Password / Token'],
['label'=>'Test SMS Number'],
['label'=>'Default Template','type'=>'textarea','full'=>true]
]);
ch_module_list('SMS Log','Receiver');
ch_end_app();
?>