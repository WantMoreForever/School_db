<?php
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';

$uid = requireStudentLogin();

$success_msg = '';
$error_msg   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $old_pwd  = $_POST['old_password']     ?? '';
    $new_pwd  = $_POST['new_password']     ?? '';
    $conf_pwd = $_POST['confirm_password'] ?? '';

    if (!$old_pwd || !$new_pwd || !$conf_pwd) {
        $error_msg = '请填写所有字段。';
    } elseif (strlen($new_pwd) < 8) {
        $error_msg = '新密码长度不能少于 8 位。';
    } elseif ($new_pwd !== $conf_pwd) {
        $error_msg = '两次输入的新密码不一致。';
    } elseif ($new_pwd === $old_pwd) {
        $error_msg = '新密码不能与旧密码相同。';
    } else {
        $stmt = $pdo->prepare("SELECT password FROM user WHERE user_id = ?");
        $stmt->execute([$uid]);
        $stored_pwd = $stmt->fetchColumn();

        if ($stored_pwd !== $old_pwd) {
            $error_msg = '当前密码不正确。';
        } else {
            $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $stmt->execute([$new_pwd, $uid]);
            $success_msg = '密码已修改成功！';
        }
    }
}
$activePage = 'change_pwd';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>修改密码 · 吉林大学教学管理系统</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/change_pwd.css">
</head>
<body>
<?php include __DIR__ . '/../components/student_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">修改密码</div>
        <div class="topbar-right">
            <div class="status-dot" title="在线"></div>
            <div class="topbar-time" id="clock"><?= date('Y-m-d H:i:s') ?></div>
        </div>
    </header>
    <div class="content">
<?php include __DIR__ . '/../components/alerts.php'; ?>

        <div class="pwd-tips-card fade-up">
            <div class="tips-icon">🛡</div>
            <div class="tips-body">
                <div class="tips-title">密码安全建议</div>
                <div class="tips-list">
                    至少 8 位 · 包含大小写字母与数字 · 避免使用生日或学号 · 定期更换
                </div>
            </div>
        </div>

        <div class="pwd-card fade-up delay-1">
            <div class="pwd-card-title">🔒 修改登录密码</div>
            <form action="change_pwd.php" method="POST" id="pwdForm" class="pwd-form" autocomplete="off">
                <div class="form-row">
                    <label class="form-label" for="oldPwd">当前密码</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="oldPwd"
                               name="old_password" placeholder="请输入当前密码"
                               autocomplete="current-password">
                        <button type="button" class="eye-btn" data-target="oldPwd" title="显示/隐藏">👁</button>
                    </div>
                </div>
                <div class="form-row">
                    <label class="form-label" for="newPwd">新密码</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="newPwd"
                               name="new_password" placeholder="至少 8 位"
                               autocomplete="new-password">
                        <button type="button" class="eye-btn" data-target="newPwd" title="显示/隐藏">👁</button>
                    </div>
                    <div class="strength-bar-wrap">
                        <div class="strength-bar" id="strengthBar"></div>
                    </div>
                    <div class="strength-label" id="strengthLabel"></div>
                </div>

                <div class="form-row">
                    <label class="form-label" for="confPwd">确认新密码</label>
                    <div class="input-wrap">
                        <input class="form-input" type="password" id="confPwd"
                               name="confirm_password" placeholder="再次输入新密码"
                               autocomplete="new-password">
                        <button type="button" class="eye-btn" data-target="confPwd" title="显示/隐藏">👁</button>
                    </div>
                    <div class="match-hint" id="matchHint"></div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">确认修改</button>
                    <a href="profile.php" class="btn-ghost">取消</a>
                </div>
            </form>
        </div>

        <div class="pwd-note fade-up delay-2">
            <span class="note-icon">ℹ️</span>
            密码修改成功后当前登录状态保持不变，下次登录请使用新密码。
        </div>

    </div>
</main>

<script src="/js/clock.js"></script>
<script src="/js/change_pwd.js"></script>
</body>
</html>