<?php
/**
 * Database Configuration
 * school_db - Teacher Portal
 */
declare(strict_types=1);

require_once __DIR__ . '/../../components/bootstrap.php';
require_once __DIR__ . '/../../components/db.php';

define('AVATAR_DIR', rtrim(app_avatar_dir(), '/\\') . DIRECTORY_SEPARATOR);
define('AVATAR_URL', app_avatar_url());
$avatarAllowed = app_config('upload.avatar.allowed_mimes', []);
define('AVATAR_MAX_SIZE', (int) app_config('upload.avatar.max_size', 0));
define('AVATAR_ALLOWED', is_array($avatarAllowed) ? array_keys($avatarAllowed) : []);

function get_pdo(): PDO
{
    return app_require_pdo();
}

function get_current_teacher_id(PDO $pdo): ?int
{
    return app_current_teacher_id($pdo);
}

function get_teacher_id(PDO $pdo): ?int
{
    return get_current_teacher_id($pdo);
}

function require_teacher_auth(PDO $pdo): int
{
    app_start_session();

    if (app_current_user_id() === null && app_session_numeric_value('teacher_id') === null) {
        teacher_json_send([
            'error' => '未登录，请先通过教务系统登录',
            'redirect' => app_login_url(),
        ], 401);
    }

    $teacherId = get_current_teacher_id($pdo);
    if ($teacherId === null) {
        teacher_json_send(['error' => '无教师访问权限'], 403);
    }

    return $teacherId;
}

function json_ok(mixed $data): never
{
    teacher_json_send(['data' => $data]);
}

function json_err(string $msg, int $code = 400): never
{
    teacher_json_send(['error' => $msg, 'message' => $msg], $code);
}

function teacher_json_send(array $payload, int $status = 200): never
{
    http_response_code($status);
    echo json_encode(teacher_json_envelope($payload, $status), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function teacher_json_envelope(array $payload, int $status = 200): array
{
    $isSuccess = true;
    if (array_key_exists('ok', $payload)) {
        $isSuccess = $payload['ok'] === true;
    } elseif (array_key_exists('success', $payload)) {
        $isSuccess = $payload['success'] !== false;
    } elseif ($status >= 400) {
        $isSuccess = false;
    }

    $message = (string) ($payload['message'] ?? $payload['msg'] ?? ($isSuccess ? '操作成功' : ($payload['error'] ?? '请求失败')));
    $code = (string) ($payload['code'] ?? ($isSuccess ? app_config('api.default_success_code', 'OK') : teacher_error_code_for_status($status)));
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

function teacher_error_code_for_status(int $status): string
{
    return app_api_error_code_for_status($status);
}

function teacher_debug_enabled(): bool
{
    return (bool) app_config('app.debug', false);
}

function teacher_log_exception(Throwable $e, string $context = 'teacher.api'): void
{
    error_log(sprintf(
        '[%s] %s in %s:%d',
        $context,
        $e->getMessage(),
        $e->getFile(),
        $e->getLine()
    ));
}

function teacher_public_error_message(Throwable $e): string
{
    $isDatabaseError = $e instanceof PDOException
        || stripos($e->getMessage(), 'SQLSTATE') !== false
        || stripos($e->getMessage(), 'database') !== false;

    if (teacher_debug_enabled()) {
        return $isDatabaseError
            ? '数据库操作失败：' . $e->getMessage()
            : '服务处理失败：' . $e->getMessage();
    }

    return $isDatabaseError
        ? '数据加载失败，请稍后重试'
        : '服务处理失败，请稍后重试';
}

function teacher_handle_exception(Throwable $e, string $context = 'teacher.api'): never
{
    teacher_log_exception($e, $context);

    $status = 500;
    teacher_json_send([
        'error' => teacher_public_error_message($e),
    ], $status);
}

function json_rows(PDOStatement $stmt): array
{
    return $stmt->fetchAll();
}

function consume_remaining_results(?PDOStatement $stmt): void
{
    if (!$stmt) {
        return;
    }

    try {
        do {
            $stmt->fetchAll();
        } while ($stmt->nextRowset());
    } catch (Throwable $e) {
    }

    try {
        $stmt->closeCursor();
    } catch (Throwable $e) {
    }
}

require_once __DIR__ . '/../../components/logger.php';
