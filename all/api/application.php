<?php
/**
 * application.php
 * ────────────────────────────────────────────────────────────────
 * Teacher Portal — Course Application, Enrollment Management,
 *                  Advisor View & Teaching Overview
 * ────────────────────────────────────────────────────────────────
 * All stored procedures called here are defined in
 * api/application_procedures.sql and use ONLY existing tables.
 *
 * Actions:
 *   Course / Teaching Assignment
 *     get_courses_to_apply    GET  – available courses with flags
 *     apply_to_teach          POST – create section + join teaching
 *     get_teaching_overview   GET  – CTE + window-fn summary
 *     remove_teaching         POST – delete from teaching (safe)
 *     update_section_info     POST – update capacity / enroll dates
 *
 *   Enrollment Management (takes table)
 *     get_pending_enrollments GET  – pending students across sections
 *     approve_enrollment      POST – pending → enrolled
 *     reject_enrollment       POST – pending → dropped
 *     get_enrollment_stats    GET  – CASE/WHEN aggregate per section
 *
 *   Advisor
 *     get_advisor_students    GET  – advisees with GPA + window rank
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

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        // ── Course / Teaching ───────────────────────────────────
        'get_courses_to_apply'  => action_get_courses_to_apply($pdo, $TEACHER_ID),
        'apply_to_teach'        => action_apply_to_teach($pdo, $TEACHER_ID),
        'get_teaching_overview' => action_get_teaching_overview($pdo, $TEACHER_ID),
        'remove_teaching'       => action_remove_teaching($pdo, $TEACHER_ID),
        'update_section_info'   => action_update_section_info($pdo, $TEACHER_ID),
        // ── Enrollment ─────────────────────────────────────────
        'get_pending_enrollments' => action_get_pending_enrollments($pdo, $TEACHER_ID),
        'approve_enrollment'    => action_approve_enrollment($pdo, $TEACHER_ID),
        'reject_enrollment'     => action_reject_enrollment($pdo, $TEACHER_ID),
        'get_enrollment_stats'  => action_get_enrollment_stats($pdo, $TEACHER_ID),
        // ── Advisor ────────────────────────────────────────────
        'get_advisor_students'  => action_get_advisor_students($pdo, $TEACHER_ID),
        default                 => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    json_err('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}

// ═══════════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════════

/** Decode JSON body (falls back to $_POST). */
function body(): array {
    static $b = null;
    if ($b === null) {
        $b = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    }
    return $b;
}

/**
 * Call a stored procedure that returns OUT params via @user-variables.
 * $outVars: associative array  alias => '@var_name'
 * Returns the fetched row.
 */
function call_out(PDO $pdo, string $sql, array $inParams, array $outVars): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($inParams);
    // Consume any result sets produced before the OUT-param SELECT
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $selSql = 'SELECT ' . implode(', ', array_map(
        fn($alias, $var) => "$var AS `$alias`",
        array_keys($outVars),
        array_values($outVars)
    ));
    return $pdo->query($selSql)->fetch(PDO::FETCH_ASSOC);
}

// ═══════════════════════════════════════════════════════════════
// COURSE / TEACHING ACTIONS
// ═══════════════════════════════════════════════════════════════

/**
 * GET /api/application.php?action=get_courses_to_apply
 * Returns all courses with already_teaching_now / existing_section_id flags.
 */
function action_get_courses_to_apply(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_courses_to_apply(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * POST /api/application.php?action=apply_to_teach
 * Body: { course_id, semester, year, capacity?, enrollment_start?, enrollment_end? }
 *
 * Finds or creates a section for (course, semester, year), then assigns
 * the teacher to it via the teaching table. Auto-approved immediately.
 */
function action_apply_to_teach(PDO $pdo, int $tid): void {
    $b = body();

    $course_id     = (int)($b['course_id']     ?? 0);
    $semester      = trim($b['semester']        ?? '');
    $year          = (int)($b['year']           ?? 0);
    $capacity      = isset($b['capacity'])      ? (int)$b['capacity']      : null;
    $enroll_start  = trim($b['enrollment_start'] ?? '') ?: null;
    $enroll_end    = trim($b['enrollment_end']   ?? '') ?: null;

    if (!$course_id)                             json_err('course_id is required.');
    if (!in_array($semester, ['Spring','Fall'])) json_err('semester must be Spring or Fall.');
    if ($year < 2020 || $year > 2035)            json_err('year must be 2020–2035.');
    if ($capacity !== null && $capacity < 1)     json_err('capacity must be ≥ 1.');

    $stmt = $pdo->prepare(
        'CALL sp_apply_to_teach(?,?,?,?,?,?,?, @sid,@ok,@msg)'
    );
    $stmt->execute([$tid, $course_id, $semester, $year,
                    $capacity, $enroll_start, $enroll_end]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query(
        'SELECT @sid AS section_id, @ok AS success, @msg AS message'
    )->fetch(PDO::FETCH_ASSOC);

    if (!(int)$r['success']) json_err($r['message']);
    json_ok(['section_id' => (int)$r['section_id'], 'message' => $r['message']]);
}

/**
 * GET /api/application.php?action=get_teaching_overview
 * CTE + window functions: enrollment_rank, fill_pct, total_students_all, etc.
 */
function action_get_teaching_overview(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teaching_overview(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * POST /api/application.php?action=remove_teaching
 * Body: { section_id }
 * Removes teacher from the section (blocked if exam records exist).
 */
function action_remove_teaching(PDO $pdo, int $tid): void {
    $b          = body();
    $section_id = (int)($b['section_id'] ?? 0);
    if (!$section_id) json_err('section_id is required.');

    $stmt = $pdo->prepare('CALL sp_remove_teaching(?,?, @ok,@msg)');
    $stmt->execute([$tid, $section_id]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS success, @msg AS message')->fetch(PDO::FETCH_ASSOC);
    if (!(int)$r['success']) json_err($r['message']);
    json_ok(['message' => $r['message']]);
}

/**
 * POST /api/application.php?action=update_section_info
 * Body: { section_id, capacity?, enrollment_start?, enrollment_end? }
 */
function action_update_section_info(PDO $pdo, int $tid): void {
    $b = body();
    $section_id    = (int)($b['section_id']       ?? 0);
    $capacity      = isset($b['capacity'])          ? (int)$b['capacity']       : null;
    $enroll_start  = trim($b['enrollment_start']    ?? '') ?: null;
    $enroll_end    = trim($b['enrollment_end']      ?? '') ?: null;

    if (!$section_id) json_err('section_id is required.');

    $stmt = $pdo->prepare('CALL sp_update_section_info(?,?,?,?,?, @ok,@msg)');
    $stmt->execute([$tid, $section_id, $capacity, $enroll_start, $enroll_end]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS success, @msg AS message')->fetch(PDO::FETCH_ASSOC);
    if (!(int)$r['success']) json_err($r['message']);
    json_ok(['message' => $r['message']]);
}

// ═══════════════════════════════════════════════════════════════
// ENROLLMENT MANAGEMENT ACTIONS
// ═══════════════════════════════════════════════════════════════

/**
 * GET /api/application.php?action=get_pending_enrollments
 * All students with takes.status='pending' across teacher's sections.
 * Includes queue_position (ROW_NUMBER OVER PARTITION BY section).
 */
function action_get_pending_enrollments(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_pending_enrollments(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * POST /api/application.php?action=approve_enrollment
 * Body: { student_id, section_id }
 * Changes takes.status pending → enrolled (checks capacity).
 */
function action_approve_enrollment(PDO $pdo, int $tid): void {
    $b          = body();
    $student_id = (int)($b['student_id'] ?? 0);
    $section_id = (int)($b['section_id'] ?? 0);
    if (!$student_id || !$section_id) json_err('student_id and section_id are required.');

    $stmt = $pdo->prepare('CALL sp_approve_enrollment(?,?,?, @ok,@msg)');
    $stmt->execute([$tid, $student_id, $section_id]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS success, @msg AS message')->fetch(PDO::FETCH_ASSOC);
    if (!(int)$r['success']) json_err($r['message']);
    json_ok(['message' => $r['message']]);
}

/**
 * POST /api/application.php?action=reject_enrollment
 * Body: { student_id, section_id }
 * Changes takes.status pending → dropped.
 */
function action_reject_enrollment(PDO $pdo, int $tid): void {
    $b          = body();
    $student_id = (int)($b['student_id'] ?? 0);
    $section_id = (int)($b['section_id'] ?? 0);
    if (!$student_id || !$section_id) json_err('student_id and section_id are required.');

    $stmt = $pdo->prepare('CALL sp_reject_enrollment(?,?,?, @ok,@msg)');
    $stmt->execute([$tid, $student_id, $section_id]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS success, @msg AS message')->fetch(PDO::FETCH_ASSOC);
    if (!(int)$r['success']) json_err($r['message']);
    json_ok(['message' => $r['message']]);
}

/**
 * GET /api/application.php?action=get_enrollment_stats
 * Per-section CASE/WHEN aggregate counts (enrolled / pending / dropped).
 */
function action_get_enrollment_stats(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_enrollment_stats(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

// ═══════════════════════════════════════════════════════════════
// ADVISOR ACTION
// ═══════════════════════════════════════════════════════════════

/**
 * GET /api/application.php?action=get_advisor_students
 * All advisees (advisor table) with GPA + RANK window function.
 */
function action_get_advisor_students(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_advisor_students(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}
