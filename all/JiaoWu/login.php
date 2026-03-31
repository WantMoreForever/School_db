<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>教务系统登录</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-box">
        <h2>教务系统登录</h2>
        <form action="login_check.php" method="post">
            邮箱：<input type="email" name="email" required><br>
            密码：<input type="password" name="password" required><br>
            <button type="submit">登录</button>
        </form>
    </div>
</body>
</html>