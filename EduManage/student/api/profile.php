<?php
// API: student/api/profile.php
// 返回学生个人信息页面所需的 JSON 数据，供 student/spa.html 与 js/profile.js 使用。

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
student_api_bootstrap();
student_api_no_cache();

require_once __DIR__ . '/../../components/db.php';
require_once __DIR__ . '/../../components/student_data.php';

$pdo = student_api_require_pdo();
$uid = student_api_require_login();

// 为 SPA 表单准备 CSRF Token。
$_SESSION['csrf_token'] = student_api_ensure_csrf_token();

// 学生端上传配置。
$studentConfig = include __DIR__ . '/config.php';

function build_profile_payload(PDO $pdo, int $uid, string $successMsg = '', string $errorMsg = ''): array
{
    $student = getStudentBaseInfo($pdo, $uid);

    $activePage = 'profile';
    ob_start();
    include __DIR__ . '/../../components/alerts.php';
    $alertsHtml = ob_get_clean();

    return [
        'student' => student_api_utf8($student),
        'alerts_html' => student_api_utf8_string($alertsHtml ?: ''),
        'success_msg' => student_api_utf8_string($successMsg),
        'error_msg' => student_api_utf8_string($errorMsg),
        'csrf_token' => $_SESSION['csrf_token'] ?? null,
    ];
}

function emit_profile_response(bool $ok, array $payload, ?string $error = null): never
{
    $response = ['success' => $ok] + $payload;
    if ($error !== null) {
        $response['error'] = $error;
    }

    student_api_json_ok($response);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedToken = $_POST['csrf_token'] ?? null;
    if (!student_api_validate_csrf($postedToken)) {
        $payload = build_profile_payload($pdo, $uid, '', 'CSRF 验证失败，请刷新页面后重试。');
        emit_profile_response(false, $payload, 'invalid_csrf');
    }

    try {
        if (!empty($_FILES['avatar']['name'])) {
            $file = $_FILES['avatar'];

            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                $payload = build_profile_payload($pdo, $uid, '', '上传失败，错误代码：' . (string) ($file['error'] ?? 'unknown'));
                emit_profile_response(false, $payload, 'upload_error');
            }

            $maxAvatarSize = (int) ($studentConfig['avatar']['max_size'] ?? app_config('upload.avatar.max_size', 0));
            if (($file['size'] ?? 0) > $maxAvatarSize) {
                $payload = build_profile_payload(
                    $pdo,
                    $uid,
                    '',
                    '文件大小不能超过 ' . number_format($maxAvatarSize / 1024 / 1024, 0) . ' MB。'
                );
                emit_profile_response(false, $payload, 'file_too_large');
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = $finfo ? finfo_file($finfo, $file['tmp_name']) : false;
            if ($finfo) {
                finfo_close($finfo);
            }

            $allowedMimes = $studentConfig['avatar']['allowed_mimes'] ?? app_config('upload.avatar.allowed_mimes', []);
            if (!is_array($allowedMimes)) {
                $allowedMimes = [];
            }

            if (!is_string($mime) || !isset($allowedMimes[$mime])) {
                $payload = build_profile_payload($pdo, $uid, '', '不支持的图片格式：' . (string) $mime);
                emit_profile_response(false, $payload, 'invalid_mime');
            }

            $filename = sprintf('avatar_%d_%d.%s', $uid, time(), $allowedMimes[$mime]);
            $uploadDir = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR;

            if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                $payload = build_profile_payload($pdo, $uid, '', '头像目录创建失败。');
                emit_profile_response(false, $payload, 'create_dir_failed');
            }

            $destination = $uploadDir . $filename;
            if (!move_uploaded_file($file['tmp_name'], $destination)) {
                $payload = build_profile_payload($pdo, $uid, '', '保存文件失败。');
                emit_profile_response(false, $payload, 'save_failed');
            }

            $oldStmt = $pdo->prepare('SELECT image FROM user WHERE user_id = ?');
            $oldStmt->execute([$uid]);
            $oldImage = (string) ($oldStmt->fetchColumn() ?: '');
            $oldPath = $uploadDir . basename($oldImage);
            if ($oldImage !== '' && $oldImage !== $filename && is_file($oldPath)) {
                @unlink($oldPath);
            }

            $updateStmt = $pdo->prepare('UPDATE user SET image = ? WHERE user_id = ?');
            $updateStmt->execute([$filename, $uid]);

            if (function_exists('sys_log')) {
                sys_log($pdo, $uid, sys_log_build('更新头像', [
                    'user_id' => $uid,
                    'filename' => $filename,
                ]), 'user', $uid);
            }

            $payload = build_profile_payload($pdo, $uid, '头像已更新。', '');
            emit_profile_response(true, $payload);
        }

        if (($_POST['action'] ?? '') === 'update_info') {
            $phone = trim((string) ($_POST['phone'] ?? ''));
            $phoneValue = $phone === '' ? null : $phone;

            if ($phoneValue !== null && !preg_match('/^\d{11}$/', $phoneValue)) {
                $payload = build_profile_payload($pdo, $uid, '', '手机号必须为 11 位数字。');
                emit_profile_response(false, $payload, 'invalid_phone');
            }

            $updateStmt = $pdo->prepare('UPDATE user SET phone = ? WHERE user_id = ?');
            $updateStmt->execute([$phoneValue, $uid]);

            if (function_exists('sys_log')) {
                sys_log($pdo, $uid, sys_log_build('更新联系电话', [
                    'user_id' => $uid,
                    'phone' => $phoneValue ?? 'NULL',
                ]), 'user', $uid);
            }

            $payload = build_profile_payload($pdo, $uid, '个人信息已保存。', '');
            emit_profile_response(true, $payload);
        }

        $payload = build_profile_payload($pdo, $uid);
        emit_profile_response(true, $payload);
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $payload = build_profile_payload($pdo, $uid, '', '数据库错误：' . $e->getMessage());
        emit_profile_response(false, $payload, 'db_exception');
    }
}

$successMsg = (string) ($_SESSION['flash_success'] ?? '');
$errorMsg = (string) ($_SESSION['flash_error'] ?? '');
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$payload = build_profile_payload($pdo, $uid, $successMsg, $errorMsg);
emit_profile_response(true, $payload);
