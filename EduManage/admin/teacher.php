<?php
/**
 * admin/teacher.php
 * 教师管理页面：维护教师账号、职称、院系和状态信息。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$adminApiUrl = app_catalog_url('admin', 'api', 'main');
$teacherImportUrl = app_catalog_url('admin', 'api', 'import_teachers');

$departments = admin_fetch_departments($pdo);
$teachers = admin_fetch_teachers_for_management($pdo);
$crudConfig = [
    'type' => 'teacher',
    'entity_label' => '教师',
    'page_heading' => '教师管理',
    'page_subtitle' => '',
    'list_title' => '教师列表',
    'add_action' => 'add_teacher',
    'update_action' => 'update_teacher',
    'redirect' => 'teacher.php',
    'import_modal_id' => 'importTeachersModal',
    'empty_text' => '暂无教师数据',
];
$records = $teachers;
$importConfig = [
    'modal_id' => 'importTeachersModal',
    'form_id' => 'importTeachersForm',
    'action_url' => $teacherImportUrl,
    'title' => '批量导入教师',
    'headers_text' => '姓名, 邮箱, 密码, 性别, 手机号, 职称, 院系',
    'required_text' => '必填字段：姓名、邮箱、密码、性别、职称、院系。职称目前只支持：教授、副教授、辅导员、讲师。',
    'file_help_text' => '建议优先使用模板生成的 Excel 文件。系统会严格校验表头、职称、院系、邮箱、手机号和 UTF-8 编码。',
    'xlsx_button_id' => 'downloadTeacherTemplateXlsx',
    'csv_button_id' => 'downloadTeacherTemplateCsv',
    'headers' => ['姓名', '邮箱', '密码', '性别', '手机号', '职称', '院系'],
    'sample_rows' => [
        ['李老师', 'lilaoshi@school.edu', '123456', '女', '13800004567', '讲师', '计算机学院'],
    ],
    'xlsx_filename' => '教师导入模板.xlsx',
    'csv_filename' => '教师导入模板.csv',
    'sheet_name' => '教师模板',
];
$page_title = '教师管理 - 管理后台';
require 'layout_head.php';
?>
<?php require app_admin_partial_path('personnel_crud'); ?>
<?php require app_admin_partial_path('personnel_import_modal'); ?>

<?php require app_admin_partial_path('reset_password_modals'); ?>

<script>
window.AdminPageConfig = Object.assign({}, window.AdminPageConfig, {
    csrfToken: <?= json_encode(app_ensure_csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
    defaultRedirect: 'teacher.php',
    resetPasswordUrl: <?= json_encode($adminApiUrl . '?act=reset_password', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
});
</script>
<?php require 'footer.php'; ?>
