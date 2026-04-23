<?php
/**
 * admin/department.php
 * 院系管理页面：复用通用资源模板维护院系基础数据。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$page_title = '院系管理 - 管理后台';
$adminApiUrl = app_catalog_url('admin', 'api', 'main');

$departments = admin_fetch_departments_with_stats($pdo);
$resourceConfig = [
    'type' => 'department',
    'entity_label' => '院系',
    'page_heading' => '院系管理',
    'page_subtitle' => '',
    'list_title' => '院系列表',
    'add_action' => 'add_department',
    'update_action' => 'update_department',
    'empty_text' => '暂无院系数据',
    'count_unit' => '个',
    'id_field' => 'dept_id',
    'action_width' => '160px',
];
$records = $departments;
require 'layout_head.php';
?>
<?php require app_admin_partial_path('resource_crud'); ?>
<?php require 'footer.php'; ?>
