<?php

/**
 * 前端资源版本与 CDN 配置
 *
 * 负责范围：
 * - 学生端、教师端、后台使用到的静态资源版本号
 * - 前端依赖的 CDN 地址
 *
 * 修改影响：
 * - 会影响浏览器缓存刷新
 * - 会影响外部资源是否能成功加载
 * - 如果版本号不匹配，可能出现“页面加载旧 JS / CSS”的问题
 */

return [
    'asset_versions' => [
        // 公共门户样式版本号。
        // 修改场景：改了 css/portal-common.css 后建议同步更新。
        'portal_common_css' => getenv('ASSET_VERSION_PORTAL_COMMON') ?: 'v=20260423a',
        // 教师门户样式版本号。
        'teacher_css' => getenv('ASSET_VERSION_TEACHER_CSS') ?: 'v=20260423a',
        // 教师门户共享 JS helper 版本号。
        'teacher_app_api_js' => getenv('ASSET_VERSION_TEACHER_APP_API') ?: 'v=20260423a',
        // 学生门户主样式版本号。
        'student_css' => getenv('ASSET_VERSION_STUDENT_CSS') ?: 'v=20260423a',
        // 学生门户时钟脚本版本号。
        'student_clock_js' => getenv('ASSET_VERSION_STUDENT_CLOCK') ?: 'v=20260419c',
        // 学生门户路径配置 helper 版本号。
        'student_app_paths_js' => getenv('ASSET_VERSION_STUDENT_APP_PATHS') ?: 'v=20260422b',
        // 学生门户运行时配置脚本版本号。
        'student_config_js' => getenv('ASSET_VERSION_STUDENT_CONFIG') ?: 'v=20260422b',
        // 学生门户接口封装脚本版本号。
        'student_app_api_js' => getenv('ASSET_VERSION_STUDENT_APP_API') ?: 'v=20260423a',
        // 侧边栏脚本版本号。
        'student_sidebar_js' => getenv('ASSET_VERSION_STUDENT_SIDEBAR') ?: 'v=20260423b',
        // 个人资料脚本版本号。
        'student_profile_js' => getenv('ASSET_VERSION_STUDENT_PROFILE') ?: 'v=20260422c',
        // 课表页脚本版本号。
        'student_schedule_js' => getenv('ASSET_VERSION_STUDENT_SCHEDULE') ?: 'v=20260422b',
        // 空闲教室脚本版本号。
        'student_free_classroom_js' => getenv('ASSET_VERSION_STUDENT_FREE_CLASSROOM') ?: 'v=20260422b',
        // 考试信息脚本版本号。
        'student_exam_info_js' => getenv('ASSET_VERSION_STUDENT_EXAM_INFO') ?: 'v=20260422b',
        // 成绩页脚本版本号。
        'student_my_grades_js' => getenv('ASSET_VERSION_STUDENT_MY_GRADES') ?: 'v=20260422b',
        // 选课页脚本版本号。
        'student_course_select_js' => getenv('ASSET_VERSION_STUDENT_COURSE_SELECT') ?: 'v=20260422b',
        // 学生首页脚本版本号。
        'student_portal_js' => getenv('ASSET_VERSION_STUDENT_PORTAL') ?: 'v=20260422b',
        // 改密脚本版本号。
        'student_change_pwd_js' => getenv('ASSET_VERSION_STUDENT_CHANGE_PWD') ?: 'v=20260422b',
        // 公告页脚本版本号。
        'student_announcement_js' => getenv('ASSET_VERSION_STUDENT_ANNOUNCEMENT') ?: 'v=20260422c',
    ],

    'cdn' => [
        // Bootstrap CSS CDN。
        // 修改影响：后台页面和部分公共组件样式是否能正常加载。
        'bootstrap_css' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css',
        // Bootstrap JS CDN。
        // 修改影响：模态框、下拉框等依赖 Bootstrap JS 的交互。
        'bootstrap_js' => 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js',
        // XLSX 解析库 CDN。
        // 修改影响：管理员批量导入、前端 Excel/CSV 读取功能。
        'xlsx_js' => 'https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js',
        // Google Fonts 样式地址。
        // 修改影响：学生端、教师端字体显示。
        'fonts_css' => 'https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap',
        // 预连接字体服务域名。
        // 修改影响：字体资源加载速度。
        'fonts_preconnect' => 'https://fonts.googleapis.com',
    ],
];
