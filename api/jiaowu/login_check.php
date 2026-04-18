<?php
session_start(); // ← 必须放在最顶部，任何输出之前
$_SESSION['test'] = 'ok';
$host = 'localhost';
$dbUser = 'root';       // ← 改名，避免与 $user 冲突（虽然不是直接原因，但是好习惯）
$pwd = 'yaoxicheng';
$dbname = 'school_db';

$conn = mysqli_connect($host, $dbUser, $pwd, $dbname);
mysqli_set_charset($conn, 'utf8mb4');

$email = $_POST['email'];
$password = $_POST['password'];

// 使用预处理语句，防止 SQL 注入
$stmt = mysqli_prepare($conn, "SELECT * FROM user WHERE email = ? AND password = ?");
mysqli_stmt_bind_param($stmt, 'ss', $email, $password);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "账号或密码错误！<a href='login'>返回登录</a>";
    exit;
}

$user_id = $user['user_id'];

$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $user['name']; // 确认数据库字段名是否真的是 name

// 角色判断（同样建议改用预处理）
$stmt = mysqli_prepare($conn, "SELECT user_id FROM admin WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$is_admin = mysqli_stmt_get_result($stmt)->num_rows > 0;

$stmt = mysqli_prepare($conn, "SELECT user_id FROM teacher WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$is_teacher = mysqli_stmt_get_result($stmt)->num_rows > 0;

$stmt = mysqli_prepare($conn, "SELECT user_id FROM student WHERE user_id = ?");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$is_student = mysqli_stmt_get_result($stmt)->num_rows > 0;

if ($is_admin) {
    header("Location: admin.php");
} elseif ($is_teacher) {
    header("Location: teacher.php");
} elseif ($is_student) {
    header("Location: student.php");
} else {
    echo "未分配角色，请联系管理员";
}
exit;
?>