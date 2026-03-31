<?php
// 数据库连接
$host = 'localhost';
$user = 'root';
$pwd = '123456';
$dbname = 'school_db';

$conn = mysqli_connect($host, $user, $pwd, $dbname);
mysqli_set_charset($conn, 'utf8mb4');

// 获取表单数据
$email = $_POST['email'];
$password = $_POST['password'];

// 1. 先查 user 表，判断账号密码是否正确
$sql = "SELECT * FROM user WHERE email = '$email' AND password = '$password'";
$result = mysqli_query($conn, $sql);
$user = mysqli_fetch_assoc($result);

if (!$user) {
    echo "账号或密码错误！<a href='login'>返回登录</a>";
    exit;
}

// 登录成功！获取 user_id
$user_id = $user['user_id'];

// ==============================
// 2. 开始判断角色（核心代码）
// ==============================

// 判断是不是 管理员
$is_admin = mysqli_fetch_row(mysqli_query($conn, "SELECT user_id FROM admin WHERE user_id = $user_id"));

// 判断是不是 老师
$is_teacher = mysqli_fetch_row(mysqli_query($conn, "SELECT user_id FROM teacher WHERE user_id = $user_id"));

// 判断是不是 学生
$is_student = mysqli_fetch_row(mysqli_query($conn, "SELECT user_id FROM student WHERE user_id = $user_id"));

// ==============================
// 3. 自动跳转到对应页面
// ==============================
session_start();
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $user['name'];

if ($is_admin) {
    header("Location: admin.php");
} elseif ($is_teacher) {
    header("Location: teacher.php");
} elseif ($is_student) {
    header("Location: student.php");
} else {
    echo "未分配角色，请联系管理员";
}
?>