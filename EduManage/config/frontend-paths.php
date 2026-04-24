<?php

declare(strict_types=1);

/**
 * 前端配置输出入口
 *
 * 负责范围：
 * - 把后端整理好的路径、版本号、枚举、上传限制输出给浏览器
 * - 生成全局变量 window.APP_PATHS
 *
 * 修改影响：
 * - 学生端 SPA、教师端页面、部分后台脚本都会依赖这里的输出
 * - 如果输出结构变化，前端 helper 需要同步修改
 */

require_once __DIR__ . '/paths.php';

// JavaScript 响应头。
// 用途：让浏览器把本文件当作脚本执行，而不是纯文本下载。
$contentType = (string) app_config('api.javascript_content_type', '');
if ($contentType !== '') {
    header('Content-Type: ' . $contentType);
}

// 前端配置脚本默认禁止缓存，避免浏览器长期持有旧路径和旧版本号。
$headers = app_config('api.no_cache_headers', []);
if (is_array($headers)) {
    foreach ($headers as $name => $value) {
        header((string) $name . ': ' . (string) $value);
    }
}

// 输出统一前端配置对象。
// 结构说明：
// - assets：静态资源 URL
// - versions：静态资源版本号
// - enums：前端展示需要的枚举标签
// - upload：上传限制
// - student / teacher / admin：各端入口与 API 路径
$payload = app_frontend_paths();
echo 'window.APP_PATHS = Object.assign({}, window.APP_PATHS || {}, ' .
    json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) .
    ');';
