<?php
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/auth.php';
require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/grade_helpers.php';

$uid = student_api_require_login();
$STU_CFG = include __DIR__ . '/config.php';
$default_year = (int) ($STU_CFG['term']['current_year'] ?? date('Y'));
$default_sem  = (string) ($STU_CFG['term']['current_semester'] ?? student_term_default_semester());

$filter_sem  = $_GET['semester'] ?? 'all';
$filter_type = $_GET['type']     ?? 'all';
$p_year = null;
$p_sem = null;

$semesters = [];
$exams     = [];
$stats     = ['total' => 0, 'final' => 0, 'midterm' => 0, 'quiz' => 0, 'upcoming' => 0];

if ($pdo !== null) {
    $st = $pdo->prepare("SELECT DISTINCT
            CONCAT(sec.year, '-', sec.semester) AS sem_key,
            sec.year, sec.semester
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        WHERE t.student_id = ?
        ORDER BY sec.year DESC, sec.semester ASC");
    $st->execute([$uid]);
    $semesters = $st->fetchAll(PDO::FETCH_ASSOC);

    // 支持传入 semester=all 表示不按学期过滤（显示全部学期）
    $is_all_sem = ($filter_sem === 'all');

    if ($is_all_sem) {
        $p_year = null;
        $p_sem  = null;
        $st = $pdo->prepare("SELECT
            e.exam_id, e.exam_type, e.exam_date,
            c.name AS course_name, c.credit,
            sec.section_id, sec.semester, sec.year,
            (SELECT CONCAT(cr.building, '-', cr.room_number) FROM schedule ss LEFT JOIN classroom cr ON cr.classroom_id = ss.classroom_id
             WHERE ss.section_id = sec.section_id
             ORDER BY ss.day_of_week, ss.start_time LIMIT 1) AS location
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        JOIN course  c   ON c.course_id    = sec.course_id
        JOIN exam e      ON e.student_id   = t.student_id
                        AND e.section_id   = t.section_id
        WHERE t.student_id = ?
        ORDER BY e.exam_date ASC, e.exam_type ASC");
        $st->execute([$uid]);
    } else {
        $parts  = explode('-', $filter_sem, 2);
        $p_year = isset($parts[0]) ? (int)$parts[0] : $default_year;
        $p_sem  = isset($parts[1]) ? $parts[1]      : $default_sem;

        $st = $pdo->prepare("SELECT
            e.exam_id, e.exam_type, e.exam_date,
            c.name AS course_name, c.credit,
            sec.section_id, sec.semester, sec.year,
            (SELECT CONCAT(cr.building, '-', cr.room_number) FROM schedule ss LEFT JOIN classroom cr ON cr.classroom_id = ss.classroom_id
             WHERE ss.section_id = sec.section_id
             ORDER BY ss.day_of_week, ss.start_time LIMIT 1) AS location
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        JOIN course  c   ON c.course_id    = sec.course_id
        JOIN exam e      ON e.student_id   = t.student_id
                        AND e.section_id   = t.section_id
        WHERE t.student_id = ?
          AND sec.year = ? AND sec.semester = ?
        ORDER BY e.exam_date ASC, e.exam_type ASC");
        $st->execute([$uid, $p_year, $p_sem]);
    }
    $all_rows = $st->fetchAll(PDO::FETCH_ASSOC);
    $st->closeCursor();

    $today = date('Y-m-d');

    foreach ($all_rows as $row) {
        if ($filter_type !== 'all' && $row['exam_type'] !== $filter_type) continue;
        $exams[] = $row;
    }

    foreach ($all_rows as $row) {
        $stats['total']++;
        if ($row['exam_type'] === 'final')   $stats['final']++;
        if ($row['exam_type'] === 'midterm') $stats['midterm']++;
        if ($row['exam_type'] === 'quiz')    $stats['quiz']++;
        if ($row['exam_date'] && $row['exam_date'] >= $today) $stats['upcoming']++;
    }
}

$success_msg = $_SESSION['flash_success'] ?? '';
$error_msg   = $_SESSION['flash_error'] ?? '';

ob_start();
include __DIR__ . '/../../components/alerts.php';
$alerts_html = ob_get_clean();

$alerts_html  = student_api_utf8($alerts_html);

unset($_SESSION['flash_success'], $_SESSION['flash_error']);

$data = [
    'semesters' => $semesters,
    'filter_sem' => $filter_sem,
    'filter_type' => $filter_type,
    'p_year' => $p_year,
    'p_sem' => $p_sem,
    'exams' => $exams,
    'stats' => $stats,
];

$data = student_api_utf8($data);

student_api_json_ok(['ok' => true, 'alerts_html' => $alerts_html, 'data' => $data]);
