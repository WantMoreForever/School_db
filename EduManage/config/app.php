<?php

/**
 * 应用基础运行配置
 *
 * 负责范围：
 * - 当前运行环境（local / test / production）
 * - 调试开关
 * - 全局时区与字符集
 * - Session 兜底目录
 * - 系统日志筛选与清理策略
 *
 * 修改影响：
 * - 会影响后台、教师端、学生端的基础运行行为
 * - 会影响日志清理、日志关键词识别、时区相关时间显示
 * - 会影响前端静态资源总版本号的默认值
 */

return [
    // 当前应用环境标识。
    // 用途：区分本地开发、测试或正式环境，便于后续按环境扩展逻辑。
    // 取值示例：local / test / production
    // 修改影响：如果后续代码按环境分支，这里会决定启用哪套行为。
    'env' => getenv('APP_ENV') ?: 'local',

    // 是否开启调试模式。
    // 用途：为后续更详细的错误输出、日志增强预留统一开关。
    // 取值范围：true / false，支持环境变量字符串自动转布尔值。
    // 修改影响：可能影响错误展示、日志详细程度以及开发辅助信息。
    'debug' => filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN),

    // 应用默认时区。
    // 用途：统一 PHP 日期函数、日志时间、页面时间显示的基准时区。
    // 常见取值：Asia/Shanghai、UTC
    // 修改影响：登录、日志、学期时间判断、页面时间展示都会受影响。
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Shanghai',

    // 应用默认字符集。
    // 用途：作为页面输出、文本处理的统一字符集说明。
    // 常见取值：UTF-8
    // 修改影响：可能影响页面输出编码、字符串处理和文档说明一致性。
    'charset' => 'UTF-8',

    // 全局静态资源版本号。
    // 用途：当某个资源没有单独版本配置时，可作为统一版本参考。
    // 取值示例：20260423a、release-2026-04-23
    // 修改影响：前端缓存失效策略、浏览器是否重新拉取静态资源。
    'asset_version' => getenv('ASSET_VERSION') ?: '20260423a',

    'session' => [
        // Session 保存目录兜底路径。
        // 用途：默认 session.save_path 不可写时，应用会退回到这个目录保存会话。
        // 取值示例：D:\Temp\php_sessions 或系统临时目录拼接 php_sessions
        // 修改影响：登录态保持、管理员/教师/学生会话读写。
        'fallback_dir' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'php_sessions',
    ],

    'logging' => [
        // 日志保留天数。
        // 用途：控制系统日志文件或清理逻辑保留多久，避免日志无限增长。
        // 取值范围：建议大于等于 1 的整数，示例 30。
        // 修改影响：磁盘占用、历史操作可追溯时长。
        'retention_days' => 30,

        // 关键操作关键词列表。
        // 用途：辅助日志系统识别“重要操作”，用于高亮、筛选或保留。
        // 取值示例：中文动词、英文动作关键字。
        // 修改影响：哪些日志会被识别为重要事件。
        'important_keywords' => [
            '成功', '失败', '创建', '删除', '更新', '分配', '开放', '选课', '退选', '登录', '登出',
            '提交', '通过', '拒绝', '导出', '导入', '上传', '审核', '批准', '取消', '锁定', '解锁',
            'SUCCESS', 'FAILED', 'CREATE', 'DELETE', 'UPDATE', 'ENROLL', 'DROP', 'LOGIN',
            'ASSIGN', 'OPEN', 'CLOSE', 'APPROVE', 'REJECT', 'EXPORT', 'IMPORT', 'UPLOAD',
        ],

        // 忽略日志关键词的正则。
        // 用途：过滤掉查询类、只读类操作，减少系统日志噪音。
        // 取值示例：/(SELECT|GET|FETCH)/i
        // 修改影响：日志筛选结果，误改可能导致有用日志被忽略。
        'ignore_regex' => '/(SELECT|GET|FETCH|COUNT|LIST|QUERY|READ|SHOW)/i',
    ],
];
