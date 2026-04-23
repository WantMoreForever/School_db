<?php
/**
 * Database Configuration
 * school_db - Teacher Portal
 */
// Database credentials are maintained centrally in components/db.php.
// Prefer using the shared PDO provided by components/db.php. Fallback to
// legacy globals/constants if the shared component is not available.

$bootstrap = __DIR__ . '/../../components/bootstrap.php';
if (file_exists($bootstrap)) {
    require_once $bootstrap;
}

$componentsDb = __DIR__ . '/../../components/db.php';
if (file_exists($componentsDb)) {
    require_once $componentsDb;
}

define('AVATAR_DIR', rtrim(app_avatar_dir(), '/\\') . DIRECTORY_SEPARATOR);
define('AVATAR_URL', app_avatar_url());
$avatarAllowed = app_config('upload.avatar.allowed_mimes', []);
define('AVATAR_MAX_SIZE', (int) app_config('upload.avatar.max_size', 0));
define('AVATAR_ALLOWED', is_array($avatarAllowed) ? array_keys($avatarAllowed) : []);

function get_pdo(): PDO
{
    if (function_exists('app_require_pdo')) {
        return app_require_pdo();
    }

    static $pdo = null;
    if ($pdo === null) {
        $databaseConfig = app_config('database', []);
        $host = (string) ($databaseConfig['host'] ?? '');
        $port = (int) ($databaseConfig['port'] ?? 0);
        $name = (string) ($databaseConfig['database'] ?? '');
        $user = (string) ($databaseConfig['username'] ?? '');
        $pass = (string) ($databaseConfig['password'] ?? '');
        $charset = (string) ($databaseConfig['charset'] ?? '');
        if ($host === '' || $port <= 0 || $name === '' || $user === '' || $charset === '') {
            throw new RuntimeException('Database configuration is incomplete.');
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $host, $port, $name, $charset);
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $pdo;
}

function get_teacher_id(PDO $pdo): ?int
{
    if (!empty($_SESSION['teacher_id'])) {
        return (int) $_SESSION['teacher_id'];
    }

    if (!empty($_SESSION['user_id'])) {
        $uid = (int) $_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT user_id FROM teacher WHERE user_id = ? LIMIT 1');
        $stmt->execute([$uid]);
        if ($stmt->fetch()) {
            $_SESSION['teacher_id'] = $uid;
            return $uid;
        }
    }

    return null;
}

function require_teacher_auth(PDO $pdo): int
{
    if (empty($_SESSION['teacher_id']) && empty($_SESSION['user_id'])) {
        teacher_json_send([
            'error' => '未登录，请先通过教务系统登录',
            'redirect' => app_login_url(),
        ], 401);
    }

    $teacherId = get_teacher_id($pdo);
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

$loggerPath = __DIR__ . '/../../components/logger.php';
if (file_exists($loggerPath)) {
    require_once $loggerPath;
}
