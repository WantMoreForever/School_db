<?php
/**
 * admin/common.php
 * 管理后台公共引导：加载基础组件、启动会话、提供管理员鉴权与常用辅助函数。
 */
require_once __DIR__ . '/../components/bootstrap.php';
define('IN_ADMIN', true);
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/lib/queries.php';

app_start_session();
header('Content-Type: text/html; charset=utf-8');
date_default_timezone_set('Asia/Shanghai');

function admin_auth(): void
{
    $pdo = app_require_pdo();

    if (empty($_SESSION['user_id'])) {
        header('Location: ' . app_login_url());
        exit;
    }

    $stmt = $pdo->prepare('SELECT 1 FROM admin WHERE user_id = ? LIMIT 1');
    $stmt->execute([(int) $_SESSION['user_id']]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        exit('无管理员权限');
    }
}

/**
 * 判断当前管理员是否为超管（super_admin）
 * 返回 true 表示是 super_admin，否则 false
 */
function admin_is_super_admin(): bool
{
    if (empty($_SESSION['user_id'])) return false;

    try {
        $pdo = app_require_pdo();
        $stmt = $pdo->prepare('SELECT role FROM admin WHERE user_id = ? LIMIT 1');
        $stmt->execute([(int) $_SESSION['user_id']]);
        $role = $stmt->fetchColumn();
        return is_string($role) && $role === 'super_admin';
    } catch (Throwable $e) {
        // 如果 admin 表没有 role 字段或查询失败，认为不是超管
        return false;
    }
}

function h($str): string
{
    return htmlspecialchars((string) ($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function admin_csrf_input(): string
{
    return app_csrf_input();
}

function admin_page_registry(): array
{
    static $pages = null;
    if ($pages !== null) {
        return $pages;
    }

    $pages = [
        'index' => [
            'script' => 'index.php',
            'label' => '首页',
            'nav' => false,
        ],
        'student' => [
            'script' => 'student.php',
            'label' => '学生管理',
            'nav' => true,
        ],
        'teacher' => [
            'script' => 'teacher.php',
            'label' => '教师管理',
            'nav' => true,
        ],
        'course' => [
            'script' => 'course.php',
            'label' => '课程管理',
            'nav' => true,
        ],
        'schedule_manage' => [
            'script' => 'schedule_manage.php',
            'label' => '排课管理',
            'nav' => true,
        ],
        'classroom' => [
            'script' => 'classroom.php',
            'label' => '教室管理',
            'nav' => true,
        ],
        'announcement' => [
            'script' => 'announcement.php',
            'label' => '公告管理',
            'nav' => true,
        ],
        'department' => [
            'script' => 'department.php',
            'label' => '院系管理',
            'nav' => true,
        ],
        'major' => [
            'script' => 'major.php',
            'label' => '专业管理',
            'nav' => true,
        ],
        'syslog' => [
            'script' => 'syslog.php',
            'label' => '系统日志',
            'nav' => true,
        ],
        'admin_manage' => [
            'script' => 'admin_manage.php',
            'label' => '管理员管理',
            'nav' => true,
            'super_admin_only' => true,
        ],
        'profile' => [
            'script' => 'profile.php',
            'label' => '个人资料',
            'nav' => false,
        ],
    ];

    return $pages;
}

function admin_current_page_key(?string $scriptName = null): string
{
    $script = basename((string) ($scriptName ?? ($_SERVER['SCRIPT_NAME'] ?? '')));
    foreach (admin_page_registry() as $key => $meta) {
        if (($meta['script'] ?? '') === $script) {
            return $key;
        }
    }

    return '';
}

function admin_nav_pages(bool $includeSuperAdmin = false): array
{
    $pages = [];
    foreach (admin_page_registry() as $key => $meta) {
        if (empty($meta['nav'])) {
            continue;
        }
        if (!empty($meta['super_admin_only']) && !$includeSuperAdmin) {
            continue;
        }

        $pages[$key] = $meta;
    }

    return $pages;
}

function admin_page_url(string $pageKey): string
{
    return app_catalog_url('admin', 'pages', $pageKey);
}

function admin_page_alert(): ?array
{
    $error = trim((string) ($_GET['error'] ?? ''));
    if ($error !== '') {
        return [
            'type' => 'danger',
            'message' => $error,
        ];
    }

    $success = trim((string) ($_GET['success'] ?? ''));
    if ($success !== '') {
        return [
            'type' => 'success',
            'message' => $success === '1' ? '操作成功' : $success,
        ];
    }

    return null;
}

function admin_build_query(array $overrides, ?array $source = null): string
{
    $params = $source ?? $_GET;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }

        $params[$key] = (string) $value;
    }

    $query = http_build_query($params);
    return $query !== '' ? ('?' . $query) : '';
}

function admin_paginate(int $total, int $page, int $perPage, int $radius = 3): array
{
    $totalPages = (int) max(1, ceil($total / max($perPage, 1)));
    $page = max(1, min($page, $totalPages));

    return [
        'page' => $page,
        'total_pages' => $totalPages,
        'start' => max(1, $page - $radius),
        'end' => min($totalPages, $page + $radius),
    ];
}
