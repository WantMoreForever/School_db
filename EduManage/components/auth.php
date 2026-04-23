<?php
// components/auth.php

require_once __DIR__ . '/bootstrap.php';

app_start_session();

function requireStudentLogin(): int
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        header('Location: ' . app_login_url());
        exit;
    }

    $pdo = app_db();
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare('SELECT status FROM user WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int) $uid]);
            $status = $stmt->fetchColumn();
            if (!is_string($status) || $status !== 'active') {
                app_logout_and_redirect(app_login_url('error=' . urlencode('账号已被停用或封禁，请联系管理员。')));
            }
        } catch (Throwable $e) {
            app_logout_and_redirect(app_login_url('error=' . urlencode('无法验证账号状态，请联系管理员。')));
        }
    }

    return (int) $uid;
}
