<?php
/**
 * admin/api/shared.php
 * 管理后台 API 共享工具：统一响应、CSRF 校验、上传处理、字段规范化与公告目标处理。
 */

function admin_api_error_response(bool $isAjax, string $redirect, string $message, int $status = 400): void
{
    if ($isAjax) {
        admin_api_json_response(false, ['error' => $message, 'message' => $message], $status);
    }

    header('Location: ' . admin_api_redirect_url($redirect, ['error' => $message]));
    exit;
}

function admin_api_success_response(bool $isAjax, string $redirect, string $message, array $payload = []): void
{
    if ($isAjax) {
        admin_api_json_response(true, array_merge(['message' => $message], $payload));
    }

    header('Location: ' . admin_api_redirect_url($redirect));
    exit;
}

function admin_api_json_response(bool $ok, array $payload = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(admin_api_json_envelope($ok, $payload, $status), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function admin_api_json_envelope(bool $ok, array $payload = [], int $status = 200): array
{
    $message = (string) ($payload['message'] ?? $payload['msg'] ?? ($ok ? '操作成功' : ($payload['error'] ?? '请求失败')));
    $code = (string) ($payload['code'] ?? ($ok ? 'OK' : admin_api_error_code_for_status($status)));
    $error = $payload['error'] ?? ($ok ? null : $message);

    $envelope = [
        'ok' => $ok,
        'success' => $ok,
        'code' => $code,
        'message' => $message,
    ];
    if (!$ok || $error !== null) {
        $envelope['error'] = $error;
    }

    return array_merge($envelope, $payload, [
        'ok' => $ok,
        'success' => $ok,
        'code' => $code,
        'message' => $message,
    ]);
}

function admin_api_error_code_for_status(int $status): string
{
    return match ($status) {
        401 => 'ERR_UNAUTHENTICATED',
        403 => 'ERR_FORBIDDEN',
        404 => 'ERR_NOT_FOUND',
        409 => 'ERR_CONFLICT',
        419 => 'ERR_CSRF',
        500, 501, 502, 503, 504 => 'ERR_SERVER',
        default => $status >= 500 ? 'ERR_SERVER' : 'ERR_VALIDATION',
    };
}

function admin_api_redirect_url(string $redirect, array $query = [], string $default = 'index.php'): string
{
    $redirect = admin_api_clean_redirect($redirect, $default);

    if (preg_match('/^[a-z][a-z0-9+.-]*:\/\//i', $redirect)) {
        $redirect = $default;
    }

    $fragment = '';
    $hashPos = strpos($redirect, '#');
    if ($hashPos !== false) {
        $fragment = substr($redirect, $hashPos);
        $redirect = substr($redirect, 0, $hashPos);
    }

    $existingQuery = '';
    $queryPos = strpos($redirect, '?');
    if ($queryPos !== false) {
        $existingQuery = substr($redirect, $queryPos + 1);
        $redirect = substr($redirect, 0, $queryPos);
    }

    if ($redirect === '') {
        $redirect = $default;
    }

    if (str_starts_with($redirect, '/')) {
        $url = $redirect;
    } else {
        $script = basename(str_replace('\\', '/', $redirect));
        $pageKey = '';
        if (function_exists('admin_page_registry')) {
            foreach (admin_page_registry() as $key => $meta) {
                if (($meta['script'] ?? '') === $script) {
                    $pageKey = (string) $key;
                    break;
                }
            }
        }

        if ($pageKey !== '' && function_exists('admin_page_url')) {
            $url = admin_page_url($pageKey);
        } elseif (str_starts_with(str_replace('\\', '/', $redirect), 'admin/')) {
            $url = app_url($redirect);
        } else {
            $url = app_url('admin/' . ltrim($redirect, '/'));
        }
    }

    $params = [];
    if ($existingQuery !== '') {
        parse_str($existingQuery, $params);
    }
    foreach ($query as $key => $value) {
        if ($value === null || $value === '') {
            unset($params[$key]);
            continue;
        }
        $params[$key] = (string) $value;
    }

    if ($params !== []) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    return $url . $fragment;
}

function admin_api_redirect(string $redirect, array $query = []): never
{
    header('Location: ' . admin_api_redirect_url($redirect, $query));
    exit;
}

function admin_api_clean_redirect(string $redirect, string $default = 'index.php'): string
{
    $redirect = trim($redirect);
    if ($redirect === '') {
        return $default;
    }

    return preg_replace('/[\r\n].*/', '', $redirect) ?: $default;
}

function admin_api_require_csrf(bool $isAjax, string $redirect = 'index.php'): void
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!app_validate_csrf($token)) {
        if ($isAjax) {
            admin_api_json_response(false, [
                'code' => 'ERR_CSRF',
                'error' => 'CSRF 验证失败',
                'message' => 'CSRF 验证失败',
            ], 419);
        }

        header('Location: ' . admin_api_redirect_url($redirect, ['error' => 'CSRF 验证失败']));
        exit;
    }
}

function admin_api_protect_unsafe_action(string $act, bool $isAjax): void
{
    $unsafeActs = [
        'add_student',
        'del_student',
        'add_teacher',
        'del_teacher',
        'toggle_status',
        'reset_password',
        'update_student',
        'update_teacher',
        'update_self',
        'add_course',
        'update_course',
        'add_announcement',
        'update_announcement',
        'delete_announcement',
        'pin_announcement',
        'add_classroom',
        'update_classroom',
        'add_schedule',
        'update_schedule',
        'del_schedule',
        'add_department',
        'update_department',
        'add_major',
        'update_major',
    ];

    if (in_array($act, $unsafeActs, true)) {
        admin_api_require_csrf($isAjax);
    }
}

function admin_api_handle_avatar_upload()
{
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $file = $_FILES['avatar'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => '上传头像失败'];
    }
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['error' => '头像文件过大，最大 2MB'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        return ['error' => '不支持的图片格式'];
    }

    $uploadDir = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $filename = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
    $destination = $uploadDir . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        return ['error' => '保存头像失败'];
    }

    return $filename;
}

function admin_api_delete_avatar_file(?string $filename): void
{
    if (!$filename) {
        return;
    }

    $path = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR . basename($filename);
    if (is_file($path)) {
        @unlink($path);
    }
}

function admin_api_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function admin_api_validate_school_email(string $email): ?string
{
    if ($email === '') {
        return '邮箱不能为空';
    }
    if (!preg_match('/^[A-Za-z0-9._%+-]+@school\.edu$/', $email)) {
        return '邮箱必须是以 @school.edu 结尾的有效地址';
    }

    return null;
}

function admin_api_validate_phone(string $phone): ?string
{
    if ($phone === '') {
        return null;
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        return '手机号必须为11位数字';
    }

    return null;
}

function admin_api_validate_student_no(string $studentNo): ?string
{
    if (!preg_match('/^\d{8}$/', $studentNo)) {
        return '学号必须为8位数字';
    }

    return null;
}

function admin_api_normalize_major_code(string $majorCode): string
{
    return strtoupper(trim($majorCode));
}

function admin_api_validate_major_code(string $majorCode): ?string
{
    if (!preg_match('/^[A-Z]{1,10}$/', $majorCode)) {
        return '专业代码必须为1-10位大写字母';
    }

    return null;
}

function admin_api_normalize_department_code(string $deptCode): string
{
    return strtoupper(trim($deptCode));
}

function admin_api_validate_department_code(string $deptCode): ?string
{
    if (!preg_match('/^[A-Z]{1,10}$/', $deptCode)) {
        return '院系代码必须为 1-10 位大写字母';
    }

    return null;
}

function admin_api_major_columns(PDO $pdo): array
{
    try {
        $colStmt = $pdo->query('SHOW COLUMNS FROM major');
        $columns = $colStmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $columns = [];
    }

    return [
        'has_dept' => in_array('dept_id', $columns, true),
        'has_code' => in_array('major_code', $columns, true),
        'has_name' => in_array('major_name', $columns, true),
    ];
}

function admin_api_resolve_announcement_target(PDO $pdo, string $target, int $majorId): array
{
    if (in_array($target, ['all', 'students', 'teachers'], true)) {
        return [$target, 0];
    }

    if ($target !== 'major') {
        return ['all', 0];
    }

    if ($majorId <= 0) {
        throw new InvalidArgumentException('请选择专业');
    }

    $stmt = $pdo->prepare('SELECT major_id FROM major WHERE major_id = ? LIMIT 1');
    $stmt->execute([$majorId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('所选专业不存在');
    }

    return ['major', $majorId];
}

function admin_api_store_announcement_target(PDO $pdo, int $announcementId, string $target, int $majorId, bool $replaceExisting = false): void
{
    [$targetType, $targetId] = admin_api_resolve_announcement_target($pdo, $target, $majorId);

    if ($replaceExisting) {
        $pdo->prepare('DELETE FROM announcement_target WHERE announcement_id = ?')->execute([$announcementId]);
    }

    $stmt = $pdo->prepare('INSERT INTO announcement_target (announcement_id, target_type, target_id) VALUES (?, ?, ?)');
    $stmt->execute([$announcementId, $targetType, $targetId]);
}
