<?php

/**
 * 前端资源版本与 CDN 配置
 *
 * 使用原则：
 * - 本文件中的固定值是前端资源版本和 CDN 的主配置来源
 * - 环境变量仅作为可选覆盖，不配置环境变量时项目也应可直接运行
 * - 本地开发优先直接修改本文件，不要求配置系统环境变量、Web 服务器环境变量或 .env
 */

$config = [
    'asset_versions' => [
        // 公共门户样式版本号。
        'portal_common_css' => 'v=20260423a',
        // 教师门户样式版本号。
        'teacher_css' => 'v=20260423a',
        // 教师门户共享 JS helper 版本号。
        'teacher_app_api_js' => 'v=20260423a',
        // 学生门户主样式版本号。
        'student_css' => 'v=20260423a',
        // 学生门户时钟脚本版本号。
        'student_clock_js' => 'v=20260419c',
        // 学生门户路径配置 helper 版本号。
        'student_app_paths_js' => 'v=20260422b',
        // 学生门户运行时配置脚本版本号。
        'student_config_js' => 'v=20260422b',
        // 学生门户接口封装脚本版本号。
        'student_app_api_js' => 'v=20260423a',
        // 侧边栏脚本版本号。
        'student_sidebar_js' => 'v=20260423b',
        // 个人资料脚本版本号。
        'student_profile_js' => 'v=20260422c',
        // 课表页脚本版本号。
        'student_schedule_js' => 'v=20260422b',
        // 空闲教室脚本版本号。
        'student_free_classroom_js' => 'v=20260422b',
        // 考试信息脚本版本号。
        'student_exam_info_js' => 'v=20260422b',
        // 成绩页脚本版本号。
        'student_my_grades_js' => 'v=20260422b',
        // 选课页脚本版本号。
        'student_course_select_js' => 'v=20260422b',
        // 学生首页脚本版本号。
        'student_portal_js' => 'v=20260422b',
        // 改密脚本版本号。
        'student_change_pwd_js' => 'v=20260422b',
        // 公告页脚本版本号。
        'student_announcement_js' => 'v=20260422c',
    ],

    'cdn' => [
        // Bootstrap CSS CDN。
        'bootstrap_css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        // Bootstrap JS CDN。
        'bootstrap_js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        // XLSX 解析库 CDN。
        'xlsx_js' => 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js',
        // Google Fonts 样式地址。
        'fonts_css' => 'https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap',
        // 预连接字体服务域名。
        'fonts_preconnect' => 'https://fonts.googleapis.com',
    ],
];

$assetEnvMap = [
    'portal_common_css' => 'ASSET_VERSION_PORTAL_COMMON',
    'teacher_css' => 'ASSET_VERSION_TEACHER_CSS',
    'teacher_app_api_js' => 'ASSET_VERSION_TEACHER_APP_API',
    'student_css' => 'ASSET_VERSION_STUDENT_CSS',
    'student_clock_js' => 'ASSET_VERSION_STUDENT_CLOCK',
    'student_app_paths_js' => 'ASSET_VERSION_STUDENT_APP_PATHS',
    'student_config_js' => 'ASSET_VERSION_STUDENT_CONFIG',
    'student_app_api_js' => 'ASSET_VERSION_STUDENT_APP_API',
    'student_sidebar_js' => 'ASSET_VERSION_STUDENT_SIDEBAR',
    'student_profile_js' => 'ASSET_VERSION_STUDENT_PROFILE',
    'student_schedule_js' => 'ASSET_VERSION_STUDENT_SCHEDULE',
    'student_free_classroom_js' => 'ASSET_VERSION_STUDENT_FREE_CLASSROOM',
    'student_exam_info_js' => 'ASSET_VERSION_STUDENT_EXAM_INFO',
    'student_my_grades_js' => 'ASSET_VERSION_STUDENT_MY_GRADES',
    'student_course_select_js' => 'ASSET_VERSION_STUDENT_COURSE_SELECT',
    'student_portal_js' => 'ASSET_VERSION_STUDENT_PORTAL',
    'student_change_pwd_js' => 'ASSET_VERSION_STUDENT_CHANGE_PWD',
    'student_announcement_js' => 'ASSET_VERSION_STUDENT_ANNOUNCEMENT',
];

foreach ($assetEnvMap as $key => $envName) {
    $value = getenv($envName);
    if (is_string($value) && trim($value) !== '') {
        $config['asset_versions'][$key] = trim($value);
    }
}

return $config;
