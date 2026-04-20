<?php
/**
 * Teacher Schedule API
 * Course schedule management: view schedule, weekly timetable, update/add/delete schedule
 * All operations verify teacher authorization
 */

session_start();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$pdo = get_pdo();
$TEACHER_ID = get_teacher_id($pdo);

// ── Route ─────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_schedule'          => action_get_schedule($pdo, $TEACHER_ID),
        'get_teacher_schedule'  => action_get_teacher_schedule($pdo, $TEACHER_ID),
        'get_weekly_schedule'   => action_get_weekly_schedule($pdo, $TEACHER_ID),
        'get_week_range'        => action_get_week_range($pdo, $TEACHER_ID),
        'update_schedule'       => action_update_schedule($pdo, $TEACHER_ID),
        'add_schedule'          => action_add_schedule($pdo, $TEACHER_ID),
        'delete_schedule'       => action_delete_schedule($pdo, $TEACHER_ID),
        'check_conflicts'       => action_check_conflicts($pdo, $TEACHER_ID),
        default                 => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    json_err('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}

// ═══════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * GET /api/schedule.php?action=get_schedule&section_id=X
 * Get all schedules for a specific section
 */
function action_get_schedule(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('Missing section_id');

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $stmt = $pdo->prepare('CALL sp_get_schedule(?)');
    $stmt->execute([$section_id]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/schedule.php?action=get_teacher_schedule
 * Get all schedules for all classes taught by this teacher
 */
function action_get_teacher_schedule(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_schedule(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/schedule.php?action=get_weekly_schedule&week=N
 * Get teacher's timetable for the given week number (1-based).
 * Returns only entries whose week_start ≤ N ≤ week_end.
 */
function action_get_weekly_schedule(PDO $pdo, int $tid): void {
    $week = (int)($_GET['week'] ?? 1);
    if ($week < 1 || $week > 52) json_err('week must be between 1 and 52');

    $stmt = $pdo->prepare('CALL sp_get_teacher_weekly_schedule(?, ?)');
    $stmt->execute([$tid, $week]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/schedule.php?action=get_week_range
 * Returns min_week and max_week across all the teacher's schedule entries.
 * Used by the frontend to populate the week selector.
 */
function action_get_week_range(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_week_range(?)');
    $stmt->execute([$tid]);
    $row = $stmt->fetch();

    if (!$row) {
        json_ok(['min_week' => 1, 'max_week' => 16]);
    } else {
        json_ok([
            'min_week' => (int)$row['min_week'],
            'max_week' => (int)$row['max_week'],
        ]);
    }
}

/**
 * POST /api/schedule.php?action=update_schedule
 * Body: schedule_id, day_of_week, start_time, end_time, location
 */
function action_update_schedule(PDO $pdo, int $tid): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $schedule_id = (int)($body['schedule_id'] ?? 0);
    $day_of_week = (int)($body['day_of_week'] ?? 0);
    $start_time  = trim($body['start_time'] ?? '');
    $end_time    = trim($body['end_time'] ?? '');
    $location    = trim($body['location'] ?? '');

    if (!$schedule_id || !$day_of_week || !$start_time || !$end_time || !$location) {
        json_err('Missing required fields: schedule_id, day_of_week, start_time, end_time, location');
    }

    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $start_time) ||
        !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $end_time)) {
        json_err('Invalid time format. Use HH:mm or HH:mm:ss');
    }

    if ($day_of_week < 1 || $day_of_week > 7) {
        json_err('Day of week must be between 1 (Monday) and 7 (Sunday)');
    }

    $stmt = $pdo->prepare('CALL sp_update_schedule(?, ?, ?, ?, ?, ?, @p_success, @p_message)');
    $stmt->execute([$schedule_id, $day_of_week, $start_time, $end_time, $location, $tid]);
    $stmt->nextRowset();

    $result  = $pdo->query('SELECT @p_success AS success, @p_message AS message')->fetch();
    $success = (bool)$result['success'];
    $message = $result['message'];

    if (!$success) json_err($message, 400);
    json_ok(['message' => $message]);
}

/**
 * POST /api/schedule.php?action=add_schedule
 * Body: section_id, day_of_week, start_time, end_time, location, week_start, week_end
 */
function action_add_schedule(PDO $pdo, int $tid): void {
    $body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $section_id  = (int)($body['section_id'] ?? 0);
    $day_of_week = (int)($body['day_of_week'] ?? 0);
    $start_time  = trim($body['start_time'] ?? '');
    $end_time    = trim($body['end_time'] ?? '');
    $location    = trim($body['location'] ?? '');
    $week_start  = trim($body['week_start'] ?? date('Y-m-d'));
    $week_end    = trim($body['week_end'] ?? date('Y-m-d', strtotime('+16 weeks')));

    if (!$section_id || !$day_of_week || !$start_time || !$end_time || !$location) {
        json_err('Missing required fields: section_id, day_of_week, start_time, end_time, location');
    }

    if (!preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $start_time) ||
        !preg_match('/^\d{1,2}:\d{2}(:\d{2})?$/', $end_time)) {
        json_err('Invalid time format. Use HH:mm or HH:mm:ss');
    }

    if ($day_of_week < 1 || $day_of_week > 7) {
        json_err('Day of week must be between 1 (Monday) and 7 (Sunday)');
    }

    $stmt = $pdo->prepare('CALL sp_add_schedule(?, ?, ?, ?, ?, ?, ?, ?, @p_schedule_id, @p_success, @p_message)');
    $stmt->execute([$section_id, $day_of_week, $start_time, $end_time, $location, $week_start, $week_end, $tid]);
    $stmt->nextRowset();

    $result      = $pdo->query('SELECT @p_schedule_id AS schedule_id, @p_success AS success, @p_message AS message')->fetch();
    $success     = (bool)$result['success'];
    $message     = $result['message'];
    $schedule_id = (int)$result['schedule_id'];

    if (!$success) json_err($message, 400);
    json_ok(['schedule_id' => $schedule_id, 'message' => $message]);
}

/**
 * POST /api/schedule.php?action=delete_schedule
 * Body: schedule_id
 */
function action_delete_schedule(PDO $pdo, int $tid): void {
    $body        = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $schedule_id = (int)($body['schedule_id'] ?? 0);
    if (!$schedule_id) json_err('Missing schedule_id');

    $stmt = $pdo->prepare('CALL sp_delete_schedule(?, ?, @p_success, @p_message)');
    $stmt->execute([$schedule_id, $tid]);
    $stmt->nextRowset();

    $result  = $pdo->query('SELECT @p_success AS success, @p_message AS message')->fetch();
    $success = (bool)$result['success'];
    $message = $result['message'];

    if (!$success) json_err($message, 400);
    json_ok(['message' => $message]);
}

/**
 * GET /api/schedule.php?action=check_conflicts&section_id=X&day_of_week=Y&start_time=Z&end_time=W&location=L
 * Check for schedule conflicts (room and teacher)
 */
function action_check_conflicts(PDO $pdo, int $tid): void {
    $section_id  = (int)($_GET['section_id'] ?? 0);
    $day_of_week = (int)($_GET['day_of_week'] ?? 0);
    $start_time  = trim($_GET['start_time'] ?? '');
    $end_time    = trim($_GET['end_time'] ?? '');
    $location    = trim($_GET['location'] ?? '');

    if (!$section_id || !$day_of_week || !$start_time || !$end_time || !$location) {
        json_err('Missing required fields');
    }

    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    $room_conflict = $pdo->prepare('SELECT fn_check_room_conflict(?, ?, ?, ?, 0) AS conflicts');
    $room_conflict->execute([$location, $day_of_week, $start_time, $end_time]);
    $room_conflicts = (int)$room_conflict->fetch()['conflicts'];

    $teacher_conflict = $pdo->prepare('SELECT fn_check_teacher_conflict(?, ?, ?, ?, 0) AS conflicts');
    $teacher_conflict->execute([$tid, $day_of_week, $start_time, $end_time]);
    $teacher_conflicts = (int)$teacher_conflict->fetch()['conflicts'];

    json_ok([
        'room_conflicts'    => $room_conflicts,
        'teacher_conflicts' => $teacher_conflicts,
        'has_conflicts'     => $room_conflicts > 0 || $teacher_conflicts > 0,
        'messages'          => array_values(array_filter([
            $room_conflicts    > 0 ? 'Room is already reserved at this time' : '',
            $teacher_conflicts > 0 ? 'You have a conflicting schedule at this time' : '',
        ])),
    ]);
}