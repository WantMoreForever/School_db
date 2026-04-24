<?php
/**
 * admin/student.php
 * 学生管理页面：维护学生账号、学号、院系信息及相关操作入口。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$adminApiUrl = app_catalog_url('admin', 'api', 'main');
$studentImportUrl = app_catalog_url('admin', 'api', 'import_students');

$departments = admin_fetch_departments($pdo);
$students = admin_fetch_students_for_management($pdo);
$crudConfig = [
    'type' => 'student',
    'entity_label' => '学生',
    'page_heading' => '学生管理',
    'page_subtitle' => '',
    'list_title' => '学生列表',
    'add_action' => 'add_student',
    'update_action' => 'update_student',
    'redirect' => 'student.php',
    'import_modal_id' => 'importStudentsModal',
    'empty_text' => '暂无学生数据',
];
$records = $students;
$importConfig = [
    'modal_id' => 'importStudentsModal',
    'form_id' => 'importStudentsForm',
    'action_url' => $studentImportUrl,
    'title' => '批量导入学生',
    'headers_text' => '姓名, 邮箱, 密码, 性别, 手机号, 学号, 入学年份, 年级, 院系, 专业',
    'required_text' => '必填字段：姓名、邮箱、密码、性别、学号、入学年份、院系。专业可留空，但若填写必须属于对应院系。',
    'file_help_text' => '建议优先使用模板生成的 Excel 文件。系统会严格校验表头、院系、专业、学号、邮箱、手机号和 UTF-8 编码。',
    'xlsx_button_id' => 'downloadStudentTemplateXlsx',
    'csv_button_id' => 'downloadStudentTemplateCsv',
    'headers' => ['姓名', '邮箱', '密码', '性别', '手机号', '学号', '入学年份', '年级', '院系', '专业'],
    'sample_rows' => [
        ['张三', 'zhangsan@school.edu', '123456', '男', '13800001234', '20240001', '2024', '大一', '计算机学院', '软件工程'],
    ],
    'xlsx_filename' => '学生导入模板.xlsx',
    'csv_filename' => '学生导入模板.csv',
    'sheet_name' => '学生模板',
];
$page_title = '学生管理 - 管理后台';
require 'layout_head.php';
?>
<?php require app_admin_partial_path('personnel_crud'); ?>
<?php require app_admin_partial_path('personnel_import_modal'); ?>

<?php require app_admin_partial_path('reset_password_modals'); ?>

<script>
window.AdminPageConfig = Object.assign({}, window.AdminPageConfig, {
    csrfToken: <?= json_encode(app_ensure_csrf_token(), JSON_UNESCAPED_UNICODE) ?>,
    defaultRedirect: 'student.php',
    resetPasswordUrl: <?= json_encode($adminApiUrl . '?act=reset_password', JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
});
</script>
<?php require 'footer.php'; ?>
