<?php
// ============================================================
// 考试安排页  exam_info.php
// ============================================================
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/grade_helpers.php';

$uid = requireStudentLogin();

$default_year = (int)date('Y');
$default_sem  = (date('n') >= 9) ? 'Fall' : 'Spring';

$filter_sem  = $_GET['semester'] ?? ($default_year . '-' . $default_sem);
$filter_type = $_GET['type']     ?? 'all';

$semesters = [];
$exams     = [];
$stats     = ['total' => 0, 'final' => 0, 'midterm' => 0, 'quiz' => 0, 'upcoming' => 0];

if ($pdo) {
    $st = $pdo->prepare("
        SELECT DISTINCT
            CONCAT(sec.year, '-', sec.semester) AS sem_key,
            sec.year, sec.semester
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        WHERE t.student_id = ? AND t.status = 'enrolled'
        ORDER BY sec.year DESC, sec.semester ASC
    ");
    $st->execute([$uid]);
    $semesters = $st->fetchAll();

    if (!isset($_GET['semester']) && !empty($semesters)) {
        $filter_sem = $semesters[0]['sem_key'];
    }
}

$parts  = explode('-', $filter_sem, 2);
$p_year = isset($parts[0]) ? (int)$parts[0] : $default_year;
$p_sem  = isset($parts[1]) ? $parts[1]      : $default_sem;

if ($pdo) {
    // 只查 exam 表中有日期的记录（INNER JOIN exam，且 exam_date IS NOT NULL）
    $st = $pdo->prepare("
        SELECT
            e.exam_id, e.exam_type, e.exam_date,
            c.name AS course_name, c.credit,
            sec.section_id, sec.semester, sec.year,
            (SELECT ss.location FROM schedule ss
             WHERE ss.section_id = sec.section_id
             ORDER BY ss.day_of_week, ss.start_time LIMIT 1) AS location
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        JOIN course  c   ON c.course_id    = sec.course_id
        JOIN exam e      ON e.student_id   = t.student_id
                        AND e.section_id   = t.section_id
                        AND e.exam_date IS NOT NULL
        WHERE t.student_id = ? AND t.status = 'enrolled'
          AND sec.year = ? AND sec.semester = ?
        ORDER BY e.exam_date ASC, e.exam_type ASC
    ");
    $st->execute([$uid, $p_year, $p_sem]);
    $all_rows = $st->fetchAll();
    $st->closeCursor();

    $today = date('Y-m-d');

    foreach ($all_rows as $row) {
        if ($filter_type !== 'all' && $row['exam_type'] !== $filter_type) continue;
        $exams[] = $row;
    }

    // 统计基于全部有日期的行，不受类型筛选影响
    foreach ($all_rows as $row) {
        $stats['total']++;
        if ($row['exam_type'] === 'final')   $stats['final']++;
        if ($row['exam_type'] === 'midterm') $stats['midterm']++;
        if ($row['exam_type'] === 'quiz')    $stats['quiz']++;
        if ($row['exam_date'] >= $today)     $stats['upcoming']++;
    }
}

$activePage = 'exam_info';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>考试安排 · 学生门户</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/exam_info.css">
</head>
<body>

<?php include __DIR__ . '/../components/alerts.php'; ?>
<?php include __DIR__ . '/../components/student_sidebar.php'; ?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">考试安排</div>
        <div class="topbar-right">
            <div class="status-dot"></div>
            <div class="topbar-time" id="clock"></div>
        </div>
    </header>

    <div class="content">

        <!-- Hero -->
        <div class="page-hero fade-up">
            <div class="ph-icon">📝</div>
            <div class="ph-text">
                <div class="ph-title">考试安排</div>
                <div class="ph-sub">
                    <?= htmlspecialchars($p_year . ' ' . $p_sem) ?> 学期 ·
                    查看本学期已选课程的考试类型、日期与考场信息
                </div>
            </div>
            <div class="ph-stats">
                <div class="ph-stat">
                    <span class="ph-val"><?= $stats['total'] ?></span>
                    <span class="ph-label">本学期考试</span>
                </div>
                <div class="ph-divider"></div>
                <div class="ph-stat">
                    <span class="ph-val"><?= $stats['upcoming'] ?></span>
                    <span class="ph-label">即将到来</span>
                </div>
            </div>
        </div>

        <!-- 统计卡片 -->
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
                <div class="esc-icon">⏰</div>
                <div class="esc-val"><?= $stats['upcoming'] ?></div>
                <div class="esc-label">即将到来</div>
            </div>
        </div>

        <!-- 筛选栏 -->
        <div class="filter-bar fade-up delay-2">
            <div class="filter-group">
                <span class="filter-label">学期</span>
                <div class="filter-tabs">
                    <?php foreach ($semesters as $s): ?>
                    <a class="ftab <?= ($filter_sem === $s['sem_key']) ? 'active' : '' ?>"
                       href="?semester=<?= urlencode($s['sem_key']) ?>&type=<?= urlencode($filter_type) ?>">
                        <?= htmlspecialchars($s['year'] . ' ' . $s['semester']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-group">
                <span class="filter-label">类型</span>
                <div class="filter-tabs">
                    <?php
                    $typeOptions = ['all' => '全部', 'final' => '期末', 'midterm' => '期中', 'quiz' => '测验'];
                    foreach ($typeOptions as $v => $label):
                    ?>
                    <a class="ftab <?= ($filter_type === $v) ? 'active' : '' ?>"
                       href="?semester=<?= urlencode($filter_sem) ?>&type=<?= $v ?>">
                        <?= $label ?>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="filter-count">共 <strong><?= count($exams) ?></strong> 条记录</div>
        </div>

        <!-- 考试列表 -->
        <?php if (empty($exams)): ?>
        <div class="empty-state fade-up delay-3">
            <div class="es-icon">📭</div>
            <div>本学期暂无考试安排记录</div>
        </div>
        <?php else: ?>
        <div class="exam-list fade-up delay-3">
            <?php
            $today = date('Y-m-d');
            foreach ($exams as $ex):
                $dateStr = date('Y年m月d日', strtotime($ex['exam_date']));
                $isPast  = $ex['exam_date'] < $today;
                $isToday = $ex['exam_date'] === $today;
                $isFuture= $ex['exam_date'] > $today;
            ?>
            <div class="exam-card <?= $isPast ? 'past' : ($isToday ? 'today' : '') ?>">

                <!-- 左：类型 badge -->
                <div class="exam-type-col">
                    <span class="badge <?= typeBadge($ex['exam_type']) ?>"><?= typeLabel($ex['exam_type']) ?></span>
                </div>

                <!-- 中：课程名 + 时间 + 地点 -->
                <div class="exam-info">
                    <div class="exam-course"><?= htmlspecialchars($ex['course_name']) ?></div>
                    <div class="exam-meta">
                        <span class="exam-meta-item">📅 <?= $dateStr ?></span>
                        <?php if (!empty($ex['location'])): ?>
                        <span class="exam-meta-item">📍 <?= htmlspecialchars($ex['location']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 右：状态 -->
                <div class="exam-right">
                    <?php if ($isToday): ?>
                        <span class="today-badge">今天考试</span>
                    <?php elseif ($isPast): ?>
                        <span class="past-badge">已完成</span>
                    <?php elseif ($isFuture): ?>
                        <span class="upcoming-badge">即将到来</span>
                    <?php endif; ?>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- 底部提示 -->
        <div class="exam-notice fade-up delay-4">
            <div class="notice-icon">ℹ️</div>
            <div class="notice-text">
                考试安排如有变动，以教务处通知为准。如有疑问请联系教务处：<strong>12345678</strong>
            </div>
        </div>

    </div>
</main>

<script src="/js/clock.js"></script>
</body>
</html>