<?php
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/logger.php';

app_start_session();
$pdo = app_db();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . app_login_url());
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$password = (string) ($_POST['password'] ?? '');

if ($email === '' || $password === '' || $pdo === null) {
    header('Location: ' . app_login_url('error=' . urlencode('请输入正确的账号和密码。')));
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM user WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !app_password_verify_compat($password, $user['password'] ?? '')) {
    header('Location: ' . app_login_url('error=' . urlencode('账号或密码错误。')));
    exit;
}

$status = $user['status'] ?? null;
if (!is_string($status) || $status !== 'active') {
    header('Location: ' . app_login_url('error=' . urlencode('账号已被停用或封禁，请联系管理员。')));
    exit;
}

$role = app_find_user_role($pdo, (int) $user['user_id']);
if ($role === null) {
    header('Location: ' . app_login_url('error=' . urlencode('当前账号未分配系统角色，请联系管理员。')));
    exit;
}

app_login_user($pdo, $user);
sys_log($pdo, (int) $user['user_id'], sys_log_build('登录成功', [
    'user_id' => (int) $user['user_id'],
]), 'user', (int) $user['user_id']);

app_redirect_by_role($role);
