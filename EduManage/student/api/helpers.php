<?php
// student/api/helpers.php

declare(strict_types=1);

define('IN_STUDENT', true);
require_once __DIR__ . '/../../components/bootstrap.php';

// 引入日志组件，便于 student API 中复用 sys_log()
$loggerPath = __DIR__ . '/../../components/logger.php';
if (file_exists($loggerPath)) {
    require_once $loggerPath;
}

function student_api_bootstrap(): void
{
    app_start_session();
    app_send_json_header();
}

function student_api_no_cache(): void
{
    app_send_no_cache_headers();
    header('Expires: 0');
}

function student_api_require_login(): int
{
    $uid = $_SESSION['user_id'] ?? null;
    if (!$uid) {
        student_api_json_error('未登录，请先登录', 401, [
            'code' => app_api_error_code_for_status(401),
            'error' => 'unauthenticated',
        ]);
    }

    // 如果有数据库连接，则验证用户状态，只有 active 才允许继续访问。
    $pdo = app_db();
    if ($pdo !== null) {
        try {
            $stmt = $pdo->prepare('SELECT status FROM user WHERE user_id = ? LIMIT 1');
            $stmt->execute([(int) $uid]);
            $status = $stmt->fetchColumn();
            if (!is_string($status) || $status !== 'active') {
                student_api_json_error('账号已被停用或封禁', 403, ['error' => 'inactive']);
            }
        } catch (Throwable $e) {
            student_api_json_error('服务器错误', 500, ['error' => 'server_error']);
        }
    }

    return (int) $uid;
}

function student_api_ensure_csrf_token(): string
{
    return app_ensure_csrf_token();
}

function student_api_validate_csrf(?string $token): bool
{
    return app_validate_csrf($token);
}

function student_api_require_pdo(): PDO
{
    try {
        return app_require_pdo();
    } catch (Throwable $e) {
        student_api_json_error('数据库连接失败', 500, ['error' => 'db_error']);
    }
}

function student_api_utf8($value)
{
    if (is_array($value)) {
        $out = [];
        foreach ($value as $key => $item) {
            $newKey = is_string($key) ? student_api_utf8_string($key) : $key;
            $out[$newKey] = student_api_utf8($item);
        }
        return $out;
    }

    if (is_object($value)) {
        $vars = get_object_vars($value);
        return (object) student_api_utf8($vars);
    }

    if (is_string($value)) {
        return student_api_utf8_string($value);
    }

    return $value;
}

function student_api_utf8_string(?string $value): string
{
    if ($value === null || $value === '') {
        return (string) $value;
    }

    if (function_exists('mb_check_encoding')) {
        if (mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        $converted = @mb_convert_encoding($value, 'UTF-8', 'GBK');
        if (is_string($converted) && mb_check_encoding($converted, 'UTF-8')) {
            return $converted;
        }

        return $value;
    }

    if (function_exists('iconv')) {
        $converted = @iconv('GBK', 'UTF-8//IGNORE', $value);
        return is_string($converted) && $converted !== '' ? $converted : $value;
    }

    return $value;
}

function student_api_json_ok(array $payload): never
{
    student_api_json_send($payload);
}

function student_api_json_error(string $message, int $status = 400, array $extra = []): never
{
    student_api_json_send(array_merge([
        'message' => $message,
        'error' => $extra['error'] ?? $message,
        'code' => $extra['code'] ?? student_api_error_code_for_status($status),
        'ok' => false,
        'success' => false,
    ], $extra), $status);
}

function student_api_json_send(array $payload, int $status = 200): never
{
    http_response_code($status);

    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $flags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }

    $json = json_encode(student_api_utf8(student_api_json_envelope($payload, $status)), $flags);
    if ($json === false) {
        $fallback = json_encode([
            'ok' => false,
            'success' => false,
            'code' => app_api_error_code_for_status(500),
            'error' => 'JSON 编码失败',
            'message' => 'JSON 编码失败',
        ], $flags);

        echo $fallback !== false ? $fallback : '{"success":false,"message":"JSON 编码失败"}';
        exit;
    }

    echo $json;
    exit;
}

function student_api_json_envelope(array $payload, int $status = 200): array
{
    $isSuccess = true;
    if (array_key_exists('ok', $payload)) {
        $isSuccess = $payload['ok'] === true;
    } elseif (array_key_exists('success', $payload)) {
        $isSuccess = $payload['success'] !== false;
    } elseif ($status >= 400) {
        $isSuccess = false;
    }

    $message = (string) ($payload['message'] ?? $payload['msg'] ?? ($isSuccess ? '操作成功' : '请求失败'));
    $code = (string) ($payload['code'] ?? ($isSuccess ? app_config('api.default_success_code', 'OK') : student_api_error_code_for_status($status)));
    $error = $payload['error'] ?? ($isSuccess ? null : $message);

    $envelope = [
        'ok' => $isSuccess,
        'success' => $isSuccess,
        'code' => $code,
        'message' => $message,
    ];
    if (!$isSuccess || $error !== null) {
        $envelope['error'] = $error;
    }

    return array_merge($envelope, $payload, [
        'ok' => $isSuccess,
        'success' => $isSuccess,
        'code' => $code,
        'message' => $message,
    ]);
}

function student_api_error_code_for_status(int $status): string
{
    return app_api_error_code_for_status($status);
}
