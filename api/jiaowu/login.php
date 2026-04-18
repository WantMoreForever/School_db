<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$pdo = null;
$db_error = null;
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=school_db;charset=utf8mb4",
        'root',
        'yaoxicheng',
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error_msg = '请输入邮箱和密码。';
    } elseif (!empty($db_error)) {
        $error_msg = '数据库连接失败：' . $db_error;
    } else {
        $stmt = $pdo->prepare('SELECT * FROM user WHERE email = :email AND password = :password LIMIT 1');
        $stmt->execute([':email' => $email, ':password' => $password]);
        $user = $stmt->fetch();

        if (!$user) {
            $error_msg = '账号或密码错误。';
        } else {
            $_SESSION['user_id']   = $user['user_id'];
            $_SESSION['user_name'] = $user['name'];
            $uid = $user['user_id'];

            $stmt = $pdo->prepare('SELECT user_id FROM admin WHERE user_id = :uid LIMIT 1');
            $stmt->execute([':uid' => $uid]);
            if ($stmt->fetchColumn()) {
                header('Location: /all/admin.php');
                exit;
            }

            $stmt = $pdo->prepare('SELECT user_id FROM teacher WHERE user_id = :uid LIMIT 1');
            $stmt->execute([':uid' => $uid]);
            if ($stmt->fetchColumn()) {
                header('Location: /jiaowu/teacher.php');
                exit;
            }

            $stmt = $pdo->prepare('SELECT user_id FROM student WHERE user_id = :uid LIMIT 1');
            $stmt->execute([':uid' => $uid]);
            if ($stmt->fetchColumn()) {
                header('Location: /student/student_portal.php');
                exit;
            }

            $error_msg = '未分配角色，请联系管理员。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>教务系统登录</title>
    <link rel="stylesheet" href="/jiaowu/style.css">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
    <div class="login-box">
        <h2>教务系统登录</h2>
        <?php if (!empty($error_msg)): ?>
            <p style="color:red;"><?= htmlspecialchars($error_msg) ?></p>
        <?php endif; ?>
        <form action="" method="post">
            邮箱：<input type="email" name="email" required value="<?= htmlspecialchars($email ?? '') ?>"><br>
            密码：<input type="password" name="password" required><br>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>