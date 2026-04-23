<?php
/**
 * admin/api/index.php
 * 管理后台统一 API 入口：保留原有 `act` 调用方式，并将请求分发到对应业务模块。
 */

require_once __DIR__ . '/bootstrap.php';

$handlers = [
    __DIR__ . '/personnel.php',
    __DIR__ . '/resources.php',
    __DIR__ . '/schedule.php',
    __DIR__ . '/announcement.php',
];

foreach ($handlers as $handler) {
    $handled = require $handler;
    if ($handled) {
        exit;
    }
}

admin_api_redirect('index.php');
