<?php
require_once __DIR__.'/includes/ch_ui_components.php';
ch_begin_app('roles','Roles & Permissions');
ch_quick_form('Role Permission Setup', [
['label'=>'Role Name','type'=>'select','options'=>['Super Admin','Admin','Accounts','Manager','Marketer','Delivery Man']],
['label'=>'Module','type'=>'select','options'=>['Dashboard','Orders','Delivery','Invoice','Collection','Purchase','Payment','Reports','Users']],
['label'=>'Permissions','type'=>'select','options'=>['View','Add','Edit','Delete','Bulk Delete','Approve','Reject','Print','Export','SMS Send','Report View']],
['label'=>'Notes','type'=>'textarea','full'=>true]
]);
ch_module_list('Permission Matrix','Role / Module');
ch_end_app();
?>