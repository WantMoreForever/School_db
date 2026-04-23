<?php
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/auth.php';
require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/grade_helpers.php';

$uid = student_api_require_login();

$filter_sem = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$sort_by    = isset($_GET['sort'])    ? $_GET['sort']    : 'default';
$sort_dir   = isset($_GET['dir'])     ? $_GET['dir']     : 'desc';
if (!in_array($sort_by,  array('default','score','credit','gpa'))) $sort_by  = 'default';
if (!in_array($sort_dir, array('asc','desc')))                      $sort_dir = 'desc';

$semesters = array();
$grades = array();
$total_credit = 0.0;
$final_count = 0;
$gpa = 0.0;
$gpaColorVal = '#94a3b8';

// load student config for visualization defaults
$STU_CFG = include __DIR__ . '/config.php';
$arcRadius = $STU_CFG['gpa']['arc_radius'] ?? 50;
$arcTotal = round(2 * M_PI * $arcRadius, 2);
$arcOffset = 0;

if ($pdo !== null) {
    // semesters
    $st = $pdo->prepare("CALL sp_get_exam_semesters(?)");
    $st->execute([$uid]);
    $semesters = $st->fetchAll(PDO::FETCH_ASSOC);
    while ($st->nextRowset()) {} // 替换为清除所有行集，防止 'Packets out of order'

    // determine year/sem filter
    $p_year = null; $p_sem = null;
    if ($filter_sem !== 'all') {
        $parts = explode('-', $filter_sem, 2);
        $p_year = isset($parts[0]) ? (int)$parts[0] : null;
        $p_sem  = isset($parts[1]) ? $parts[1] : null;
    }

    // fetch exams
    $st = $pdo->prepare("CALL sp_get_student_exams(?, ?, ?, ?)");
    $st->execute([$uid, null, $p_year, $p_sem]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    while ($st->nextRowset()) {} // 替换为清除所有行集，防止 'Packets out of order'

    foreach ($rows as $row) {
        // 仅包含期末成绩（前端只展示期末）
        if ($row['exam_type'] !== 'final') continue;
        $row['gpa_point'] = scoreToGpa($row['score']);
        $grades[] = $row;
    }

    // sorting (match my_grades.php)
    if ($sort_by === 'score') {
        $dir = $sort_dir;
        usort($grades, function($a, $b) use ($dir) {
            $diff = (float)$a['score'] - (float)$b['score'];
            return ($dir === 'asc') ? ($diff > 0 ? 1 : ($diff < 0 ? -1 : 0))
                                    : ($diff < 0 ? 1 : ($diff > 0 ? -1 : 0));
        });
    } elseif ($sort_by === 'credit') {
        $dir = $sort_dir;
        usort($grades, function($a, $b) use ($dir) {
            $diff = (float)$a['credit'] - (float)$b['credit'];
            return ($dir === 'asc') ? ($diff > 0 ? 1 : ($diff < 0 ? -1 : 0))
                                    : ($diff < 0 ? 1 : ($diff > 0 ? -1 : 0));
        });
    } elseif ($sort_by === 'gpa') {
        $dir = $sort_dir;
        usort($grades, function($a, $b) use ($dir) {
            $av = ($a['gpa_point'] !== null) ? (float)$a['gpa_point'] : -1;
            $bv = ($b['gpa_point'] !== null) ? (float)$b['gpa_point'] : -1;
            $diff = $av - $bv;
            return ($dir === 'asc') ? ($diff > 0 ? 1 : ($diff < 0 ? -1 : 0))
                                    : ($diff < 0 ? 1 : ($diff > 0 ? -1 : 0));
        });
    }

    // compute GPA summary (only final)
    $total_cx_gpa = 0.0; $total_credit = 0.0; $final_count = 0;
    foreach ($grades as $g) {
        if ($g['exam_type'] === 'final' && $g['gpa_point'] !== null) {
            $total_cx_gpa += (float)$g['credit'] * (float)$g['gpa_point'];
            $total_credit += (float)$g['credit'];
            $final_count++;
        }
    }
    $gpa = ($total_credit > 0) ? round($total_cx_gpa / $total_credit, 2) : 0.00;
    // clamp GPA to visual range to avoid unexpected offsets
    $gpa = max(0.0, min(4.0, (float)$gpa));
    $gpaColorVal = gpaColor($gpa);
    // ensure arcTotal uses configured arc radius so front-end stroke lengths match
    $arcTotal = round(2 * M_PI * $arcRadius, 2);
    $arcOffset = round($arcTotal * (1 - $gpa / 4.0), 2);
}

// capture alerts using components
ob_start();
include __DIR__ . '/../../components/alerts.php';
$alerts_html = ob_get_clean();

$alerts_html  = student_api_utf8_string($alerts_html);

// Ensure data arrays/strings are UTF-8 to avoid front-end JSON decoding issues
$semesters = student_api_utf8($semesters);
$grades    = student_api_utf8($grades);
$filter_sem = student_api_utf8_string($filter_sem);

$out = [
    'ok' => true,
    'data' => [
        'semesters' => $semesters,
        'grades'    => $grades,
        'gpa'       => $gpa,
        'gpaColorVal'=> $gpaColorVal,
        'arcTotal'  => $arcTotal,
        'arcOffset' => $arcOffset,
        'total_credit' => $total_credit,
        'final_count'  => $final_count,
        'grades_count' => count($grades),
        'filter_sem'   => $filter_sem,
        'sort_by'      => $sort_by,
        'sort_dir'     => $sort_dir,
    ],
    'alerts_html'  => $alerts_html,
];

student_api_json_ok($out);
