<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('settings','Settings');
ch_quick_form('Business Settings', [
['label'=>'Business Name','value'=>'Color Heaven'],
['label'=>'Business Mobile'],
['label'=>'Email','type'=>'email'],
['label'=>'Website'],
['label'=>'Address','type'=>'textarea','full'=>true]
]);
ch_end_app();
?>