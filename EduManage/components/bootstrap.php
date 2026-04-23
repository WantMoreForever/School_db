<?php
// components/bootstrap.php

declare(strict_types=1);

require_once __DIR__ . '/../config/app_config.php';
require_once __DIR__ . '/../config/paths.php';

function app_send_json_header(): void
{
    $contentType = (string) app_config('api.json_content_type', '');
    if ($contentType !== '') {
        header('Content-Type: ' . $contentType);
    }
}

function app_send_no_cache_headers(): void
{
    $headers = app_config('api.no_cache_headers', []);
    if (!is_array($headers)) {
        return;
    }

    foreach ($headers as $name => $value) {
        header((string) $name . ': ' . (string) $value);
    }
}

function app_send_cors_headers(): void
{
    $cors = app_config('api.cors', []);
    if (!is_array($cors)) {
        return;
    }

    $headerMap = [
        'allow_origin' => 'Access-Control-Allow-Origin',
        'allow_methods' => 'Access-Control-Allow-Methods',
        'allow_headers' => 'Access-Control-Allow-Headers',
    ];

    foreach ($headerMap as $key => $headerName) {
        $value = (string) ($cors[$key] ?? '');
        if ($value !== '') {
            header($headerName . ': ' . $value);
        }
    }
}

function app_apply_api_headers(bool $withCors = false, bool $noCache = false): void
{
    app_send_json_header();
    if ($withCors) {
        app_send_cors_headers();
    }
    if ($noCache) {
        app_send_no_cache_headers();
    }
}

function app_exit_on_options_request(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        exit;
    }
}

function app_api_error_code_for_status(int $status): string
{
    $map = app_config('api.error_codes_by_status', []);
    if (is_array($map) && isset($map[$status])) {
        return (string) $map[$status];
    }

    if ($status >= 500 && is_array($map) && isset($map[500])) {
        return (string) $map[500];
    }

    return (string) app_config('api.default_error_code', 'ERROR');
}

function app_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $ok = @session_start();
    if ($ok) {
        return;
    }

    $fallback = (string) app_config('app.session.fallback_dir', '');
    if ($fallback === '') {
        return;
    }

    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }

    @session_save_path($fallback);
    @session_start();
}

function app_ensure_csrf_token(): string
{
    app_start_session();
    if (empty($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }

    return (string) $_SESSION['csrf_token'];
}

function app_csrf_input(): string
{
    $token = htmlspecialchars(app_ensure_csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function app_validate_csrf(?string $token): bool
{
    app_start_session();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    return is_string($token) && $token !== '' && is_string($sessionToken) && hash_equals($sessionToken, $token);
}

function app_abort_csrf(bool $isAjax = false, string $redirect = 'index.php'): never
{
    $message = 'CSRF 验证失败，请刷新页面后重试。';
    if ($isAjax) {
        http_response_code(419);
        app_send_json_header();
        echo json_encode([
            'ok' => false,
            'success' => false,
            'code' => app_api_error_code_for_status(419),
            'error' => $message,
            'message' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $sep = strpos($redirect, '?') !== false ? '&' : '?';
    header('Location: ' . $redirect . $sep . 'error=' . urlencode($message));
    exit;
}

function app_password_verify_compat(string $input, $stored): bool
{
    if (!is_string($stored) || $stored === '') {
        return false;
    }

    if (preg_match('/^\$2[aby]\$/', $stored) === 1) {
        return password_verify($input, $stored);
    }

    if (preg_match('/^[0-9a-f]{64}$/i', $stored) === 1) {
        return hash('sha256', $input) === strtolower($stored);
    }

    return hash_equals($stored, $input);
}

function app_password_hash(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

function app_find_user_role(PDO $pdo, int $userId): ?string
{
    $roles = app_config('auth.role_tables', []);
    if (!is_array($roles)) {
        return null;
    }

    foreach ($roles as $role => $table) {
        if (!is_string($role) || !is_string($table) || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            continue;
        }
        $stmt = $pdo->prepare("SELECT 1 FROM {$table} WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        if ($stmt->fetchColumn()) {
            return $role;
        }
    }

    return null;
}

function app_redirect_by_role(string $role): never
{
    $map = app_config('auth.role_home', []);
    $target = is_array($map) ? ($map[$role] ?? null) : null;
    $url = app_login_url();

    if (is_array($target) && $target !== []) {
        $url = app_catalog_url(...array_map('strval', $target));
    } elseif (is_string($target) && $target !== '') {
        $url = app_url($target);
    }

    $fragment = (string) app_config('auth.role_home_fragments.' . $role, '');
    if ($fragment !== '' && strpos($url, '#') === false) {
        $url .= $fragment;
    }

    header('Location: ' . $url);
    exit;
}

function app_enum_map(string $key, array $default = []): array
{
    $value = app_config('enums.' . $key, $default);
    return is_array($value) ? $value : $default;
}

function app_enum_keys(string $key, array $default = []): array
{
    $map = app_enum_map($key);
    if ($map === []) {
        return $default;
    }

    return array_map('strval', array_keys($map));
}

function app_first_enum_key(string $key, string $default = ''): string
{
    $keys = app_enum_keys($key);
    if ($keys === []) {
        return $default;
    }

    return (string) $keys[0];
}

function app_enum_label(string $key, string $value, string $default = ''): string
{
    $map = app_enum_map($key);
    if (isset($map[$value])) {
        return (string) $map[$value];
    }

    return $default;
}

function app_default_current_semester(): string
{
    $keys = app_enum_keys('semester');
    if ($keys !== []) {
        return date('n') >= 9
            ? (string) end($keys)
            : (string) reset($keys);
    }

    return date('n') >= 9 ? 'Fall' : 'Spring';
}

function app_semester_label(string $semester, bool $withSuffix = true, string $default = ''): string
{
    $label = app_enum_label('semester', $semester, $default !== '' ? $default : $semester);
    if ($withSuffix) {
        return $label;
    }

    return str_ends_with($label, '学期') ? substr($label, 0, -6) : $label;
}

function app_semester_year_label(int $year, string $semester, bool $withSuffix = true): string
{
    $label = app_semester_label($semester, $withSuffix, $semester);
    return $year > 0 ? ($year . ' 年 ' . $label) : $label;
}

function app_semester_sort_values(): array
{
    $keys = app_enum_keys('semester');
    if ($keys === []) {
        return [];
    }

    return array_reverse($keys);
}

function app_semester_sql_order_expr(string $column): string
{
    if (!preg_match('/^[A-Za-z0-9_.]+$/', $column)) {
        $column = 'semester';
    }

    $values = app_semester_sort_values();
    if ($values === []) {
        return '0';
    }

    $quoted = array_map(
        static fn(string $value): string => "'" . str_replace("'", "''", $value) . "'",
        $values
    );

    return 'FIELD(' . $column . ', ' . implode(', ', $quoted) . ')';
}

function app_super_admin_role(): string
{
    return (string) app_config('auth.super_admin_role', 'super_admin');
}

function app_is_super_admin_role($role): bool
{
    return is_string($role) && $role !== '' && $role === app_super_admin_role();
}

function app_frontend_cdn_url(string $key, string $default = ''): string
{
    $value = app_config('frontend.cdn.' . $key, '');
    return is_string($value) && $value !== '' ? $value : $default;
}

function app_login_user(PDO $pdo, array $user): void
{
    app_start_session();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['user_name'] = (string) ($user['name'] ?? '');

    $role = app_find_user_role($pdo, (int) $user['user_id']);
    if ($role === 'teacher') {
        $_SESSION['teacher_id'] = (int) $user['user_id'];
    } else {
        unset($_SESSION['teacher_id']);
    }
}

function app_logout_and_redirect(?string $path = null): never
{
    app_start_session();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
    header('Location: ' . ($path ?? app_login_url()));
    exit;
}
