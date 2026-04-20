<?php
/**
 * Audit / Backup API
 * Calls stored procedures defined in backup_audit.sql
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
        'get_exam_audit'   => action_get_exam_audit($pdo, $TEACHER_ID),
        'get_grade_audit'  => action_get_grade_audit($pdo, $TEACHER_ID),
        'restore_score'    => action_restore_score($pdo, $TEACHER_ID),
        'get_snapshot'     => action_get_snapshot($pdo, $TEACHER_ID),
        default            => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    json_err('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * GET ?action=get_exam_audit&section_id=X[&student_id=Y]
 */
function action_get_exam_audit(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    if (!$section_id) json_err('section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_exam_audit(?, ?)');
    $stmt->execute([$section_id, $student_id]);
    json_ok($stmt->fetchAll());
}

/**
 * GET ?action=get_grade_audit&section_id=X[&student_id=Y]
 */
function action_get_grade_audit(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
    if (!$section_id) json_err('section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_grade_audit(?, ?)');
    $stmt->execute([$section_id, $student_id]);
    json_ok($stmt->fetchAll());
}

/**
 * POST body: { audit_id }
 * Restore exam score to the old_score recorded in the given audit entry.
 */
function action_restore_score(PDO $pdo, int $tid): void {
    $b        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $audit_id = (int)($b['audit_id'] ?? 0);
    if (!$audit_id) json_err('audit_id required');

    // Set session var so the restore UPDATE itself is also audited with correct actor
    $pdo->exec("SET @current_user_id = {$tid}");

    $stmt = $pdo->prepare('CALL sp_restore_exam_score(?,?,@ok,@msg)');
    $stmt->execute([$audit_id, $tid]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['message' => $r['msg']]);
}

/**
 * GET ?action=get_snapshot&section_id=X
 * Returns a point-in-time view of all grades in the section.
 */
function action_get_snapshot(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_section_grade_snapshot(?)');
    $stmt->execute([$section_id]);
    json_ok($stmt->fetchAll());
}
