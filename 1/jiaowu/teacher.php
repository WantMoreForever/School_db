<?php session_start(); ?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>登录成功 — 教务系统</title>
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body {
    min-height: 100vh;
    background: linear-gradient(135deg, #e8f0fe 0%, #f5f7fa 60%, #e3f0ff 100%);
    display: flex; align-items: center; justify-content: center;
    font-family: "Microsoft YaHei", "PingFang SC", sans-serif;
}
.card {
    background: #fff;
    border-radius: 20px;
    box-shadow: 0 8px 40px rgba(45,103,234,0.12), 0 2px 8px rgba(0,0,0,0.06);
    padding: 48px 52px 40px;
    text-align: center;
    max-width: 440px;
    width: 90%;
    animation: fadeUp .45s ease;
}
@keyframes fadeUp {
    from { opacity: 0; transform: translateY(24px); }
    to   { opacity: 1; transform: translateY(0); }
}
.avatar {
    width: 76px; height: 76px; border-radius: 50%;
    background: linear-gradient(135deg, #2d67ea, #5856d6);
    display: flex; align-items: center; justify-content: center;
    font-size: 30px; color: #fff; font-weight: 700;
    margin: 0 auto 20px;
    box-shadow: 0 4px 16px rgba(45,103,234,0.3);
}
.badge {
    display: inline-block; padding: 3px 12px;
    background: #e8f0fe; color: #2d67ea;
    border-radius: 20px; font-size: 12px; font-weight: 600;
    margin-bottom: 14px;
}
.greeting {
    font-size: 22px; font-weight: 700; color: #1a1a2e;
    margin-bottom: 8px;
}
.sub {
    font-size: 14px; color: #6e6e73; margin-bottom: 32px;
    line-height: 1.7;
}
.btn-main {
    display: block; width: 100%;
    padding: 13px 0;
    background: linear-gradient(90deg, #2d67ea, #5856d6);
    color: #fff; border: none; border-radius: 10px;
    font-size: 15px; font-weight: 600; cursor: pointer;
    text-decoration: none;
    transition: opacity .2s, transform .15s;
    margin-bottom: 12px;
}
.btn-main:hover { opacity: .88; transform: translateY(-1px); }
.btn-ghost {
    display: block; width: 100%;
    padding: 12px 0;
    background: transparent; color: #6e6e73;
    border: 1px solid #e0e0e0; border-radius: 10px;
    font-size: 14px; cursor: pointer;
    text-decoration: none;
    transition: background .15s, color .15s;
}
.btn-ghost:hover { background: #f5f5f5; color: #333; }
.divider {
    border: none; border-top: 1px solid #f0f0f0;
    margin: 24px 0 20px;
}
.info-row {
    display: flex; justify-content: space-between;
    font-size: 13px; padding: 6px 0;
    border-bottom: 1px solid #f5f5f5;
    color: #555;
}
.info-row:last-child { border-bottom: none; }
.info-row span:first-child { color: #aaa; }
</style>
</head>
<body>

<div class="card">
    <?php
    $name    = $_SESSION['user_name'] ?? '教师';
    preg_match('/./u', $name, $m);
    $initial = $m[0] ?? '师';
    ?>
    <div class="avatar"><?= htmlspecialchars($initial) ?></div>

    <div class="badge">✅ 身份验证通过</div>
    <div class="greeting">欢迎回来，<?= htmlspecialchars($name) ?></div>
    <div class="sub">
        您已成功登录教务系统教师端<br>
        请进入教师门户查看您的课程与学生信息
    </div>

    <a class="btn-main" href="/../all/index.php">🏠 进入教师门户</a>
    <a class="btn-ghost" href="login.php">← 重新登录其他账号</a>

    <hr class="divider">
    <div style="font-size:12px;color:#aaa;text-align:left;margin-bottom:10px">当前 Session 信息</div>
    <div class="info-row">
        <span>用户名</span>
        <span><?= htmlspecialchars($_SESSION['user_name'] ?? '—') ?></span>
    </div>
    <div class="info-row">
        <span>User ID</span>
        <span><?= htmlspecialchars((string)($_SESSION['user_id'] ?? '—')) ?></span>
    </div>
    <div class="info-row">
        <span>登录时间</span>
        <span><?= date('Y-m-d H:i') ?></span>
    </div>
</div>

</body>
</html>
