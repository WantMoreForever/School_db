<?php
// ============================================================
// 考试相关页  exam_info.php
// ============================================================
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/grade_helpers.php';

$uid = requireStudentLogin();
$filter_type = $_GET['type']     ?? 'all';
$filter_sem  = $_GET['semester'] ?? 'all';
$semesters = [];
$stats     = ['total'=>0, 'final'=>0, 'midterm'=>0, 'quiz'=>0, 'avg'=>null];
$exams     = [];

if ($pdo) {
    $st = $pdo->prepare("CALL sp_get_exam_semesters(?)");
    $st->execute([$uid]);
    $semesters = $st->fetchAll();
     $st->closeCursor();
}

if ($pdo) {
    $st = $pdo->prepare("
        SELECT
            COUNT(*)                                                  AS total,
            SUM(exam_type='final')                                    AS final_cnt,
            SUM(exam_type='midterm')                                  AS midterm_cnt,
            SUM(exam_type='quiz')                                     AS quiz_cnt,
            ROUND(AVG(CASE WHEN score IS NOT NULL THEN score END), 2) AS avg_score
        FROM exam WHERE student_id = ?
    ");
    $st->execute(array($uid));
    $row = $st->fetch();
    if ($row) {
        $stats = array(
            'total'   => (int)$row['total'],
            'final'   => (int)$row['final_cnt'],
            'midterm' => (int)$row['midterm_cnt'],
            'quiz'    => (int)$row['quiz_cnt'],
            'avg'     => $row['avg_score'],
        );
    }
}

if ($pdo) {
    $p_type = ($filter_type !== 'all') ? $filter_type : null;
    $p_year = null;
    $p_sem  = null;
    if ($filter_sem !== 'all') {
        $parts = explode('-', $filter_sem, 2);
        $p_year = isset($parts[0]) ? (int)$parts[0] : null;
        $p_sem  = isset($parts[1]) ? $parts[1] : null;
    }
    $st = $pdo->prepare("CALL sp_get_student_exams(?, ?, ?, ?)");
    $st->execute([$uid, $p_type, $p_year, $p_sem]);
    $exams = $st->fetchAll();
     $st->closeCursor();
}

include __DIR__ . '/../components/alerts.php';
$activePage = 'exam_info';
include __DIR__ . '/../components/student_sidebar.php';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>考试相关 · 学生门户</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/exam_info.css">
</head>
<body>
<main class="main">
    <header class="topbar">
        <div class="topbar-left">考试相关</div>
        <div class="topbar-right">
            <div class="status-dot"></div>
            <div class="topbar-time" id="clock"></div>
        </div>
    </header>

    <div class="content">

        <div class="page-hero fade-up">
            <div class="ph-icon">📝</div>
            <div class="ph-text">
                <div class="ph-title">考试安排与成绩</div>
                <div class="ph-sub">查看已参与的所有考试记录、成绩及教师评分详情</div>
            </div>
            <div class="ph-stats">
                <div class="ph-stat">
                    <span class="ph-val"><?= $stats['total'] ?></span>
                    <span class="ph-label">总场次</span>
                </div>
                <div class="ph-divider"></div>
                <div class="ph-stat">
                    <span class="ph-val"><?= ($stats['avg'] !== null) ? number_format((float)$stats['avg'], 1) : '—' ?></span>
                    <span class="ph-label">平均分</span>
                </div>
            </div>
        </div>

        <div class="exam-stats-row fade-up delay-1">
            <div class="exam-stat-card esc-rose">
                <div class="esc-icon">🎯</div>
                <div class="esc-val"><?= $stats['final'] ?></div>
                <div class="esc-label">期末考试</div>
            </div>
            <div class="exam-stat-card esc-gold">
                <div class="esc-icon">📖</div>
                <div class="esc-val"><?= $stats['midterm'] ?></div>
                <div class="esc-label">期中考试</div>
            </div>
            <div class="exam-stat-card esc-jade">
                <div class="esc-icon">✏️</div>
                <div class="esc-val"><?= $stats['quiz'] ?></div>
                <div class="esc-label">平时测验</div>
            </div>
            <div class="exam-stat-card esc-blue">
                <div class="esc-icon">⭐</div>
                <div class="esc-val"><?= ($stats['avg'] !== null) ? number_format((float)$stats['avg'], 1) : '—' ?></div>
                <div class="esc-label">综合均分</div>
            </div>
        </div>

        <div class="filter-bar fade-up delay-2">
            <div class="filter-group">
                <span class="filter-label">类型</span>
                <div class="filter-tabs">
                    <?php
                    $typeOptions = array('all'=>'全部','final'=>'期末','midterm'=>'期中','quiz'=>'测验');
                    foreach ($typeOptions as $v => $label):
                    ?>
                    <a class="ftab <?= ($filter_type === $v) ? 'active' : '' ?>"
                       href="?type=<?= $v ?>&semester=<?= urlencode($filter_sem) ?>"><?= $label ?></a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-group">
                <span class="filter-label">学期</span>
                <select class="filter-select"
                        onchange="location.href='?type=<?= urlencode($filter_type) ?>&semester='+this.value">
                    <option value="all" <?= ($filter_sem === 'all') ? 'selected' : '' ?>>全部学期</option>
                    <?php foreach ($semesters as $s): ?>
                    <option value="<?= htmlspecialchars($s['sem_key']) ?>"
                            <?= ($filter_sem === $s['sem_key']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($s['year'] . ' ' . $s['semester']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-count">共 <strong><?= count($exams) ?></strong> 条记录</div>
        </div>

        <?php if (empty($exams)): ?>
        <div class="empty-state fade-up delay-3">
            <div class="es-icon">📭</div>
            <div>暂无已公布成绩的考试记录</div>
        </div>
        <?php else: ?>
        <div class="exam-list fade-up delay-3">
            <?php foreach ($exams as $ex):
                $score   = $ex['score'];
                $ring    = scoreRing($score);
                $color   = scoreColor($score);
                $circ    = round(2 * M_PI * 22, 2);
                $offset  = round($circ * (1 - $ring / 100), 2);
                $dateStr = ($ex['exam_date']) ? date('Y年m月d日', strtotime($ex['exam_date'])) : '待定';
                $sv      = (float)$score;
                $teacherTitle = isset($ex['teacher_title']) ? $ex['teacher_title'] : '';
            ?>
            <div class="exam-card" data-type="<?= htmlspecialchars($ex['exam_type']) ?>">

                <div class="exam-score-ring">
                    <svg viewBox="0 0 56 56" class="ring-svg">
                        <circle cx="28" cy="28" r="22" class="ring-bg"/>
                        <circle cx="28" cy="28" r="22" class="ring-fg"
                                style="stroke:<?= $color ?>;stroke-dasharray:<?= $circ ?>;stroke-dashoffset:<?= $offset ?>"/>
                    </svg>
                    <div class="ring-label" style="color:<?= $color ?>">
                        <?= number_format($sv, 1) ?>
                    </div>
                </div>

                <div class="exam-info">
                    <div class="exam-course"><?= htmlspecialchars($ex['course_name']) ?></div>
                    <div class="exam-meta">
                        <span class="badge <?= typeBadge($ex['exam_type']) ?>"><?= typeLabel($ex['exam_type']) ?></span>
                        <span class="exam-meta-item">📅 <?= $dateStr ?></span>
                        <span class="exam-meta-item">📚 <?= htmlspecialchars($ex['year'] . ' · ' . $ex['semester']) ?></span>
                        <span class="exam-meta-item">🎓 <?= htmlspecialchars($ex['credit']) ?> 学分</span>
                    </div>
                    <div class="exam-teacher">
                        <span class="teacher-tag">👨‍🏫 <?= htmlspecialchars($teacherTitle . ' ' . $ex['teacher_name']) ?></span>
                    </div>
                </div>

                <div class="exam-right">
                    <div class="exam-score-big" style="color:<?= $color ?>">
                        <?= number_format($sv, 1) ?><span class="exam-score-unit">分</span>
                    </div>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </div>
</main>

<script src="/js/clock.js"></script>
<script src="/js/exam_info.js"></script>
</body>
</html>