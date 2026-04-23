<?php
// API: student/api/student_portal.php
// 返回学生门户所需的 JSON 数据（供前端 student_portal.js 使用）

ini_set('display_errors', '0');

require_once __DIR__ . '/helpers.php';

register_shutdown_function(function () {
    $err = error_get_last();
    if (!$err) return;

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array($err['type'], $fatalTypes, true)) return;

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    if (!headers_sent()) {
        http_response_code(500);
        app_send_json_header();
    }

    echo json_encode([
        'ok' => false,
        'success' => false,
        'code' => app_api_error_code_for_status(500),
        'error' => 'fatal_error',
        'message' => '首页接口致命错误：' . ($err['message'] ?? 'unknown'),
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
});

student_api_bootstrap();
student_api_no_cache();

require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/student_data.php';
require_once __DIR__ . '/../../components/grade_helpers.php';

// load student area config
$STU_CFG = include __DIR__ . '/config.php';

$uid = student_api_require_login();

$student = null;
$stats = ['gpa' => 0.0, 'credits' => 0.0, 'published' => 0];
$recent_grades = [];
$enrolled_count = 0;
$enroll_open = false;
$enroll_end = '';
$alerts_html = '';
$year_min = (int)date('Y');

try {
    if ($pdo !== null) {
        $student = getStudentBaseInfo($pdo, (int)$uid);
        $stats = calcStudentStats($pdo, (int)$uid);

        $recent_grades_limit = 8;
        $recent_grades_year_range = 2;
        $current_year = (int)date('Y');
        $year_min = $current_year - ($recent_grades_year_range - 1);

        try {
            $sets = function_exists('app_call_multi_result_rows')
                ? app_call_multi_result_rows($pdo, 'sp_project_get_student_portal_summary', [(int)$uid, $year_min, $recent_grades_limit])
                : [];
            $enrolled_count = (int)($sets[0][0]['enrolled_count'] ?? 0);
            $recent_grades = $sets[1] ?? [];
            $active_max_end = $sets[2][0]['enroll_end'] ?? null;
        } catch (Throwable $e) {
            $st = $pdo->prepare("SELECT COUNT(*) FROM takes WHERE student_id = ?");
            $st->execute([(int)$uid]);
            $enrolled_count = (int)$st->fetchColumn();

            $st = $pdo->prepare("SELECT c.name AS course, c.credit,
                       CONCAT(sec.year, '-', sec.semester) AS semester,
                       sec.year AS sec_year,
                       MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) AS final_score
                FROM takes t
                JOIN section sec ON sec.section_id = t.section_id
                JOIN course c    ON c.course_id    = sec.course_id
                LEFT JOIN exam e ON e.student_id  = t.student_id
                                 AND e.section_id = t.section_id
                WHERE t.student_id = ?
                  AND sec.year    >= ?
                GROUP BY t.section_id, c.name, c.credit, sec.year, sec.semester, t.enrolled_at
                HAVING MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) IS NOT NULL
                ORDER BY sec.year DESC, t.enrolled_at DESC
                LIMIT ?");
            $st->execute([(int)$uid, $year_min, $recent_grades_limit]);
            $recent_grades = $st->fetchAll(PDO::FETCH_ASSOC);

            $st = $pdo->prepare("SELECT MAX(enrollment_end) FROM section WHERE NOW() BETWEEN enrollment_start AND enrollment_end");
            $st->execute();
            $active_max_end = $st->fetchColumn();
        }

        if ($active_max_end) {
            $enroll_open = true;
            $enroll_end = $active_max_end;
        }
    }

    $activePage = 'student_portal';
    ob_start();
    include __DIR__ . '/../../components/alerts.php';
    $alerts_html = ob_get_clean();
} catch (Throwable $e) {
    error_log('student_portal API error: ' . $e->getMessage());
    student_api_json_error('首页接口异常：' . $e->getMessage(), 500, ['error' => 'server_error']);
}

$student = student_api_utf8($student);
$stats = student_api_utf8($stats);
$recent_grades = student_api_utf8($recent_grades);
$alerts_html = student_api_utf8_string($alerts_html ?? '');

$result = [
    'success' => true,
    'student' => $student,
    'stats' => $stats,
    'recent_grades' => $recent_grades,
    'enrolled_count' => $enrolled_count,
    'enroll_open' => $enroll_open,
    'year_min' => $year_min,
    'enroll_end' => $enroll_end,
    'alerts_html' => $alerts_html,
];

student_api_json_ok($result);
