<?php
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/auth.php';
require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/student_data.php';

$uid = student_api_require_login();

$STU_CFG = include __DIR__ . '/config.php';

$GRID_START_H = $STU_CFG['schedule']['grid_start_h'] ?? 8;
$GRID_END_H   = $STU_CFG['schedule']['grid_end_h'] ?? 22;
$ROW_PX       = $STU_CFG['schedule']['row_px'] ?? 72;
$TOTAL_WEEKS  = $STU_CFG['schedule']['total_weeks'] ?? 16;
$SEMESTER_START = $STU_CFG['term']['start_date'] ?? date('Y-m-d', strtotime('monday this week'));

$all_schedules = [];
$courses = [];
$semester_label = '';
$total_credits = 0;
$total_sessions = 0;
$color_map = [];
$grid = [];
for ($d = 1; $d <= 7; $d++) $grid[$d] = [];

if ($pdo !== null) {
    try {
        $rows = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_student_schedule', [$uid])
            : [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            SELECT
                c.course_id,
                c.name AS course_name,
                c.credit,
                sec.section_id,
                sec.semester,
                sec.year,
                ss.schedule_id,
                ss.day_of_week,
                ss.start_time,
                ss.end_time,
                CONCAT(cr.building, '-', cr.room_number) AS location,
                COALESCE(ss.week_start, 1)  AS week_start,
                COALESCE(ss.week_end, 16)   AS week_end,
                t_user.name AS teacher_name,
                tc.title AS teacher_title
            FROM takes tk
            JOIN section sec ON sec.section_id = tk.section_id
            JOIN course c ON c.course_id = sec.course_id
            JOIN schedule ss ON ss.section_id = sec.section_id
            LEFT JOIN classroom cr ON cr.classroom_id = ss.classroom_id
            LEFT JOIN (
                SELECT teaching.section_id, MIN(teaching.teacher_id) AS primary_teacher_id
                FROM teaching
                GROUP BY teaching.section_id
            ) pri_tch ON pri_tch.section_id = sec.section_id
            LEFT JOIN teacher tc ON tc.user_id = pri_tch.primary_teacher_id
            LEFT JOIN user t_user ON t_user.user_id = tc.user_id
            WHERE tk.student_id = ?
            ORDER BY sec.year DESC, sec.semester DESC, ss.day_of_week, ss.start_time
        ");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $by_sem = [];
    foreach ($rows as $r) {
        $key = $r['year'] . '-' . $r['semester'];
        $by_sem[$key][] = $r;
    }
    krsort($by_sem);

    $current_key = array_key_first($by_sem);
    if ($current_key) {
        $all_schedules = $by_sem[$current_key];
        $parts = explode('-', $current_key, 2);
        $sem_year = $parts[0] ?? '';
        $sem_name = $parts[1] ?? '';
        $semester_label = student_term_year_label((int) $sem_year, (string) $sem_name, true);
        $total_sessions = count($all_schedules);
    }

    $seen = [];
    foreach ($all_schedules as $s) {
        if (!isset($seen[$s['course_id']])) {
            $seen[$s['course_id']] = true;
            $total_credits += (float)$s['credit'];

            $teacher_str = trim(($s['teacher_title'] ?? '') . ' ' . ($s['teacher_name'] ?? ''));
            $courses[] = [
                'course_id'   => $s['course_id'],
                'course_name' => $s['course_name'],
                'credit'      => $s['credit'],
                'teacher'     => $teacher_str !== '' ? $teacher_str : '待定',
            ];
        }
    }

    foreach ($all_schedules as &$s) {
        $teacher_str = trim(($s['teacher_title'] ?? '') . ' ' . ($s['teacher_name'] ?? ''));
        $s['teacher'] = $teacher_str !== '' ? $teacher_str : '待定';
    }
    unset($s);

    $ci = 0;
    foreach ($courses as $c) {
        $color_map[$c['course_id']] = $ci % 9;
        $ci++;
    }

    foreach ($all_schedules as $s) {
        $day = (int)$s['day_of_week'];
        if ($day >= 1 && $day <= 7) {
            $s['color_idx'] = $color_map[$s['course_id']] ?? 0;
            $grid[$day][] = $s;
        }
    }
}

$success_msg = '';
$error_msg = '';

if (!empty($_SESSION['flash_success'])) { $success_msg = $_SESSION['flash_success']; }
if (!empty($_SESSION['flash_error']))   { $error_msg   = $_SESSION['flash_error']; }
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

ob_start();
include __DIR__ . '/../../components/alerts.php';
$alerts_html = ob_get_clean();

$alerts_html = student_api_utf8_string($alerts_html);
$courses = student_api_utf8($courses);
$grid = student_api_utf8($grid);
$semester_label = student_api_utf8_string($semester_label);

$out = [
    'ok' => true,
    'data' => [
        'semester_label' => $semester_label,
        'courses'        => $courses,
        'grid'           => $grid,
        'color_map'      => $color_map,
        'total_credits'  => $total_credits,
        'total_sessions' => $total_sessions,
        'grid_start_h'   => $GRID_START_H,
        'grid_end_h'     => $GRID_END_H,
        'row_px'         => $ROW_PX,
        'total_weeks'    => $TOTAL_WEEKS,
        'semester_start' => $SEMESTER_START,
    ],
    'alerts_html'  => $alerts_html,
];

student_api_json_ok($out);
