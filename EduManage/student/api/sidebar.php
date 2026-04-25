<?php
// API: student/api/sidebar.php
// 独立返回侧栏所需数据，供前端 JS 渲染

ini_set('display_errors', '0');

require_once __DIR__ . '/helpers.php';
student_api_bootstrap();
student_api_no_cache();

require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/student_data.php';

$uid = student_api_require_login();

$view = $_GET['view'] ?? 'portal';
$allowed_views = ['portal', 'profile', 'schedule', 'free_classroom', 'course', 'grades', 'exam', 'change_pwd', 'announcement'];
if (!in_array($view, $allowed_views, true)) $view = 'portal';

$student = null;
if ($pdo !== null) {
    $student = getStudentBaseInfo($pdo, (int)$uid);
}

$out = [
    'success' => true,
    'view' => $view,
    'student' => student_api_utf8($student),
    'meta' => [
        'school_name' => '吉林大学 教学管理系统',
        'portal_tag' => 'Jilin University Teaching Management System',
        'version' => 'v2.0 · school_db',
        'logout_url' => app_logout_url(),
    ],
];

student_api_json_ok($out);
