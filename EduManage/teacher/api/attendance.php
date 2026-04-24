<?php
/**
 * Attendance API
 * Calls stored procedures defined in attendance_procedures.sql
 */
require_once __DIR__ . '/../../components/bootstrap.php';

app_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
$pdo        = get_pdo();
$TEACHER_ID = require_teacher_auth($pdo);
$action     = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_section_schedules'   => action_get_section_schedules($pdo, $TEACHER_ID),
        'get_schedule_attendance' => action_get_schedule_attendance($pdo, $TEACHER_ID),
        'get_student_summary'     => action_get_student_summary($pdo, $TEACHER_ID),
        'get_section_report'      => action_get_section_report($pdo, $TEACHER_ID),
        'record_attendance'       => action_record($pdo, $TEACHER_ID),
        'batch_record'            => action_batch_record($pdo, $TEACHER_ID),
        default                   => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    teacher_handle_exception($e, 'teacher.api.attendance');
} catch (Throwable $e) {
    teacher_handle_exception($e, 'teacher.api.attendance');
}

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * GET ?action=get_section_schedules&section_id=X
 * Reuses sp_get_schedule to list all time slots for a section,
 * so the frontend can build a "pick schedule + week" selector.
 */
function action_get_section_schedules(PDO $pdo, int $tid): void {
    $sid = (int)($_GET['section_id'] ?? 0);
    if (!$sid) json_err('section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $sid]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_schedule(?)');
    $stmt->execute([$sid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET ?action=get_schedule_attendance&schedule_id=X&week=N
 */
function action_get_schedule_attendance(PDO $pdo, int $tid): void {
    $schedule_id = (int)($_GET['schedule_id'] ?? 0);
    $week        = (int)($_GET['week'] ?? 0);
    if (!$schedule_id || !$week) json_err('schedule_id and week required');

    // Verify teacher owns this schedule via teaching table
    $check = $pdo->prepare(
        'SELECT 1 FROM schedule s JOIN teaching tg ON tg.section_id=s.section_id
         WHERE s.schedule_id=? AND tg.teacher_id=? LIMIT 1'
    );
    $check->execute([$schedule_id, $tid]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_schedule_attendance(?, ?)');
    $stmt->execute([$schedule_id, $week]);
    json_ok($stmt->fetchAll());
}

/**
 * GET ?action=get_student_summary&student_id=X&section_id=Y
 */
function action_get_student_summary(PDO $pdo, int $tid): void {
    $student_id = (int)($_GET['student_id'] ?? 0);
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$student_id || !$section_id) json_err('student_id and section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_student_attendance_summary(?, ?)');
    $stmt->execute([$student_id, $section_id]);
    $row = $stmt->fetch();
    json_ok($row ?: (object)[]);
}

/**
 * GET ?action=get_section_report&section_id=X&warn_threshold=75
 */
function action_get_section_report(PDO $pdo, int $tid): void {
    $section_id     = (int)($_GET['section_id'] ?? 0);
    $warn_threshold = isset($_GET['warn_threshold']) ? (float)$_GET['warn_threshold'] : 75.0;
    if (!$section_id) json_err('section_id required');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_section_attendance_report(?, ?)');
    $stmt->execute([$section_id, $warn_threshold]);
    json_ok($stmt->fetchAll());
}

/**
 * POST body: { schedule_id, student_id, week, status, note? }
 */
function action_record(PDO $pdo, int $tid): void {
    $b           = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $schedule_id = (int)($b['schedule_id'] ?? 0);
    $student_id  = (int)($b['student_id']  ?? 0);
    $week        = (int)($b['week']        ?? 0);
    $status      = trim($b['status'] ?? 'present');
    $note        = trim($b['note']   ?? '') ?: null;

    if (!$schedule_id || !$student_id || !$week) json_err('schedule_id, student_id and week required');

    $stmt = $pdo->prepare('CALL sp_record_attendance(?,?,?,?,?,?,@ok,@msg)');
    $stmt->execute([$tid, $schedule_id, $student_id, $week, $status, $note]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    sys_log($pdo, $tid, sys_log_build('记录考勤', [
        'teacher_id' => $tid,
        'schedule_id' => $schedule_id,
    ]), 'attendance', $schedule_id);
    json_ok(['message' => $r['msg']]);
}

/**
 * POST body: { schedule_id, week, records: [{student_id, status, note?}, ...] }
 * records is sent as a plain array and converted to JSON here.
 */
function action_batch_record(PDO $pdo, int $tid): void {
    $b           = json_decode(file_get_contents('php://input'), true) ?? [];
    $schedule_id = (int)($b['schedule_id'] ?? 0);
    $week        = (int)($b['week']        ?? 0);
    $records     = $b['records'] ?? [];

    if (!$schedule_id || !$week)  json_err('schedule_id and week required');
    if (!is_array($records) || !count($records)) json_err('records array required');

    // Validate each record
    $valid_statuses = ['present', 'absent', 'late', 'excused'];
    foreach ($records as &$rec) {
        $rec['student_id'] = (int)($rec['student_id'] ?? 0);
        $rec['status']     = in_array($rec['status'] ?? '', $valid_statuses) ? $rec['status'] : 'present';
        if (isset($rec['note'])) $rec['note'] = trim($rec['note']) ?: null;
        if (!$rec['student_id']) json_err('Each record must have a valid student_id');
    }
    unset($rec);

    $json = json_encode(array_values($records));

    $stmt = $pdo->prepare('CALL sp_batch_record_attendance(?,?,?,?,@cnt,@ok,@msg)');
    $stmt->execute([$tid, $schedule_id, $week, $json]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @cnt AS count, @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    sys_log($pdo, $tid, sys_log_build('批量记录考勤', [
        'teacher_id' => $tid,
        'schedule_id' => $schedule_id,
    ]), 'attendance', $schedule_id);
    json_ok(['saved' => (int)$r['count'], 'message' => $r['msg']]);
}
