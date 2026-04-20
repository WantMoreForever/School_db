<?php
/**
 * 教师门户入口
 * 通过 JiaoWu 登录系统验证身份后方可访问。
 * JiaoWu 登录成功后设置 $_SESSION['user_id']，本页面读取并验证教师身份。
 */
session_start();

// 构建完整的基础 URL（兼容子目录部署）
$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base_url = $scheme . '://' . $host . $base;

$user_id = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? null;

// 未登录 → 跳转到 JiaoWu 登录页
if (!$user_id) {
    header('Location: ' . $base_url . '/JiaoWu/login.php');
    exit;
}

// 验证是否为教师角色
require_once __DIR__ . '/api/config.php';
try {
    $pdo  = get_pdo();
    $stmt = $pdo->prepare('SELECT user_id FROM teacher WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int)$user_id]);
    if (!$stmt->fetch()) {
        session_destroy();
        header('Location: ' . $base_url . '/JiaoWu/login.php?err=not_teacher');
        exit;
    }
    $_SESSION['teacher_id'] = (int)$user_id;
} catch (Exception $e) {
    http_response_code(500);
    echo '<p style="font-family:sans-serif;color:red">数据库连接失败，请检查配置。</p>';
    exit;
}

// 身份验证通过 → 输出教师门户页面
readfile(__DIR__ . '/index.html');
