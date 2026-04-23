<?php

declare(strict_types=1);

/**
 * 项目路径与 URL 目录表
 *
 * 负责范围：
 * - 统一维护项目根目录下的页面、接口、静态资源、上传目录路径
 * - 提供“磁盘路径”和“浏览器 URL”两套读取方式
 * - 生成前端可消费的 window.APP_PATHS 配置载荷
 *
 * 修改影响：
 * - 会影响管理员、教师、学生、登录页的跳转、资源加载、上传文件访问
 * - 如果改错某个目录表键值，页面可能出现 404、include 失败或上传目录错误
 *
 * 建议：
 * - 路径结构调整优先改本文件，不要在业务页面里继续拼接相对路径
 * - 新增前端脚本、页面或 API 时，也优先把入口登记到这里
 */

require_once __DIR__ . '/app_config.php';

if (defined('APP_PATHS_LOADED')) {
    return;
}

define('APP_PATHS_LOADED', true);

/**
 * 返回项目根目录的绝对路径。
 *
 * 用途：
 * - 作为所有相对路径拼接的起点
 * 修改影响：
 * - 如果根目录判断错误，include、上传、文件存在性检查都会受影响。
 */
function app_root_path(): string
{
    static $root = null;
    if ($root === null) {
        $root = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
    }

    return $root;
}

/**
 * 拼接服务器磁盘路径。
 *
 * 适用场景：
 * - include / require
 * - file_exists / is_file
 * - 上传保存目录
 */
function app_join_path(string ...$segments): string
{
    $clean = [];
    foreach ($segments as $index => $segment) {
        if ($segment === '') {
            continue;
        }

        $segment = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $segment);
        $segment = $index === 0
            ? rtrim($segment, "\\/")
            : trim($segment, "\\/");

        if ($segment !== '') {
            $clean[] = $segment;
        }
    }

    return implode(DIRECTORY_SEPARATOR, $clean);
}

/**
 * 拼接浏览器 URL 路径。
 *
 * 适用场景：
 * - href、src、fetch、重定向
 */
function app_join_url_path(string ...$segments): string
{
    $clean = [];
    foreach ($segments as $index => $segment) {
        if ($segment === '') {
            continue;
        }

        $segment = str_replace('\\', '/', $segment);
        $segment = $index === 0
            ? rtrim($segment, '/')
            : trim($segment, '/');

        if ($segment !== '') {
            $clean[] = $segment;
        }
    }

    return implode('/', $clean);
}

/**
 * 基于项目根目录生成绝对磁盘路径。
 */
function app_path(string $relative = ''): string
{
    return $relative === ''
        ? app_root_path()
        : app_join_path(app_root_path(), $relative);
}

/**
 * 用来推断当前项目部署子目录的 URL 标记。
 *
 * 示例：
 * - 如果 SCRIPT_NAME 包含 /student/，则项目根 URL 会回推到该目录之前
 * 修改影响：
 * - 影响项目部署在子目录（如 /2/）时的资源与页面 URL 是否正确。
 */
function app_web_base_markers(): array
{
    static $markers = null;
    if ($markers === null) {
        // 用这些目录片段来反推当前项目部署在站点下的子目录。
        $markers = [
            '/admin/',
            '/student/',
            '/teacher/',
            '/login/',
            '/components/',
            '/config/',
            '/uploads/',
            '/css/',
        ];
    }

    return $markers;
}

/**
 * 计算当前请求对应的项目 Web Base。
 *
 * 示例：
 * - 项目部署在 http://localhost/2/ 时，这里会得到 /2
 * - 项目部署在站点根目录时，这里会得到空字符串
 */
function app_web_base(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }

    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($scriptName === '') {
        $base = '';
        return $base;
    }

    foreach (app_web_base_markers() as $marker) {
        $pos = strpos($scriptName, $marker);
        if ($pos !== false) {
            $base = rtrim(substr($scriptName, 0, $pos), '/');
            return $base;
        }
    }

    $dir = str_replace('\\', '/', dirname($scriptName));
    $base = ($dir === '/' || $dir === '.') ? '' : rtrim($dir, '/');
    return $base;
}

/**
 * 基于 Web Base 拼出浏览器可访问 URL。
 */
function app_url(string $relative = ''): string
{
    $base = app_web_base();
    $relative = ltrim($relative, '/');

    if ($relative === '') {
        return $base !== '' ? $base . '/' : '/';
    }

    return ($base !== '' ? $base : '') . '/' . $relative;
}

/**
 * 项目相对路径目录表。
 *
 * 读取原则：
 * - 这里保存的是“相对项目根目录”的路径，不是绝对路径
 * - 业务层应尽量通过 app_catalog_*() 读取，而不是手写 ../ 或 /api/xxx.php
 */
function app_relative_catalog(): array
{
    static $catalog = null;
    if ($catalog !== null) {
        return $catalog;
    }

    // 这里维护的是“相对项目根目录”的统一路径目录表。
    // 后面如果要调整页面、接口、资源或上传目录，优先改这里。
    $catalog = [
        // 通用页面入口。
        'pages' => [
            'login' => 'login/login.php',
            'logout' => 'login/logout.php',
        ],
        // 前端静态资源。
        'assets' => [
            // 输出给前端的统一配置脚本入口。
            'frontend_paths_script' => 'config/frontend-paths.php',
            // 公共门户样式，学生端和教师端共享。
            'portal_common_css' => 'css/portal-common.css',
            // 管理后台样式与脚本。
            'admin_css' => 'admin/css/admin.css',
            'admin_js' => 'admin/js/admin.js',
            'admin_app_api_js' => 'admin/js/app_api.js',
            // 学生端样式与页面脚本。
            'student_css' => 'student/css/student_style.css',
            'student_clock_js' => 'student/js/clock.js',
            'student_app_paths_js' => 'student/js/app_paths.js',
            'student_config_js' => 'student/js/student_config.js',
            'student_app_api_js' => 'student/js/app_api.js',
            'student_sidebar_js' => 'student/js/student_sidebar.js',
            'student_profile_js' => 'student/js/profile.js',
            'student_schedule_js' => 'student/js/schedule.js',
            'student_free_classroom_js' => 'student/js/free_classroom.js',
            'student_exam_info_js' => 'student/js/exam_info.js',
            'student_my_grades_js' => 'student/js/my_grades.js',
            'student_course_select_js' => 'student/js/course_select.js',
            'student_portal_js' => 'student/js/student_portal.js',
            'student_change_pwd_js' => 'student/js/change_pwd.js',
            'student_announcement_js' => 'student/js/student_announcement.js',
            // 教师端样式与公共脚本。
            'teacher_css' => 'teacher/style.css',
            'teacher_app_api_js' => 'teacher/app_api.js',
            // 登录页样式。
            'login_css' => 'login/style.css',
        ],
        // 上传目录与默认图片。
        'uploads' => [
            // 上传资源根目录。
            'root' => 'uploads/',
            // 用户头像目录。
            'avatars' => 'uploads/avatars/',
            // 登录页轮播图 / 背景图目录。
            'login_images' => 'uploads/login/',
            // 默认头像文件。
            'default_avatar' => 'uploads/RC.png',
        ],
        // 学生端页面与接口。
        'student' => [
            'pages' => [
                // 学生门户单页应用入口。
                'spa' => 'student/spa.html',
            ],
            'api' => [
                // 学生端各功能接口入口。
                'announcement' => 'student/api/announcement.php',
                'change_pwd' => 'student/api/change_pwd.php',
                'config' => 'student/api/config.php',
                'course_select' => 'student/api/course_select.php',
                'exam_info' => 'student/api/exam_info.php',
                'free_classroom' => 'student/api/free_classroom.php',
                'my_grades' => 'student/api/my_grades.php',
                'profile' => 'student/api/profile.php',
                'schedule' => 'student/api/schedule.php',
                'sidebar' => 'student/api/sidebar.php',
                'student_portal' => 'student/api/student_portal.php',
            ],
        ],
        // 教师端页面与接口。
        'teacher' => [
            'pages' => [
                // 教师门户入口，实际由 index.php 包装 index.html。
                'index' => 'teacher/index.php',
            ],
            'api' => [
                // 教师端各功能接口入口。
                'teacher' => 'teacher/api/teacher.php',
                'grades' => 'teacher/api/grades.php',
                'announcement' => 'teacher/api/announcement.php',
                'attendance' => 'teacher/api/attendance.php',
                'workload' => 'teacher/api/workload.php',
                'schedule' => 'teacher/api/schedule.php',
                'application' => 'teacher/api/application.php',
            ],
        ],
        // 管理端页面与接口。
        'admin' => [
            'api' => [
                // 管理后台主 API 入口与导入接口。
                'main' => 'admin/api/index.php',
                'import_students' => 'admin/api/import_students.php',
                'import_teachers' => 'admin/api/import_teachers.php',
            ],
            // 管理端共享 partial 的服务端 include 路径。
            // 后续如果要调整 partial 目录结构，优先只改这里。
            'partials' => [
                'reset_password_modals' => 'admin/partials/reset_password_modals.php',
                'dashboard_stats' => 'admin/partials/dashboard_stats.php',
                'personnel_import_modal' => 'admin/partials/personnel_import_modal.php',
                'personnel_crud' => 'admin/partials/personnel_crud.php',
                'personnel_form_fields' => 'admin/partials/personnel_form_fields.php',
                'resource_crud' => 'admin/partials/resource_crud.php',
                'resource_form_fields' => 'admin/partials/resource_form_fields.php',
            ],
            'pages' => [
                // 管理后台页面入口。
                'index' => 'admin/index.php',
                'student' => 'admin/student.php',
                'teacher' => 'admin/teacher.php',
                'course' => 'admin/course.php',
                'schedule_manage' => 'admin/schedule_manage.php',
                'classroom' => 'admin/classroom.php',
                'announcement' => 'admin/announcement.php',
                'department' => 'admin/department.php',
                'major' => 'admin/major.php',
                'syslog' => 'admin/syslog.php',
                'admin_manage' => 'admin/admin_manage.php',
                'profile' => 'admin/profile.php',
            ],
        ],
    ];

    return $catalog;
}

/**
 * 读取目录表中的任意节点。
 *
 * 示例：
 * - app_catalog_node('student', 'api') 返回整个学生端 API 子数组
 * - app_catalog_node('assets', 'student_css') 返回具体路径字符串
 */
function app_catalog_node(string ...$keys)
{
    $value = app_relative_catalog();
    foreach ($keys as $key) {
        if (!is_array($value) || !array_key_exists($key, $value)) {
            throw new InvalidArgumentException('Unknown app path key: ' . implode('.', $keys));
        }

        $value = $value[$key];
    }

    return $value;
}

/**
 * 从目录表读取相对路径字符串。
 */
function app_catalog_relative(string ...$keys): string
{
    $value = app_catalog_node(...$keys);
    if (!is_string($value)) {
        throw new InvalidArgumentException('App path key must resolve to a string: ' . implode('.', $keys));
    }

    return $value;
}

/**
 * 从目录表读取服务器磁盘绝对路径。
 *
 * 适用：
 * - include 模板
 * - 访问上传目录
 * - 检查文件是否存在
 */
function app_catalog_path(string ...$keys): string
{
    // 取服务器磁盘绝对路径，适合 file_exists、include、上传保存。
    return app_path(app_catalog_relative(...$keys));
}

/**
 * 从目录表读取浏览器可访问 URL。
 *
 * 适用：
 * - 页面跳转
 * - 前端脚本 src / 样式 href
 * - 接口 fetch 地址
 */
function app_catalog_url(string ...$keys): string
{
    // 取浏览器访问 URL，适合 href、src、fetch、跳转。
    return app_url(app_catalog_relative(...$keys));
}

/**
 * 递归把目录表中的相对路径全部转成 URL。
 *
 * 用途：
 * - 生成前端一次性消费的资源 / API 地址表
 */
function app_urls_from_catalog(array $node): array
{
    $urls = [];
    foreach ($node as $key => $value) {
        $urls[$key] = is_array($value)
            ? app_urls_from_catalog($value)
            : app_url($value);
    }

    return $urls;
}

/**
 * 读取某个目录表节点下的所有 URL。
 */
function app_catalog_urls(string ...$keys): array
{
    $value = app_catalog_node(...$keys);
    if (!is_array($value)) {
        throw new InvalidArgumentException('App path key must resolve to an array: ' . implode('.', $keys));
    }

    return app_urls_from_catalog($value);
}

/**
 * 登录页 URL。
 *
 * 参数示例：
 * - app_login_url('err=not_teacher')
 */
function app_login_url(string $query = ''): string
{
    $url = app_catalog_url('pages', 'login');
    return $query !== '' ? $url . (str_starts_with($query, '?') ? $query : '?' . $query) : $url;
}

// 以下函数是常用路径快捷入口，目的是减少业务层重复写长 key。

function app_logout_url(): string
{
    return app_catalog_url('pages', 'logout');
}

function app_admin_css_url(): string
{
    return app_catalog_url('assets', 'admin_css');
}

function app_admin_js_url(): string
{
    return app_catalog_url('assets', 'admin_js');
}

function app_teacher_css_url(): string
{
    return app_catalog_url('assets', 'teacher_css');
}

function app_login_css_url(): string
{
    return app_catalog_url('assets', 'login_css');
}

function app_frontend_paths_script_url(): string
{
    return app_catalog_url('assets', 'frontend_paths_script');
}

/**
 * 管理端 partial 模板绝对路径读取入口。
 *
 * 修改影响：
 * - 管理后台页面 include 公共片段时都会经过这里。
 */
function app_admin_partial_path(string $partial): string
{
    // 管理端公共 partial 的统一 include 入口。
    // 页面层尽量走这里，避免散落 __DIR__ 相对路径。
    return app_catalog_path('admin', 'partials', $partial);
}

/**
 * 上传目录绝对路径。
 *
 * 示例：
 * - app_uploads_path() => uploads 根目录
 * - app_uploads_path('avatars/a.jpg') => 某个上传文件的绝对路径
 */
function app_uploads_path(string $relative = ''): string
{
    if ($relative === '') {
        return app_catalog_path('uploads', 'root');
    }

    return app_path(app_join_path(app_catalog_relative('uploads', 'root'), $relative));
}

/**
 * 上传资源 URL。
 */
function app_uploads_url(string $relative = ''): string
{
    if ($relative === '') {
        return app_catalog_url('uploads', 'root');
    }

    return app_url(app_join_url_path(app_catalog_relative('uploads', 'root'), $relative));
}

// 以下头像 / 登录图快捷函数，统一收口上传资源访问方式。

function app_avatar_dir(): string
{
    return app_catalog_path('uploads', 'avatars');
}

function app_avatar_url(?string $filename = null): string
{
    if ($filename === null || $filename === '') {
        return app_catalog_url('uploads', 'avatars');
    }

    return app_uploads_url(app_join_url_path('avatars', basename($filename)));
}

function app_default_avatar_url(): string
{
    return app_catalog_url('uploads', 'default_avatar');
}

function app_login_images_dir(): string
{
    return app_catalog_path('uploads', 'login_images');
}

function app_login_image_url(string $filename): string
{
    return app_uploads_url(app_join_url_path('login', basename($filename)));
}

function app_frontend_paths(): array
{
    // 暴露给前端 JS 的统一路径配置，最终会挂到 window.APP_PATHS。
    return [
        // 当前项目根 URL，例如 /2/ 或 /
        'base_url' => app_url(),
        // 登录 / 退出地址。
        'login_url' => app_login_url(),
        'logout_url' => app_logout_url(),
        // 前端可直接加载的静态资源 URL。
        'assets' => array_merge(
            app_catalog_urls('assets'),
            [
                // 默认头像与头像目录前缀，供学生端 / 教师端前端使用。
                'default_avatar' => app_default_avatar_url(),
                'avatar_base_url' => app_avatar_url(),
            ]
        ),
        // 静态资源版本号表，来自 config/frontend.php。
        'versions' => app_config('frontend.asset_versions', []),
        // 外部 CDN 资源地址，来自 config/frontend.php。
        'cdn' => app_config('frontend.cdn', []),
        // 前端展示需要的枚举标签。
        'enums' => [
            'gender' => app_config('enums.gender', []),
            'semester' => app_config('enums.semester', []),
            'exam_type' => app_config('enums.exam_type', []),
            'classroom_type' => app_config('enums.classroom_type', []),
            'attendance_status' => app_config('enums.attendance_status', []),
            'announcement_targets' => app_config('enums.announcement_targets', []),
        ],
        // 前端上传限制。
        'upload' => [
            'avatar' => [
                'max_size' => app_config('upload.avatar.max_size', 0),
                'allowed_mimes' => array_keys(app_config('upload.avatar.allowed_mimes', [])),
            ],
        ],
        // 学生端入口与 API 地址。
        'student' => [
            'spa_url' => app_catalog_url('student', 'pages', 'spa'),
            'api' => app_catalog_urls('student', 'api'),
        ],
        // 教师端入口与 API 地址。
        'teacher' => [
            'index_url' => app_catalog_url('teacher', 'pages', 'index'),
            'api' => app_catalog_urls('teacher', 'api'),
        ],
        // 管理端入口地址。
        'admin' => [
            'index_url' => app_catalog_url('admin', 'pages', 'index'),
        ],
    ];
}
