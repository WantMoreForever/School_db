<?php
/**
 * 教师门户入口
 * 通过登录入口系统验证身份后方可访问。
 * 登录成功后会设置 $_SESSION['user_id']，本页面读取并验证教师身份。
 */
require_once __DIR__ . '/../components/bootstrap.php';

app_start_session();

$userId = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? null;

if (!$userId) {
    header('Location: ' . app_login_url());
    exit;
}

require_once __DIR__ . '/api/config.php';

try {
    $pdo = get_pdo();
    $stmt = $pdo->prepare('SELECT user_id FROM teacher WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $userId]);
    if (!$stmt->fetch()) {
        session_destroy();
        header('Location: ' . app_login_url('err=not_teacher'));
        exit;
    }

    $_SESSION['teacher_id'] = (int) $userId;
} catch (Exception $e) {
    http_response_code(500);
    echo '<p style="font-family:sans-serif;color:red">数据库连接失败，请检查配置。</p>';
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

$headScripts = [
    '<script src="' . app_frontend_paths_script_url() . '"></script>',
    '<script src="' . $withVersion(app_url('teacher/app_api.js'), 'teacher_app_api_js') . '"></script>',
];
$replacements = [
    'href="../css/portal-common.css"' => 'href="' . $withVersion(app_catalog_url('assets', 'portal_common_css'), 'portal_common_css') . '"',
    'href="style.css"' => 'href="' . $withVersion(app_teacher_css_url(), 'teacher_css') . '"',
    '</head>' => implode('', $headScripts) . '</head>',
    "window.location.href='/login/logout.php'" => "window.location.href='" . app_logout_url() . "'",
];

echo strtr($html, $replacements);
