<?php
/**
 * 退出登录
 * 销毁 session 并跳转回 JiaoWu 登录页面
 */
session_start();
session_unset();
session_destroy();
header('Location: ' . dirname($_SERVER['SCRIPT_NAME']) . '/JiaoWu/login.php');
exit;
