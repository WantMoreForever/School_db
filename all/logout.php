<?php
/**
 * 退出登录
 * 销毁 session 并跳转回 JiaoWu 登录页面
 */
session_start();
session_unset();
session_destroy();

$scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
header('Location: ' . $scheme . '://' . $host . $base . '/JiaoWu/login.php');
exit;
