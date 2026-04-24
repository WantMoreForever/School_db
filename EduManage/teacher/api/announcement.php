<?php

declare(strict_types=1);

/**
 * Teacher announcement API.
 * 保留 is_pinned 字段作为兼容字段，但前端展示语义统一为“优先显示”。
 */
require_once __DIR__ . '/../../components/bootstrap.php';

app_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

require_once __DIR__ . '/config.php';

$pdo = get_pdo();
$teacherId = require_teacher_auth($pdo);
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_section_announcements' => action_get_section($pdo, $teacherId),
        'get_teacher_announcements' => action_get_teacher($pdo, $teacherId),
        'get_inbox' => action_get_inbox($pdo, $teacherId),
        'post_announcement' => action_post($pdo, $teacherId),
        'update_announcement' => action_update($pdo, $teacherId),
        'delete_announcement' => action_delete($pdo, $teacherId),
        'pin_announcement' => action_pin($pdo, $teacherId),
        default => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    teacher_handle_exception($e, 'teacher.api.announcement');
} catch (Throwable $e) {
    teacher_handle_exception($e, 'teacher.api.announcement');
}

function action_get_section(PDO $pdo, int $teacherId): void
{
    $sectionId = (int) ($_GET['section_id'] ?? 0);
    if (!$sectionId) {
        json_err('section_id required');
    }

    $rows = app_call_rows($pdo, 'sp_project_get_section_announcements', [$sectionId, $teacherId]);

    json_ok($rows);
}

function action_get_teacher(PDO $pdo, int $teacherId): void
{
    $rows = app_call_rows($pdo, 'sp_project_get_author_announcements', [$teacherId]);

    json_ok($rows);
}

function action_get_inbox(PDO $pdo, int $teacherId): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) ($_GET['per_page'] ?? 10);
    if ($perPage <= 0) {
        $perPage = 10;
    }
    $perPage = min(10, $perPage);
    $offset = ($page - 1) * $perPage;

    $sets = app_call_multi_result_rows($pdo, 'sp_project_get_teacher_visible_announcements', [$teacherId, $q, $perPage, $offset]);
    $total = (int)($sets[0][0]['total'] ?? 0);
    $rows = $sets[1] ?? [];

    foreach ($rows as $index => $row) {
        $targetsStr = $row['targets'] ?? '';
        $label = '全体';
        if ($targetsStr !== '') {
            $parts = array_unique(array_filter(array_map('trim', explode(',', $targetsStr))));
            foreach ($parts as $part) {
                [$targetType, $targetId] = array_pad(explode(':', $part, 2), 2, '');
                if ($targetType === 'section') {
                    $label = !empty($row['course_name'])
                        ? '课程：' . $row['course_name']
                        : '班级: ' . ($targetId !== '' ? 'ID ' . (int) $targetId : '');
                    break;
                }
                if ($targetType === 'teachers') {
                    $label = '全体教师';
                    break;
                }
                if ($targetType === 'all') {
                    $label = '全体';
                    break;
                }
            }
        }

        $rows[$index]['receiver'] = $label;
        $rows[$index]['is_author'] = ((int) ($row['author_user_id'] ?? 0) === $teacherId) ? 1 : 0;
    }

    teacher_json_send([
        'data' => $rows,
        'announcements' => $rows,
        'meta' => [
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $perPage > 0 ? (int) ceil($total / $perPage) : 0,
            'q' => $q,
        ],
    ]);
}

function action_post(PDO $pdo, int $teacherId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $sectionId = (int) ($body['section_id'] ?? 0);
    $title = trim((string) ($body['title'] ?? ''));
    $content = trim((string) ($body['content'] ?? ''));
    $isPinned = (int) ($body['is_pinned'] ?? 0);

    if (!$sectionId) {
        json_err('section_id required');
    }
    if ($title === '') {
        json_err('title required');
    }
    if ($content === '') {
        json_err('content required');
    }

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id = ? AND section_id = ?');
    $check->execute([$teacherId, $sectionId]);
    if (!$check->fetch()) {
        json_err('Not authorized to post to this section', 403);
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'INSERT INTO announcement (author_user_id, title, content, status, is_pinned, published_at, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW(), NOW())'
        );
        $stmt->execute([$teacherId, $title, $content, 'published', $isPinned]);
        $announcementId = (int) $pdo->lastInsertId();

        $targetStmt = $pdo->prepare(
            'INSERT INTO announcement_target (announcement_id, target_type, target_id) VALUES (?, ?, ?)'
        );
        $targetStmt->execute([$announcementId, 'section', $sectionId]);

        $pdo->commit();

        if (function_exists('sys_log')) {
            sys_log($pdo, $teacherId, sys_log_build('发布公告', [
                'teacher_id' => $teacherId,
                'announcement_id' => $announcementId,
                'title' => $title,
            ]), 'announcement', $announcementId);
        }

        json_ok(['announcement_id' => $announcementId, 'message' => '公告已发布']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        teacher_handle_exception($e, 'teacher.api.announcement.post');
    }
}

function action_update(PDO $pdo, int $teacherId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $announcementId = (int) ($body['announcement_id'] ?? 0);
    if (!$announcementId) {
        json_err('announcement_id required');
    }

    $title = array_key_exists('title', $body) ? trim((string) $body['title']) : null;
    $content = array_key_exists('content', $body) ? trim((string) $body['content']) : null;

    $check = $pdo->prepare('SELECT author_user_id FROM announcement WHERE announcement_id = ?');
    $check->execute([$announcementId]);
    $row = $check->fetch();
    if (!$row) {
        json_err('Announcement not found', 404);
    }
    if ((int) $row['author_user_id'] !== $teacherId) {
        json_err('Not authorized', 403);
    }

    $sets = [];
    $params = [];
    if ($title !== null) {
        $sets[] = 'title = ?';
        $params[] = $title;
    }
    if ($content !== null) {
        $sets[] = 'content = ?';
        $params[] = $content;
    }
    if ($sets === []) {
        json_err('Nothing to update');
    }

    $params[] = $announcementId;
    $sql = 'UPDATE announcement SET ' . implode(', ', $sets) . ', updated_at = NOW() WHERE announcement_id = ?';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    if (function_exists('sys_log')) {
        sys_log($pdo, $teacherId, sys_log_build('更新公告', [
            'teacher_id' => $teacherId,
            'announcement_id' => $announcementId,
        ]), 'announcement', $announcementId);
    }

    json_ok(['message' => '更新成功']);
}

function action_delete(PDO $pdo, int $teacherId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $announcementId = (int) ($body['announcement_id'] ?? 0);
    if (!$announcementId) {
        json_err('announcement_id required');
    }

    $check = $pdo->prepare('SELECT author_user_id FROM announcement WHERE announcement_id = ?');
    $check->execute([$announcementId]);
    $row = $check->fetch();
    if (!$row) {
        json_err('Announcement not found', 404);
    }
    if ((int) $row['author_user_id'] !== $teacherId) {
        json_err('Not authorized', 403);
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM announcement_target WHERE announcement_id = ?')->execute([$announcementId]);
        $pdo->prepare('DELETE FROM announcement WHERE announcement_id = ?')->execute([$announcementId]);
        $pdo->commit();

        if (function_exists('sys_log')) {
            sys_log($pdo, $teacherId, sys_log_build('删除公告', [
                'teacher_id' => $teacherId,
                'announcement_id' => $announcementId,
            ]), 'announcement', $announcementId);
        }

        json_ok(['message' => '删除成功']);
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        teacher_handle_exception($e, 'teacher.api.announcement.delete');
    }
}

function action_pin(PDO $pdo, int $teacherId): void
{
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $announcementId = (int) ($body['announcement_id'] ?? 0);
    $pin = (int) ($body['pin'] ?? 0);
    if (!$announcementId) {
        json_err('announcement_id required');
    }

    $check = $pdo->prepare('SELECT author_user_id FROM announcement WHERE announcement_id = ?');
    $check->execute([$announcementId]);
    $row = $check->fetch();
    if (!$row) {
        json_err('Announcement not found', 404);
    }
    if ((int) $row['author_user_id'] !== $teacherId) {
        json_err('Not authorized', 403);
    }

    $stmt = $pdo->prepare('UPDATE announcement SET is_pinned = ?, updated_at = NOW() WHERE announcement_id = ?');
    $stmt->execute([$pin, $announcementId]);

    $actionText = $pin ? '优先显示公告' : '取消优先显示公告';
    if (function_exists('sys_log')) {
        sys_log($pdo, $teacherId, sys_log_build($actionText, [
            'teacher_id' => $teacherId,
            'announcement_id' => $announcementId,
        ]), 'announcement', $announcementId);
    }

    json_ok(['message' => $actionText]);
}
