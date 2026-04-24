<?php

/**
 * 数据库连接配置
 *
 * 使用原则：
 * - 本文件中的固定值是本地开发和默认部署的主配置来源
 * - 环境变量仅作为可选覆盖，不配置环境变量时项目也应可直接运行
 * - 需要人工调整的数据库运行参数，优先直接修改本文件
 *
 * 说明：
 * - 当前默认值面向本地 phpStudy / MySQL 演示环境
 * - 生产部署时建议按目标数据库信息改这里，必要时再配环境变量覆盖
 */

$config = [
    // 数据库主机地址。
    'host' => 'localhost',

    // 数据库端口。
    'port' => 3306,

    // 数据库名称。
    'database' => 'school_db',

    // 数据库用户名。
    'username' => 'root',

    // 数据库密码。
    // 当前默认值面向本地开发环境。
    'password' => 'yaoxicheng',

    // 连接字符集。
    'charset' => 'utf8mb4',
];

$dbHost = getenv('DB_HOST');
if (is_string($dbHost) && trim($dbHost) !== '') {
    $config['host'] = trim($dbHost);
}

$dbPort = getenv('DB_PORT');
if (is_string($dbPort) && trim($dbPort) !== '') {
    $config['port'] = (int) $dbPort;
}

$dbName = getenv('DB_NAME');
if (is_string($dbName) && trim($dbName) !== '') {
    $config['database'] = trim($dbName);
}

$dbUser = getenv('DB_USER');
if (is_string($dbUser) && trim($dbUser) !== '') {
    $config['username'] = trim($dbUser);
}

$dbPass = getenv('DB_PASS');
if ($dbPass !== false) {
    $config['password'] = (string) $dbPass;
}

$dbCharset = getenv('DB_CHARSET');
if (is_string($dbCharset) && trim($dbCharset) !== '') {
    $config['charset'] = trim($dbCharset);
}

return $config;
