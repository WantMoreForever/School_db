<?php
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';

$uid = requireStudentLogin();

// 星期标签（中文）
$DAYS_CN  = [1 => '周一', 2 => '周二', 3 => '周三', 4 => '周四', 5 => '周五', 6 => '周六', 7 => '周日'];
$DAYS_EN  = [1 => 'Mon',  2 => 'Tue',  3 => 'Wed',  4 => 'Thu',  5 => 'Fri',  6 => 'Sat',  7 => 'Sun'];

// -----------------------------------------------------------------------
// 时间网格配置
// 如未来引入系统配置表（如 sys_config），可从数据库读取以下两个值：
//   SELECT cfg_value FROM sys_config WHERE cfg_key = 'schedule_grid_start_hour'
//   SELECT cfg_value FROM sys_config WHERE cfg_key = 'schedule_grid_end_hour'
// -----------------------------------------------------------------------
$GRID_START_H = 8;   // 课程表起始小时（08:00）
$GRID_END_H   = 22;  // 课程表结束小时（22:00）
$TOTAL_ROWS   = $GRID_END_H - $GRID_START_H; // 14 行
$ROW_PX       = 72;  // 每行像素高度，与 schedule.css 保持一致
$MINS_PER_ROW = 60;  // 每行代表 60 分钟
// -----------------------------------------------------------------------
// 总周数配置
// 如未来引入系统配置表，可从数据库读取：
//   SELECT cfg_value FROM sys_config WHERE cfg_key = 'semester_total_weeks'
// -----------------------------------------------------------------------
// 注意：此值同步写入 schedule.js 的 TOTAL_WEEKS 常量，修改时需同步更新
$TOTAL_WEEKS = 15;
// 加载学生当前学期已选课程的排课信息
$courses       = [];
$all_schedules = [];
$semester_label = '';
$total_credits  = 0;
$total_sessions = 0;
if ($pdo && !$db_error) {
    // -----------------------------------------------------------------------
    // 核心查询：获取学生已选课程的排课信息，包含教师信息
    //
    // 教师关联路径：
    //   section_id → teaching.section_id → teaching.teacher_id
    //                                     → teacher.user_id
    //                                     → user.user_id → user.name / teacher.title
    //
    // 使用子查询取每个 section 的「第一位」教师（teacher_id 最小），
    // 避免 section 有多位教师时产生重复行。
    // 如未来需要展示所有教师，可将子查询改为 GROUP_CONCAT。
    // -----------------------------------------------------------------------
        $stmt = $pdo->prepare("\n        SELECT\n            c.course_id,\n            c.name                          AS course_name,\n            c.credit,\n            sec.section_id,\n            sec.semester,\n            sec.year,\n            ss.schedule_id,\n            ss.day_of_week,\n            ss.start_time,\n            ss.end_time,\n            ss.location,\n            COALESCE(ss.week_start, 1)      AS week_start,\n            COALESCE(ss.week_end,   16)     AS week_end,\n            u.name                          AS teacher_name,\n            t.title                         AS teacher_title\n        FROM takes tk\n        JOIN section sec\n            ON sec.section_id = tk.section_id\n        JOIN course c\n            ON c.course_id = sec.course_id\n        JOIN schedule ss\n            ON ss.section_id = sec.section_id\n        -- 子查询：每个 section 只取一位主讲教师（teacher_id 最小），防止多教师时行数翻倍\n        LEFT JOIN (\n            SELECT section_id, MIN(teacher_id) AS primary_teacher_id\n            FROM teaching\n            GROUP BY section_id\n        ) pri_tch\n            ON pri_tch.section_id = sec.section_id\n        LEFT JOIN teacher t\n            ON t.user_id = pri_tch.primary_teacher_id\n        LEFT JOIN user u\n            ON u.user_id = t.user_id\n        WHERE tk.student_id = ?\n          AND tk.status = 'enrolled'\n        ORDER BY sec.year DESC, sec.semester DESC, ss.day_of_week, ss.start_time\n    ");
        $stmt->execute([$uid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 按学期分组，取最新学期
    $by_sem = [];
    foreach ($rows as $r) {
        $key = $r['year'] . '-' . $r['semester'];
        $by_sem[$key][] = $r;
    }
    krsort($by_sem);
    $current_key = array_key_first($by_sem);
    if ($current_key) {
        $all_schedules = $by_sem[$current_key];
        [$sem_year, $sem_name] = explode('-', $current_key, 2);
        $sem_cn = $sem_name === 'Spring' ? '春季学期' : '秋季学期';
        $semester_label = $sem_year . ' 年 ' . $sem_cn;
        $total_sessions = count($all_schedules);
    }

    // 统计课程（去重）
    $seen = [];
    foreach ($all_schedules as $s) {
        if (!isset($seen[$s['course_id']])) {
            $seen[$s['course_id']] = true;
            $total_credits += (float)$s['credit'];
            // 拼接教师称谓与姓名；若均为空则显示"待定"
            $teacher_str = trim(($s['teacher_title'] ?? '') . ' ' . ($s['teacher_name'] ?? ''));
            $courses[] = [
                'course_id'   => $s['course_id'],
                'course_name' => $s['course_name'],
                'credit'      => $s['credit'],
                'teacher'     => $teacher_str !== '' ? $teacher_str : '待定',
            ];
        }
    }

    // 同步教师字符串回 all_schedules（供网格渲染使用）
    foreach ($all_schedules as &$s) {
        $teacher_str = trim(($s['teacher_title'] ?? '') . ' ' . ($s['teacher_name'] ?? ''));
        $s['teacher'] = $teacher_str !== '' ? $teacher_str : '待定';
    }
    unset($s);
}

// 为每门课分配颜色索引
$color_map = [];
$ci = 0;
foreach ($courses as $c) {
    $color_map[$c['course_id']] = $ci % 9;
    $ci++;
}
// 构建网格数据：day => [排课条目, ...]
$grid = [];
for ($d = 1; $d <= 7; $d++) $grid[$d] = [];
foreach ($all_schedules as $s) {
    $s['color_idx'] = $color_map[$s['course_id']] ?? 0;
    $grid[$s['day_of_week']][] = $s;
}

// 辅助函数
function timeToMins(string $t): int {
    [$h, $m] = explode(':', $t);
    return (int)$h * 60 + (int)$m;
}
function fmtTime(string $t): string {
    return substr($t, 0, 5);
}
function timeToTopPx(string $t, int $gridStartH, int $rowPx): float {
    $mins = timeToMins($t);
    $gridStartMins = $gridStartH * 60;
    return (($mins - $gridStartMins) / 60) * $rowPx;
}
function durationToPx(string $start, string $end, int $rowPx): float {
    $dur = timeToMins($end) - timeToMins($start);
    return ($dur / 60) * $rowPx - 4;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>课程表 · 学生门户</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/schedule.css">
</head>
<body>

<?php include __DIR__ . '/../components/alerts.php'; ?>
<?php
$activePage = 'schedule';
include __DIR__ . '/../components/student_sidebar.php';
?>
<main class="main">
    <header class="topbar">
        <div class="topbar-left">课程表</div>
        <div class="topbar-right">
            <div class="status-dot" title="在线"></div>
            <div class="topbar-time" id="clock"><?= date('Y-m-d H:i:s') ?></div>
        </div>
    </header>

    <div class="content">

        <?php if (empty($all_schedules)): ?>
        <div class="schedule-wrap">
            <div class="no-schedule">
                <div class="ns-icon">🗓</div>
                <div class="ns-title">暂无课程安排</div>
                <div class="ns-sub">未找到当前学期已选课程的排课信息<br>请先完成选课，或联系管理员确认排课数据</div>
            </div>
        </div>
        <?php else: ?>

        <!-- ===== 学期横幅（只展示当前学期） ===== -->
        <div class="semester-banner fade-up">
            <div class="sb-item">
                <div class="sb-label">当前学期</div>
                <div class="sb-val"><?= htmlspecialchars($semester_label) ?></div>
            </div>
        </div>

        <!-- ===== 工具栏 ===== -->
        <div class="schedule-toolbar fade-up delay-1">
            <div class="view-toggle">
                <button class="toggle-btn active" id="btn-tt" onclick="switchView('timetable')">
                    📅 课程表视图
                </button>
                <button class="toggle-btn" id="btn-ov" onclick="switchView('overview')">
                    📋 课程列表
                </button>
            </div>

            <!-- 周导航（仅课程表视图显示） -->
            <div id="week-controls" style="display:flex;align-items:center;gap:16px;">
                <div class="week-nav">
                    <button class="week-nav-btn" id="btn-prev" onclick="changeWeek(-1)" disabled title="上一周">‹</button>
                    <div class="week-label-box" id="week-label">全部学期</div>
                    <button class="week-nav-btn" id="btn-next" onclick="changeWeek(1)" title="下一周">›</button>
                </div>
                <div class="week-pills" id="week-pills"></div>
            </div>
        </div>

        <!-- ========== 课程表视图 ========== -->
        <div id="view-timetable" class="fade-up delay-2">
            <div class="schedule-wrap">
                <div class="table-scroll">
                    <div class="timetable">

                        <!-- 表头行 -->
                        <div class="th-gutter">
                            <div class="th-gutter-inner">时间</div>
                        </div>
                        <?php for ($d = 1; $d <= 7; $d++): ?>
                        <div class="th-day <?= !empty($grid[$d]) ? 'has-class' : '' ?>">
                            <div class="day-name"><?= $DAYS_CN[$d] ?></div>
                            <div class="day-en"><?= $DAYS_EN[$d] ?></div>
                        </div>
                        <?php endfor; ?>

                        <!-- 时间行（08:00–22:00，每格 1 小时，共 14 行） -->
                        <?php for ($row = 0; $row < $TOTAL_ROWS; $row++):
                            $rowHour     = $GRID_START_H + $row;
                            $nextHour    = $rowHour + 1;
                            $isLast      = ($row === $TOTAL_ROWS - 1);
                            $rowMinStart = $rowHour  * 60;
                            $rowMinEnd   = $nextHour * 60;
                        ?>
                        <div class="slot-time<?= $isLast ? ' last-row' : '' ?>">
                            <span class="st-label"><?= sprintf('%02d:00', $rowHour) ?></span>
                        </div>

                        <?php for ($d = 1; $d <= 7; $d++):
                            $startHere = [];
                            foreach ($grid[$d] as $s) {
                                $sMin = timeToMins($s['start_time']);
                                if ($sMin >= $rowMinStart && $sMin < $rowMinEnd) {
                                    $startHere[] = $s;
                                }
                            }
                        ?>
                        <div class="slot-cell<?= $isLast ? ' last-row' : '' ?>"
                             data-day="<?= $d ?>" data-hour="<?= $rowHour ?>">
                            <?php foreach ($startHere as $s):
                                $topOffset = timeToTopPx($s['start_time'], $GRID_START_H, $ROW_PX) - ($row * $ROW_PX);
                                $heightPx  = durationToPx($s['start_time'], $s['end_time'], $ROW_PX);
                                $colorC    = 'cb-' . $s['color_idx'];
                                $tipData   = json_encode([
                                    'name'      => $s['course_name'],
                                    'room'      => $s['location'] ?? '',
                                    'teacher'   => $s['teacher'],
                                    'time'      => fmtTime($s['start_time']) . ' – ' . fmtTime($s['end_time']),
                                    'weekStart' => (int)$s['week_start'],
                                    'weekEnd'   => (int)$s['week_end'],
                                    'day'       => $DAYS_CN[$s['day_of_week']],
                                    'credit'    => $s['credit'],
                                ], JSON_UNESCAPED_UNICODE);
                            ?>
                            <div class="course-block <?= $colorC ?>"
                                 style="top:<?= round($topOffset, 1) ?>px; height:<?= round($heightPx, 1) ?>px;"
                                 data-week-start="<?= (int)$s['week_start'] ?>"
                                 data-week-end="<?= (int)$s['week_end'] ?>"
                                 data-course='<?= htmlspecialchars($tipData, ENT_QUOTES) ?>'>
                                <div class="cb-name"><?= htmlspecialchars($s['course_name']) ?></div>
                                <div class="cb-time">⏰ <?= fmtTime($s['start_time']) ?>–<?= fmtTime($s['end_time']) ?></div>
                                <?php if ($s['location']): ?>
                                <div class="cb-room">📍 <?= htmlspecialchars($s['location']) ?></div>
                                <?php endif; ?>
                                <div class="cb-weeks-badge" data-weeks-label>
                                    第 <?= (int)$s['week_start'] ?>–<?= (int)$s['week_end'] ?> 周
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endfor; // day ?>

                        <?php endfor; // row ?>

                    </div><!-- /timetable -->
                </div><!-- /table-scroll -->
            </div><!-- /schedule-wrap -->

            <div class="week-note">
                <span class="dot"></span>
                <span id="week-note-text">显示全部学期 — 各课程块标注起止周数</span>
            </div>
        </div><!-- /view-timetable -->

        <!-- ========== 课程列表视图 ========== -->
        <div id="view-overview" style="display:none;" class="fade-up delay-2">
            <div class="overview-list">
            <?php
            $ov_grouped = [];
            foreach ($all_schedules as $s) {
                $ov_grouped[$s['course_id']][] = $s;
            }
            $ov_idx = 0;
            foreach ($ov_grouped as $course_id => $scheds):
                $first  = $scheds[0];
                $colorC = 'cb-' . ($color_map[$course_id] ?? 0);
            ?>
            <div class="ov-item fade-up" style="animation-delay:<?= $ov_idx * 0.06 ?>s">
                <div class="ov-color-dot <?= $colorC ?>">📚</div>
                <div class="ov-body">
                    <div class="ov-name"><?= htmlspecialchars($first['course_name']) ?></div>
                    <div class="ov-tags">
                        <span class="ov-tag">👤 <?= htmlspecialchars($first['teacher']) ?></span>
                        <span class="ov-tag">⭐ <?= $first['credit'] ?> 学分</span>
                        <span class="ov-weeks-tag">
                            📅 第 <?= (int)$first['week_start'] ?>–<?= (int)$first['week_end'] ?> 周
                        </span>
                    </div>
                </div>
                <div class="ov-right">
                    <div class="ov-schedule-rows">
                        <?php foreach ($scheds as $sc): ?>
                        <div class="ov-sched-row">
                            <div class="ov-time-str"><?= fmtTime($sc['start_time']) ?>–<?= fmtTime($sc['end_time']) ?></div>
                            <div class="ov-day-badge <?= $colorC ?>"><?= $DAYS_CN[$sc['day_of_week']] ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="ov-location">📍 <?= htmlspecialchars($first['location'] ?? '待定') ?></div>
                </div>
            </div>
            <?php $ov_idx++; endforeach; ?>
            </div>
        </div><!-- /view-overview -->

        <?php endif; ?>

    </div><!-- /content -->
</main>

<!-- 悬浮提示框 -->
<div class="course-tooltip" id="tooltip">
    <div class="ct-title" id="tt-name"></div>
    <div class="ct-row"   id="tt-day"></div>
    <div class="ct-row"   id="tt-time"></div>
    <div class="ct-row"   id="tt-room"></div>
    <div class="ct-row"   id="tt-teacher"></div>
    <div class="ct-row"   id="tt-weeks"></div>
    <div class="ct-row"   id="tt-credit"></div>
</div>

<?php
// -----------------------------------------------------------------------
// 将 PHP 的 TOTAL_WEEKS 传递给 JS，避免两处硬编码不同步
// 如未来从系统配置表读取，修改此处的 $TOTAL_WEEKS 即可自动同步到前端
// -----------------------------------------------------------------------
?>
<script>
    const TOTAL_WEEKS_FROM_SERVER = <?= (int)$TOTAL_WEEKS ?>;
</script>
<script src="/js/schedule.js"></script>
</body>
</html>