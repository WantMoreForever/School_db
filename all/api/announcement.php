<?php
/**
 * Announcement API
 * Calls stored procedures defined in announcement_procedures.sql
 */
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
$pdo        = get_pdo();
$TEACHER_ID = get_teacher_id($pdo);
$action     = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_section_announcements' => action_get_section($pdo, $TEACHER_ID),
        'get_teacher_announcements' => action_get_teacher($pdo, $TEACHER_ID),
        'post_announcement'         => action_post($pdo, $TEACHER_ID),
        'update_announcement'       => action_update($pdo, $TEACHER_ID),
        'delete_announcement'       => action_delete($pdo, $TEACHER_ID),
        'pin_announcement'          => action_pin($pdo, $TEACHER_ID),
        default                     => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    json_err('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

/** GET ?action=get_section_announcements&section_id=X */
function action_get_section(PDO $pdo, int $tid): void {
    $sid = (int)($_GET['section_id'] ?? 0);
    if (!$sid) json_err('section_id required');

    $stmt = $pdo->prepare('CALL sp_get_section_announcements(?)');
    $stmt->execute([$sid]);
    json_ok($stmt->fetchAll());
}

/** GET ?action=get_teacher_announcements */
function action_get_teacher(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_announcements(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/** POST body: { section_id, title, content, is_pinned? } */
function action_post(PDO $pdo, int $tid): void {
    $b          = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $section_id = (int)($b['section_id'] ?? 0);
    $title      = trim($b['title']   ?? '');
    $content    = trim($b['content'] ?? '');
    $is_pinned  = (int)($b['is_pinned'] ?? 0);

    if (!$section_id) json_err('section_id required');
    if (!$title)      json_err('title required');
    if (!$content)    json_err('content required');

    $stmt = $pdo->prepare('CALL sp_post_announcement(?,?,?,?,?,@ann_id,@ok,@msg)');
    $stmt->execute([$tid, $section_id, $title, $content, $is_pinned]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ann_id AS id, @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['announcement_id' => (int)$r['id'], 'message' => $r['msg']]);
}

/** POST body: { announcement_id, title?, content? } */
function action_update(PDO $pdo, int $tid): void {
    $b   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $aid = (int)($b['announcement_id'] ?? 0);
    if (!$aid) json_err('announcement_id required');

    $title   = trim($b['title']   ?? '') ?: null;
    $content = trim($b['content'] ?? '') ?: null;

    $stmt = $pdo->prepare('CALL sp_update_announcement(?,?,?,?,@ok,@msg)');
    $stmt->execute([$aid, $tid, $title, $content]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['message' => $r['msg']]);
}

/** POST body: { announcement_id } */
function action_delete(PDO $pdo, int $tid): void {
    $b   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $aid = (int)($b['announcement_id'] ?? 0);
    if (!$aid) json_err('announcement_id required');

    $stmt = $pdo->prepare('CALL sp_delete_announcement(?,?,@ok,@msg)');
    $stmt->execute([$aid, $tid]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['message' => $r['msg']]);
}

/** POST body: { announcement_id, pin } — pin: 1=置顶 0=取消 */
function action_pin(PDO $pdo, int $tid): void {
    $b   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $aid = (int)($b['announcement_id'] ?? 0);
    $pin = (int)($b['pin'] ?? 0);
    if (!$aid) json_err('announcement_id required');

    $stmt = $pdo->prepare('CALL sp_pin_announcement(?,?,?,@ok,@msg)');
    $stmt->execute([$aid, $tid, $pin]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['message' => $r['msg']]);
}
