<?php
/**
 * admin/classroom.php
 * 教室管理页面：复用通用资源模板维护教室信息。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$page_title = '教室管理 - 管理后台';
$adminApiUrl = app_catalog_url('admin', 'api', 'main');

$classrooms = admin_fetch_classrooms($pdo);
$resourceConfig = [
    'type' => 'classroom',
    'entity_label' => '教室',
    'page_heading' => '教室管理',
    'page_subtitle' => '',
    'list_title' => '教室列表',
    'add_action' => 'add_classroom',
    'update_action' => 'update_classroom',
    'empty_text' => '暂无教室数据',
    'count_unit' => '间',
    'id_field' => 'classroom_id',
    'action_width' => '200px',
];
$records = $classrooms;
require 'layout_head.php';
?>
<?php require app_admin_partial_path('resource_crud'); ?>
<?php require 'footer.php'; ?>
