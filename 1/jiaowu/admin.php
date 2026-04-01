<?php session_start(); ?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>管理员面板</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="container">
        <h2>管理员面板</h2>
        <p>欢迎你，<?=$_SESSION['user_name']?>（管理员）</p>
        <a href="logout">退出登录</a>
    </div>
</body>
</html>