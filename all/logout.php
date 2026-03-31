<?php
/**
 * 退出登录
 * 销毁 session 并跳转回 JiaoWu 登录页面
 */
session_start();
session_unset();
session_destroy();
header('Location: JiaoWu/login.php');
exit;
