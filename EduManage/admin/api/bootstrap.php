<?php
/**
 * admin/api/bootstrap.php
 * 管理后台 API 启动层：加载鉴权、数据库、日志、共享工具，并准备当前请求上下文。
 */

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/shared.php';
require_once __DIR__ . '/../../components/logger.php';

$pdo = app_require_pdo();
if ($pdo === null) {
    http_response_code(500);
    exit('数据库连接失败');
}

$act = (string) ($_GET['act'] ?? '');
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
    && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

if ($isAjax) {
    if (empty($_SESSION['user_id'])) {
        admin_api_json_response(false, [
            'code' => 'ERR_UNAUTHENTICATED',
            'error' => 'unauthenticated',
            'message' => '未登录，请先登录',
        ], 401);
    }

    $adminStmt = $pdo->prepare('SELECT 1 FROM admin WHERE user_id = ? LIMIT 1');
    $adminStmt->execute([(int) $_SESSION['user_id']]);
    if (!$adminStmt->fetchColumn()) {
        admin_api_json_response(false, [
            'code' => 'ERR_FORBIDDEN',
            'error' => 'forbidden',
            'message' => '无管理员权限',
        ], 403);
    }
} else {
    admin_auth();
}

admin_api_protect_unsafe_action($act, $isAjax);
