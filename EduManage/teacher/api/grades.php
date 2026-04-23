<?php
/**
 * Teacher Grades API
 * Score management and grade queries
 * All operations verify teacher authorization
 */
require_once __DIR__ . '/../../components/bootstrap.php';

app_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';


$pdo = get_pdo();
$TEACHER_ID = require_teacher_auth($pdo);

// ── Route ─────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_final_scores'        => action_get_final_scores($pdo, $TEACHER_ID),
        'get_student_final_score' => action_get_student_final_score($pdo, $TEACHER_ID),
        'get_course_avg'          => action_get_course_avg($pdo, $TEACHER_ID),
        'get_student_course_avg'  => action_get_student_course_avg($pdo, $TEACHER_ID),
        'get_grade_distribution'  => action_get_grade_distribution($pdo, $TEACHER_ID),
        'get_exam_comparison'     => action_get_exam_comparison($pdo, $TEACHER_ID),
        'get_student_gpa'         => action_get_student_gpa($pdo, $TEACHER_ID),
        default                   => json_err("Unknown action: $action"),
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
 * GET /api/grades.php?action=get_final_scores&section_id=X
 * Get final exam scores for all students in a section
 * Includes: student info, final score, weighted avg, suggested grade
 */
function action_get_final_scores(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('Missing section_id');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    // Call stored procedure
    $stmt = $pdo->prepare('CALL sp_get_final_scores(?)');
    $stmt->execute([$section_id]);
    $rows = $stmt->fetchAll();

    json_ok($rows);
}

/**
 * GET /api/grades.php?action=get_student_final_score&student_id=X&section_id=Y
 * Get final exam score for a specific student in a section
 */
function action_get_student_final_score(PDO $pdo, int $tid): void {
    $student_id = (int)($_GET['student_id'] ?? 0);
    $section_id = (int)($_GET['section_id'] ?? 0);

    if (!$student_id || !$section_id) {
        json_err('Missing required parameters: student_id, section_id');
    }

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    // Verify student is enrolled in this section
    $enroll = $pdo->prepare('SELECT 1 FROM takes WHERE student_id=? AND section_id=?');
    $enroll->execute([$student_id, $section_id]);
    if (!$enroll->fetch()) json_err('Student is not enrolled in this section', 404);

    // Call stored procedure
    $stmt = $pdo->prepare('CALL sp_get_student_final_score(?, ?)');
    $stmt->execute([$student_id, $section_id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_ok(['message' => 'No final exam score found for this student']);
    } else {
        json_ok($row);
    }
}

/**
 * GET /api/grades.php?action=get_course_avg&section_id=X
 * Get the average final score for a section
 * Includes: student count, score statistics, capacity
 */
function action_get_course_avg(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('Missing section_id');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    // Call stored procedure
    $stmt = $pdo->prepare('CALL sp_get_course_avg_score(?)');
    $stmt->execute([$section_id]);
    $row = $stmt->fetch();

    if (!$row) {
        json_ok(['message' => 'No data available for this section']);
    } else {
        json_ok($row);
    }
}

/**
 * GET /api/grades.php?action=get_student_course_avg&student_id=X
 * Get average final scores for all courses a student is enrolled in
 */
function action_get_student_course_avg(PDO $pdo, int $tid): void {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) json_err('Missing student_id');

    // Call stored procedure (no teacher authorization needed for student query)
    $stmt = $pdo->prepare('CALL sp_get_course_avg_by_student(?)');
    $stmt->execute([$student_id]);
    $rows = $stmt->fetchAll();

    json_ok($rows);
}

/**
 * GET /api/grades.php?action=get_grade_distribution&section_id=X
 * Get grade distribution for a section (by letter grade)
 */
function action_get_grade_distribution(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('Missing section_id');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    // Call stored procedure
    $stmt = $pdo->prepare('CALL sp_get_grade_distribution(?)');
    $stmt->execute([$section_id]);
    $rows = $stmt->fetchAll();

    json_ok($rows);
}

/**
 * GET /api/grades.php?action=get_exam_comparison&section_id=X
 * Compare students' scores across different exam types (final vs midterm vs quiz)
 */
function action_get_exam_comparison(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    if (!$section_id) json_err('Missing section_id');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT 1 FROM teaching WHERE teacher_id=? AND section_id=?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetch()) json_err('Not authorized', 403);

    // Call stored procedure
    $stmt = $pdo->prepare('CALL sp_get_exam_comparison(?)');
    $stmt->execute([$section_id]);
    $rows = $stmt->fetchAll();

    json_ok($rows);
}

/**
 * GET /api/grades.php?action=get_student_gpa&student_id=X
 * Get GPA for a student (weighted by credit hours)
 */
function action_get_student_gpa(PDO $pdo, int $tid): void {
    $student_id = (int)($_GET['student_id'] ?? 0);
    if (!$student_id) json_err('Missing student_id');

    $result = teacher_grades_calculate_student_gpa($pdo, $student_id);

    if ($result['gpa'] === null) {
        json_ok(['gpa' => null, 'message' => 'No grade data available for this student']);
    } else {
        json_ok(['gpa' => (float)$result['gpa']]);
    }
}

function teacher_grades_has_column(PDO $pdo, string $table, string $column): bool {
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }

    $stmt = $pdo->prepare(
        'SELECT 1
         FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND COLUMN_NAME = ?
         LIMIT 1'
    );
    $stmt->execute([$table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();

    return $cache[$key];
}

function teacher_grades_calculate_student_gpa(PDO $pdo, int $studentId): array {
    $statusFilter = teacher_grades_has_column($pdo, 'takes', 'status') ? "AND tk.status = 'enrolled'" : '';

    $stmt = $pdo->prepare(
        "SELECT
            COALESCE(SUM(c.credit), 0) AS total_credits,
            COALESCE(SUM(
                CASE tk.grade
                    WHEN 'A'  THEN c.credit * 4.0
                    WHEN 'A-' THEN c.credit * 3.7
                    WHEN 'B+' THEN c.credit * 3.3
                    WHEN 'B'  THEN c.credit * 3.0
                    WHEN 'B-' THEN c.credit * 2.7
                    WHEN 'C+' THEN c.credit * 2.3
                    WHEN 'C'  THEN c.credit * 2.0
                    WHEN 'C-' THEN c.credit * 1.7
                    WHEN 'D'  THEN c.credit * 1.0
                    WHEN 'F'  THEN c.credit * 0.0
                    ELSE NULL
                END
            ), 0) AS weighted_points
         FROM takes tk
         JOIN section sec ON tk.section_id = sec.section_id
         JOIN course c ON sec.course_id = c.course_id
         WHERE tk.student_id = ?
           AND tk.grade IS NOT NULL
           {$statusFilter}"
    );
    $stmt->execute([$studentId]);
    $row = $stmt->fetch() ?: [];

    $totalCredits = (float)($row['total_credits'] ?? 0);
    $weightedPoints = (float)($row['weighted_points'] ?? 0);

    return [
        'gpa' => $totalCredits > 0 ? round($weightedPoints / $totalCredits, 2) : null,
    ];
}
