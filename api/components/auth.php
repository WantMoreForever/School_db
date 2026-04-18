<?php
// components/auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * 获取当前登录 ID，并写入 session。
 *
 * ★ 【联动修改 - 登录完成后】
 *   目前因登录页未完成，使用固定 user_id = 1 作为测试账号。
 *   正式上线时，删除 $FIXED_UID 相关代码，改为：
 *
 *     $uid = $_SESSION['user_id'] ?? null;
 *     if (!$uid) { header('Location: login.php'); exit; }
 *     return (int)$uid;
 *
 * @return int 当前学生 user_id
 */
function requireStudentLogin(): int {

    // ↓↓↓ 临时测试账号，登录页完成后删除这 3 行 ↓↓↓
  //  $FIXED_UID = 1;
   // $_SESSION['user_id'] = $FIXED_UID;
   // return $FIXED_UID;
    // ↑↑↑ 临时测试账号结束 ↑↑↑

    // ── 正式上线启用以下代码 ──────────────────────────────
    $uid = $_SESSION['user_id'] ?? null;
     if (!$uid) { header('Location: login.php'); exit; }
     return (int)$uid;
}