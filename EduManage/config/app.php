<?php

/**
 * 应用基础运行配置
 *
 * 使用原则：
 * - 本文件中的固定值是应用运行时的主配置来源
 * - 环境变量仅作为可选覆盖，不配置环境变量时项目也应可直接运行
 * - 本地开发优先直接修改本文件，不要求配置系统环境变量、Web 服务器环境变量或 .env
 *
 * 覆盖规则：
 * - 先读取本文件中的固定默认值
 * - 如果环境变量存在且有效，再按同名项覆盖
 */

$config = [
    // 当前应用环境标识。
    // 本地开发建议保持 local，生产部署可改为 production。
    'env' => 'local',

    // 是否开启调试模式。
    'debug' => false,

    // 应用默认时区。
    'timezone' => 'Asia/Shanghai',

    // 应用默认字符集。
    'charset' => 'UTF-8',

    // 全局静态资源版本号。
    'asset_version' => '20260423a',

    'session' => [
        // Session 保存目录兜底路径。
        'fallback_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php_sessions',

        // Session Cookie 安全参数。
        // 本地开发默认不强制 secure，生产环境建议设为 true。
        'cookie' => [
            'path' => '/',
            'domain' => '',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
            'secure_in_production' => true,
            'secure_when_https' => true,
        ],
    ],

    'logging' => [
        'retention_days' => 30,
        'important_keywords' => [
            '成功', '失败', '创建', '删除', '更新', '分配', '开放', '选课', '退选', '登录', '登出',
            '提交', '通过', '拒绝', '导出', '导入', '上传', '审核', '批准', '取消', '锁定', '解锁',
            'SUCCESS', 'FAILED', 'CREATE', 'DELETE', 'UPDATE', 'ENROLL', 'DROP', 'LOGIN',
            'ASSIGN', 'OPEN', 'CLOSE', 'APPROVE', 'REJECT', 'EXPORT', 'IMPORT', 'UPLOAD',
        ],
        'ignore_regex' => '/(SELECT|GET|FETCH|COUNT|LIST|QUERY|READ|SHOW)/i',
    ],
];

$appEnv = getenv('APP_ENV');
if (is_string($appEnv) && trim($appEnv) !== '') {
    $config['env'] = trim($appEnv);
}

$appDebug = getenv('APP_DEBUG');
if ($appDebug !== false && $appDebug !== '') {
    $config['debug'] = filter_var($appDebug, FILTER_VALIDATE_BOOLEAN);
}

$appTimezone = getenv('APP_TIMEZONE');
if (is_string($appTimezone) && trim($appTimezone) !== '') {
    $config['timezone'] = trim($appTimezone);
}

$assetVersion = getenv('ASSET_VERSION');
if (is_string($assetVersion) && trim($assetVersion) !== '') {
    $config['asset_version'] = trim($assetVersion);
}

return $config;
