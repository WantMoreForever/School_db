<?php
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/auth.php';
require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();

$uid = student_api_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    student_api_json_error('仅支持 POST 请求');
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) $data = $_POST;

$posted_token = $data['csrf_token'] ?? ($_POST['csrf_token'] ?? null);
if (!student_api_validate_csrf($posted_token)) {
    student_api_json_error('CSRF 验证失败', 403, ['error' => 'invalid_csrf']);
}

$old_pwd = isset($data['old_password']) ? trim($data['old_password']) : '';
$new_pwd = isset($data['new_password']) ? trim($data['new_password']) : '';
$conf_pwd = isset($data['confirm_password']) ? trim($data['confirm_password']) : '';

if ($old_pwd === '' || $new_pwd === '' || $conf_pwd === '') {
    student_api_json_error('请填写所有字段。');
}
if (strlen($new_pwd) < 6) {
    student_api_json_error('新密码长度不能少于 6 位。');
}
if ($new_pwd !== $conf_pwd) {
    student_api_json_error('两次输入的新密码不一致。');
}
if ($new_pwd === $old_pwd) {
    student_api_json_error('新密码不能与旧密码相同。');
}
try {
    // 密码存储策略：使用 bcrypt 安全哈希（兼容登录验证中的 bcrypt/sha256/plain 验证）
    $PASSWORD_MODE = 'bcrypt';

    if ($pdo === null) {
        student_api_json_error('数据库连接不可用', 500);
    }

    $stmt = $pdo->prepare("SELECT password FROM user WHERE user_id = ?");
    $stmt->execute([$uid]);
    $stored_pwd = $stmt->fetchColumn();

    if ($stored_pwd === false) {
        student_api_json_error('用户不存在。', 404);
    }

    // Verify old password. Support multiple storage formats: bcrypt, sha256, or plain
    $verified = false;
    $stored_type = 'unknown';

    // bcrypt (password_hash)
    if (is_string($stored_pwd) && strlen($stored_pwd) > 0 && (strpos($stored_pwd, '$2y$') === 0 || strpos($stored_pwd, '$2a$') === 0 || strpos($stored_pwd, '$2b$') === 0)) {
        if (password_verify($old_pwd, $stored_pwd)) { $verified = true; $stored_type = 'bcrypt'; }
    }

    // sha256 hex (64 hex chars)
    if (!$verified && is_string($stored_pwd) && preg_match('/^[0-9a-f]{64}$/i', $stored_pwd)) {
        if (hash('sha256', $old_pwd) === $stored_pwd) { $verified = true; $stored_type = 'sha256'; }
    }

    // plaintext fallback
    if (!$verified && $stored_pwd === $old_pwd) { $verified = true; $stored_type = 'plain'; }

    if (!$verified) {
        student_api_json_error('当前密码不正确。');
    }

    // Prepare new stored value according to selected PASSWORD_MODE
    $new_stored = null;
    if ($PASSWORD_MODE === 'bcrypt') {
        $new_stored = password_hash($new_pwd, PASSWORD_DEFAULT);
        if ($new_stored === false) {
            student_api_json_error('密码哈希失败，请稍后重试', 500);
        }
    } elseif ($PASSWORD_MODE === 'sha256') {
        $new_stored = hash('sha256', $new_pwd);
    } elseif ($PASSWORD_MODE === 'plain') {
        $new_stored = $new_pwd;
    } else {
        // fallback to bcrypt
        $new_stored = password_hash($new_pwd, PASSWORD_DEFAULT);
        if ($new_stored === false) {
            student_api_json_error('密码哈希失败，请稍后重试', 500);
        }
    }

    $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
    $stmt->execute([$new_stored, $uid]);

    // Verify update by re-selecting the stored password
    $verifyStmt = $pdo->prepare("SELECT password FROM user WHERE user_id = ?");
    $verifyStmt->execute([$uid]);
    $after_pwd = $verifyStmt->fetchColumn();
    if ($after_pwd === $new_stored) {
        if (function_exists('sys_log')) {
            sys_log($pdo, $uid, sys_log_build('更新密码', [
                'user_id' => $uid,
            ]), 'user', $uid);
        }
        student_api_json_ok(['success' => true, 'message' => '密码已修改成功！']);
    } else {
        error_log("change_pwd: update mismatch for user_id={$uid}");
        student_api_json_error('密码修改失败：数据库值与预期不匹配', 500);
    }
} catch (PDOException $e) {
    student_api_json_error('数据库错误：' . $e->getMessage(), 500);
}
