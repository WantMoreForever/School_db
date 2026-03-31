<?php
/**
 * 临时诊断页面 — 检查当前 Session 和数据库状态
 * 使用完毕后请删除此文件
 */
session_start();
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/api/config.php';

echo '<pre style="font-family:monospace;font-size:13px;padding:20px">';
echo "=== Session 状态 ===\n";
echo "user_id    = " . ($_SESSION['user_id'] ?? '未设置') . "\n";
echo "user_name  = " . ($_SESSION['user_name'] ?? '未设置') . "\n";
echo "teacher_id = " . ($_SESSION['teacher_id'] ?? '未设置') . "\n\n";

$uid = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? null;
if (!$uid) {
    echo "❌ 未登录，请先通过 JiaoWu/login.php 登录\n";
    echo '</pre>';
    exit;
}

try {
    $pdo = get_pdo();
    echo "✅ 数据库连接成功\n\n";

    // 1. user 表
    $row = $pdo->prepare('SELECT user_id, name, email FROM user WHERE user_id = ?');
    $row->execute([$uid]);
    $u = $row->fetch();
    echo "=== user 表 (user_id={$uid}) ===\n";
    if ($u) echo "✅ 存在: name={$u['name']}, email={$u['email']}\n\n";
    else     echo "❌ 不存在！user 表中没有 user_id={$uid} 的记录\n\n";

    // 2. teacher 表
    $row = $pdo->prepare('SELECT user_id, title, dept_id FROM teacher WHERE user_id = ?');
    $row->execute([$uid]);
    $t = $row->fetch();
    echo "=== teacher 表 (user_id={$uid}) ===\n";
    if ($t) echo "✅ 存在: title={$t['title']}, dept_id={$t['dept_id']}\n\n";
    else     echo "❌ 不存在！teacher 表中没有 user_id={$uid} 的记录\n    → 解决：INSERT INTO teacher (user_id, dept_id) VALUES ({$uid}, 1);\n\n";

    // 3. department 表
    if ($t) {
        $row = $pdo->prepare('SELECT dept_id, dept_name, dept_code FROM department WHERE dept_id = ?');
        $row->execute([$t['dept_id']]);
        $d = $row->fetch();
        echo "=== department 表 (dept_id={$t['dept_id']}) ===\n";
        if ($d) echo "✅ 存在: dept_name={$d['dept_name']}, dept_code={$d['dept_code']}\n\n";
        else     echo "❌ dept_id={$t['dept_id']} 在 department 表中不存在！\n    → 解决：INSERT INTO department (dept_name, dept_code) VALUES ('计算机学院', 'CS');\n\n";
    }

    // 4. 模拟 sp_get_teacher_info
    echo "=== 模拟 sp_get_teacher_info ===\n";
    $stmt = $pdo->prepare('
        SELECT u.user_id, u.name, u.email, t.title, d.dept_name, d.dept_code
        FROM user u
        JOIN teacher t ON u.user_id = t.user_id
        JOIN department d ON t.dept_id = d.dept_id
        WHERE u.user_id = ?
    ');
    $stmt->execute([$uid]);
    $r = $stmt->fetch();
    if ($r) {
        echo "✅ 查询成功！\n";
        print_r($r);
    } else {
        echo "❌ 查询结果为空 → 这就是 \"Teacher not found\" 的原因\n";
        echo "   三表 JOIN 失败，请检查上方哪步出现 ❌\n";
    }

    echo "\n=== 当前所有 department ===\n";
    foreach ($pdo->query('SELECT dept_id, dept_name, dept_code FROM department') as $d) {
        echo "  dept_id={$d['dept_id']}, name={$d['dept_name']}, code={$d['dept_code']}\n";
    }

    echo "\n=== 当前所有 teacher ===\n";
    foreach ($pdo->query('SELECT t.user_id, u.name, t.dept_id FROM teacher t JOIN user u ON u.user_id=t.user_id') as $t) {
        echo "  user_id={$t['user_id']}, name={$t['name']}, dept_id={$t['dept_id']}\n";
    }

} catch (Exception $e) {
    echo "❌ 数据库错误：" . $e->getMessage() . "\n";
}

echo '</pre>';
echo '<p style="font-family:sans-serif;color:gray;font-size:12px">使用完毕请删除此文件：check.php</p>';
