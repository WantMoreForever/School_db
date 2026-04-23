<?php

/**
 * API 与响应输出配置
 *
 * 负责范围：
 * - JSON / HTML / JavaScript 输出 Content-Type
 * - 无缓存响应头
 * - CORS 允许策略
 * - HTTP 状态码到业务错误码的映射
 *
 * 修改影响：
 * - 会影响管理员、教师、学生三端接口响应格式
 * - 会影响浏览器缓存、跨域访问和统一错误码返回
 */

return [
    // JSON 接口默认响应类型。
    // 用途：确保前后端都以 UTF-8 JSON 方式解析接口结果。
    // 修改影响：所有调用 app_send_json_header() 的接口。
    'json_content_type' => 'application/json; charset=utf-8',

    // 前端配置脚本等 JavaScript 输出类型。
    // 用途：例如 config/frontend-paths.php 会输出一段 JS 给浏览器执行。
    // 修改影响：浏览器是否按脚本而不是纯文本处理该响应。
    'javascript_content_type' => 'application/javascript; charset=utf-8',

    // 服务端渲染页面默认输出类型。
    // 用途：后台页面或入口页在输出 HTML 时保持统一编码声明。
    // 修改影响：页面编码、浏览器渲染方式。
    'html_content_type' => 'text/html; charset=utf-8',

    'no_cache_headers' => [
        // 禁止缓存主头。
        // 用途：确保管理后台、配置脚本、敏感接口不会被浏览器缓存。
        // 修改影响：页面刷新是否取最新内容、浏览器缓存命中率。
        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        // 兼容旧代理和旧浏览器的禁用缓存头。
        // 修改影响：兼容性层面的缓存控制行为。
        'Pragma' => 'no-cache',
    ],

    'cors' => [
        // 允许的来源域名。
        // 用途：控制哪些站点可以跨域调用接口。
        // 当前值 * 表示任意来源；正式环境如果要收紧，应改成具体域名。
        // 修改影响：前端跨域请求是否会被浏览器拦截。
        'allow_origin' => '*',
        // 允许的 HTTP 方法。
        // 修改影响：预检请求是否通过，前端可否使用指定方法访问接口。
        'allow_methods' => 'GET, POST, OPTIONS',
        // 允许携带的请求头。
        // 修改影响：AJAX 是否能发送 JSON、CSRF 头等信息。
        'allow_headers' => 'Content-Type',
    ],

    'error_codes_by_status' => [
        // 401：未登录或登录失效。
        401 => 'ERR_UNAUTHENTICATED',
        // 403：已登录但无权限。
        403 => 'ERR_FORBIDDEN',
        // 404：资源不存在。
        404 => 'ERR_NOT_FOUND',
        // 409：冲突，例如重复操作、数据状态不允许。
        409 => 'ERR_CONFLICT',
        // 419：CSRF 校验失败。
        419 => 'ERR_CSRF',
        // 5xx：统一按服务端异常处理。
        500 => 'ERR_SERVER',
        501 => 'ERR_SERVER',
        502 => 'ERR_SERVER',
        503 => 'ERR_SERVER',
        504 => 'ERR_SERVER',
    ],

    // 通用成功业务码。
    // 用途：接口成功时的统一 code 字段。
    // 修改影响：前端或测试脚本若依赖固定 code，需要同步调整。
    'default_success_code' => 'OK',

    // 通用失败业务码兜底值。
    // 用途：找不到更精确映射时，返回该错误码。
    // 修改影响：前端错误提示归类、测试断言。
    'default_error_code' => 'ERR_VALIDATION',
];
