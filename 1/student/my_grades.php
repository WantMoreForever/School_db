<?php
// ============================================================
// 成绩查询页  my_grades.php
// ============================================================
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/grade_helpers.php';

$uid = requireStudentLogin();

// ── 筛选 & 排序参数 ─────────────────────────────────────────
$filter_sem = isset($_GET['semester']) ? $_GET['semester'] : 'all';
$sort_by  = isset($_GET['sort'])    ? $_GET['sort']    : 'default';
$sort_dir = isset($_GET['dir'])     ? $_GET['dir']     : 'desc';
if (!in_array($sort_by,  array('default','score','credit','gpa'))) $sort_by  = 'default';
if (!in_array($sort_dir, array('asc','desc')))                      $sort_dir = 'desc';

$semesters = array();
if ($pdo) {
    $st = $pdo->prepare("CALL sp_get_exam_semesters(?)");
    $st->execute([$uid]);
    $semesters = $st->fetchAll();
     $st->closeCursor(); 
}

$semWhere = '';
$semParams = array($uid);
if ($filter_sem !== 'all') {
    $parts    = explode('-', $filter_sem, 2);
    $yr       = $parts[0];
    $sem      = isset($parts[1]) ? $parts[1] : '';
    $semWhere = 'AND sec.year = ? AND sec.semester = ?';
    $semParams[] = $yr;
    $semParams[] = $sem;
}

$grades = array();
if ($pdo) {
    $p_year = null;
    $p_sem  = null;
    if ($filter_sem !== 'all') {
        $parts = explode('-', $filter_sem, 2);
        $p_year = isset($parts[0]) ? (int)$parts[0] : null;
        $p_sem  = isset($parts[1]) ? $parts[1] : null;
    }
    $st = $pdo->prepare("CALL sp_get_student_exams(?, ?, ?, ?)");
    $st->execute([$uid, null, $p_year, $p_sem]);
    $rows = $st->fetchAll();
 $st->closeCursor(); 
    foreach ($rows as $row) {
        $row['gpa_point'] = scoreToGpa($row['score']);
        $grades[] = $row;
    }

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
            $diff = $a['gpa_point'] - $b['gpa_point'];
            return ($dir === 'asc') ? ($diff > 0 ? 1 : ($diff < 0 ? -1 : 0))
                                    : ($diff < 0 ? 1 : ($diff > 0 ? -1 : 0));
        });
    }
}

$total_cx_gpa = 0.0;
$total_credit = 0.0;
foreach ($grades as $g) {
    $total_cx_gpa += (float)$g['credit'] * (float)$g['gpa_point'];
    $total_credit += (float)$g['credit'];
}
$gpa         = ($total_credit > 0) ? round($total_cx_gpa / $total_credit, 2) : 0.00;
$gpaColorVal = gpaColor($gpa);
$arcTotal    = round(2 * M_PI * 50, 2);
$arcOffset   = round($arcTotal * (1 - $gpa / 4.0), 2);

function sortLink($col, $cur_sort, $cur_dir, $cur_sem) {
    $new_dir = ($cur_sort === $col) ? (($cur_dir === 'desc') ? 'asc' : 'desc') : 'desc';
    return '?semester=' . urlencode($cur_sem) . '&sort=' . $col . '&dir=' . $new_dir;
}

function sortIcon($col, $cur_sort, $cur_dir) {
    if ($cur_sort !== $col) return '<span class="sort-icon inactive">↕</span>';
    return ($cur_dir === 'desc')
        ? '<span class="sort-icon active">↓</span>'
        : '<span class="sort-icon active">↑</span>';
}

$activePage = 'my_grades';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>成绩查询 · 学生门户</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/my_grades.css">
</head>
<body>

<?php include __DIR__ . '/../components/student_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">成绩查询</div>
        <div class="topbar-right">
            <div class="status-dot"></div>
            <div class="topbar-time" id="clock"></div>
        </div>
    </header>

    <div class="content">
        <?php include __DIR__ . '/../components/alerts.php'; ?>

        <?php if ($db_error): ?>
        <div class="db-error-banner">⚠ 数据库连接失败：<?= htmlspecialchars($db_error) ?></div>
        <?php endif; ?>

        <div class="gpa-hero fade-up">
            <div class="gpa-circle-wrap">
                <svg class="gpa-arc" viewBox="0 0 120 120">
                    <circle cx="60" cy="60" r="50" class="arc-bg"/>
                    <circle cx="60" cy="60" r="50" class="arc-fg"
                            style="stroke:<?= $gpaColorVal ?>;stroke-dasharray:<?= $arcTotal ?>;stroke-dashoffset:<?= $arcOffset ?>"/>
                </svg>
                <div class="gpa-center">
                    <div class="gpa-val" style="color:<?= $gpaColorVal ?>"><?= number_format($gpa, 2) ?></div>
                    <div class="gpa-max">/ 4.00</div>
                </div>
            </div>
            <div class="gpa-details">
                <div class="gpa-title">综合 GPA
                    <?php if ($filter_sem !== 'all'): ?>
                    <span class="gpa-sem-tag"><?= htmlspecialchars(str_replace('-', ' ', $filter_sem)) ?></span>
                    <?php else: ?>
                    <span class="gpa-sem-tag">全部学期</span>
                    <?php endif; ?>
                </div>
                <div class="gpa-sub">加权 GPA = Σ(学分 × 绩点) ÷ 总学分 · 随学期筛选联动</div>
                <div class="gpa-tags">
                    <div class="gpa-tag">
                        <span class="gt-val"><?= number_format($total_credit, 1) ?></span>
                        <span class="gt-label">统计学分</span>
                    </div>
                    <div class="gpa-tag">
                        <span class="gt-val"><?= count($grades) ?></span>
                        <span class="gt-label">成绩条数</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grades-filter-bar fade-up delay-1">
            <span class="filter-label">学期</span>
            <div class="filter-tabs">
                <a class="ftab <?= ($filter_sem === 'all') ? 'active' : '' ?>"
                   href="?semester=all&sort=<?= urlencode($sort_by) ?>&dir=<?= urlencode($sort_dir) ?>">全部</a>
                <?php foreach ($semesters as $s): ?>
                <a class="ftab <?= ($filter_sem === $s['sem_key']) ? 'active' : '' ?>"
                   href="?semester=<?= urlencode($s['sem_key']) ?>&sort=<?= urlencode($sort_by) ?>&dir=<?= urlencode($sort_dir) ?>">
                    <?= htmlspecialchars($s['year'] . ' ' . $s['semester']) ?>
                </a>
                <?php endforeach; ?>
            </div>
            <div class="filter-count">共 <strong><?= count($grades) ?></strong> 条</div>
            <?php if ($sort_by !== 'default'): ?>
            <a class="reset-sort-btn" href="?semester=<?= urlencode($filter_sem) ?>&sort=default&dir=desc">
                ✕ 清除排序
            </a>
            <?php endif; ?>
        </div>

        <?php if (empty($grades)): ?>
        <div class="empty-state fade-up delay-2">
            <div class="es-icon">📭</div>
            <div>暂无已公布的成绩记录</div>
        </div>
        <?php else: ?>

        <div class="grades-table-header fade-up delay-2">
            <div>类型</div>
            <div>课程名称</div>
            <div class="col-center">
                <a class="sort-th-link" href="<?= sortLink('credit', $sort_by, $sort_dir, $filter_sem) ?>">
                    学分<?= sortIcon('credit', $sort_by, $sort_dir) ?>
                </a>
            </div>
            <div class="col-center">
                <a class="sort-th-link" href="<?= sortLink('score', $sort_by, $sort_dir, $filter_sem) ?>">
                    成绩<?= sortIcon('score', $sort_by, $sort_dir) ?>
                </a>
            </div>
            <div class="col-center">
                <a class="sort-th-link" href="<?= sortLink('gpa', $sort_by, $sort_dir, $filter_sem) ?>">
                    绩点<?= sortIcon('gpa', $sort_by, $sort_dir) ?>
                </a>
            </div>
        </div>

        <div class="grades-list fade-up delay-3">
            <?php foreach ($grades as $g):
                $gp      = (float)$g['gpa_point'];
                $sc      = (float)$g['score'];
                $gpColor = gpaColor($gp);
            ?>
            <div class="grade-row">
                <div>
                    <span class="badge <?= typeBadge($g['exam_type']) ?>"><?= typeLabel($g['exam_type']) ?></span>
                </div>
                <div class="gr-course">
                    <div class="gr-course-name"><?= htmlspecialchars($g['course_name']) ?></div>
                    <div class="gr-sem"><?= htmlspecialchars($g['year'] . ' · ' . $g['semester']) ?></div>
                </div>
                <div class="col-center">
                    <span class="credit-chip"><?= htmlspecialchars($g['credit']) ?></span>
                </div>
                <div class="col-center">
                    <span class="score-chip" style="color:<?= $scColor ?>;border-color:<?= $scColor ?>44;background:<?= $scColor ?>18">
                        <?= number_format($sc, 1) ?>
                    </span>
                </div>
                <div class="col-center">
                    <span class="gpa-chip" style="color:<?= $gpColor ?>;border-color:<?= $gpColor ?>44;background:<?= $gpColor ?>18">
                        <?= number_format($gp, 1) ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="gpa-summary-bar fade-up delay-4">
            <span>
                共 <strong><?= count($grades) ?></strong> 条 ·
                加权 GPA <strong style="color:<?= $gpaColorVal ?>"><?= number_format($gpa, 2) ?></strong>
            </span>
            <span class="gpa-note">复核成绩可联系教务处电话：12345678</span>
        </div>

        <?php endif; ?>
    </div>
</main>

<script src="/js/clock.js"></script>
</body>
</html>