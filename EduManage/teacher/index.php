<?php
/**
 * 教师门户入口
 * 通过登录入口系统验证身份后方可访问。
 * 登录成功后会设置 $_SESSION['user_id']，本页面读取并验证教师身份。
 */
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/db.php';

app_start_session();

$userId = app_current_user_id() ?? app_session_numeric_value('teacher_id');

if (!$userId) {
    header('Location: ' . app_login_url());
    exit;
}

try {
    $pdo = app_require_pdo();
    $teacherId = app_current_teacher_id($pdo);
    if ($teacherId === null) {
        app_logout_and_redirect(app_login_url('error=' . urlencode('当前账号没有教师访问权限，请重新登录。')));
    }
    $teacherProfile = app_current_teacher_profile($pdo, $teacherId);
} catch (Throwable $e) {
    error_log('[teacher.index] ' . $e->getMessage());
    http_response_code(500);
    echo '<p style="font-family:sans-serif;color:red">教师端初始化失败，请检查数据库连接或稍后重试。</p>';
    exit;
}

$html = file_get_contents(__DIR__ . '/index.html');
if ($html === false) {
    http_response_code(500);
    echo '<p style="font-family:sans-serif;color:red">教师门户页面加载失败。</p>';
    exit;
}

$withVersion = static function (string $url, string $versionKey): string {
    $version = (string) app_config('frontend.asset_versions.' . $versionKey, '');
    if ($url === '' || $version === '') {
        return $url;
    }

    return $url . (strpos($url, '?') === false ? '?' : '&') . $version;
};

$teacherProfile = is_array($teacherProfile ?? null) ? $teacherProfile : [];
$bootName = (string) ($teacherProfile['name'] ?? $_SESSION['user_name'] ?? '');
$bootEmail = (string) ($teacherProfile['email'] ?? $_SESSION['user_email'] ?? '');
$bootTitle = (string) ($teacherProfile['title'] ?? '教师');
$bootDeptName = (string) ($teacherProfile['dept_name'] ?? '');
$bootDeptCode = (string) ($teacherProfile['dept_code'] ?? '');
$bootAvatarUrl = (string) ($teacherProfile['avatar_url'] ?? app_default_avatar_url());

$headScripts = [
    '<script src="' . app_frontend_paths_script_url() . '"></script>',
    '<script src="' . $withVersion(app_url('teacher/app_api.js'), 'teacher_app_api_js') . '"></script>',
    '<script>window.TEACHER_BOOT=' . json_encode([
        'teacher_id' => (int) $teacherId,
        'user_id' => (int) ($_SESSION['user_id'] ?? $userId),
        'user_name' => $bootName,
        'user_email' => $bootEmail,
        'title' => $bootTitle,
        'dept_name' => $bootDeptName,
        'dept_code' => $bootDeptCode,
        'avatar_url' => $bootAvatarUrl,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>',
];
$replacements = [
    'href="../css/portal-common.css"' => 'href="' . $withVersion(app_catalog_url('assets', 'portal_common_css'), 'portal_common_css') . '"',
    'href="style.css"' => 'href="' . $withVersion(app_teacher_css_url(), 'teacher_css') . '"',
    '</head>' => implode('', $headScripts) . '</head>',
    "window.location.href='/login/logout.php'" => "window.location.href='" . app_logout_url() . "'",
];

echo strtr($html, $replacements);
