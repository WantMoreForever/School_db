<?php
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/student_data.php';

$uid     = requireStudentLogin();
$student = getStudentBaseInfo($pdo, $uid);

$current_year     = 2026;
$current_semester = 'Spring';

$weekMap = [
    1 => '周一',
    2 => '周二',
    3 => '周三',
    4 => '周四',
    5 => '周五',
    6 => '周六',
    7 => '周日'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action     = $_POST['action'] ?? '';
    $section_id = (int)($_POST['section_id'] ?? 0);

    // ── 选课 ──────────────────────────────────────────────────────────────
    if ($action === 'enroll' && $section_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("
                SELECT sec.section_id,
                       sec.year,
                       sec.semester,
                       sec.capacity,
                       sec.enrollment_start,
                       sec.enrollment_end,
                       COUNT(tk.student_id) AS current_enrolled
                FROM section sec
                LEFT JOIN takes tk
                    ON tk.section_id = sec.section_id
                   AND tk.status = 'enrolled'
                WHERE sec.section_id = ?
                GROUP BY sec.section_id, sec.year, sec.semester, sec.capacity,
                         sec.enrollment_start, sec.enrollment_end
                FOR UPDATE
            ");
            $stmt->execute([$section_id]);
            $section_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$section_info) {
                throw new \Exception("该课程班级不存在。");
            }

            // 校验学期
            if ((int)$section_info['year'] !== (int)$current_year || $section_info['semester'] !== $current_semester) {
                throw new \Exception("只能选择当前学期开放的课程。");
            }

            // ── 【新增】选课时间窗口校验 ──────────────────────────────────
            $now = date('Y-m-d H:i:s');
            if (
                empty($section_info['enrollment_start']) ||
                empty($section_info['enrollment_end'])   ||
                $now < $section_info['enrollment_start'] ||
                $now > $section_info['enrollment_end']
            ) {
                $hint = '';
                if (!empty($section_info['enrollment_start']) && $now < $section_info['enrollment_start']) {
                    $hint = '（选课开放时间：' . date('m-d H:i', strtotime($section_info['enrollment_start'])) . '）';
                } elseif (!empty($section_info['enrollment_end']) && $now > $section_info['enrollment_end']) {
                    $hint = '（选课已于 ' . date('m-d H:i', strtotime($section_info['enrollment_end'])) . ' 截止）';
                }
                throw new \Exception("该课程当前不在选课开放时间内，无法选课。{$hint}");
            }

            // 是否已选
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM takes
                WHERE student_id = ?
                  AND section_id = ?
                  AND status = 'enrolled'
            ");
            $stmt->execute([$uid, $section_id]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new \Exception("您已经选修了该课程，无法重复选课。");
            }

            // 容量检查
            if ((int)$section_info['current_enrolled'] >= (int)$section_info['capacity']) {
                throw new \Exception("抱歉，该课程名额已满。");
            }

            // 时间冲突检查
            $stmt = $pdo->prepare("
                SELECT
                    c_old.name          AS conflict_course_name,
                    sch_new.day_of_week AS conflict_day,
                    sch_new.start_time  AS new_start_time,
                    sch_new.end_time    AS new_end_time,
                    sch_new.week_start  AS new_week_start,
                    sch_new.week_end    AS new_week_end
                FROM section new_sec
                JOIN schedule sch_new
                    ON sch_new.section_id = new_sec.section_id
                JOIN section old_sec
                    ON old_sec.year     = new_sec.year
                   AND old_sec.semester = new_sec.semester
                   AND old_sec.section_id <> new_sec.section_id
                JOIN takes tk
                    ON tk.section_id  = old_sec.section_id
                   AND tk.student_id  = ?
                   AND tk.status      = 'enrolled'
                JOIN schedule sch_old
                    ON sch_old.section_id = old_sec.section_id
                JOIN course c_old
                    ON c_old.course_id = old_sec.course_id
                WHERE new_sec.section_id = ?
                  AND sch_new.day_of_week = sch_old.day_of_week
                  AND sch_new.week_start <= sch_old.week_end
                  AND sch_new.week_end   >= sch_old.week_start
                  AND sch_new.start_time  < sch_old.end_time
                  AND sch_new.end_time    > sch_old.start_time
                LIMIT 1
            ");
            $stmt->execute([$uid, $section_id]);
            $conflict = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($conflict) {
                $dayText = $weekMap[(int)$conflict['conflict_day']] ?? ('星期' . $conflict['conflict_day']);
                throw new \Exception(
                    "选课失败：与已选课程《{$conflict['conflict_course_name']}》时间冲突（{$dayText} "
                    . substr($conflict['new_start_time'], 0, 5) . "–"
                    . substr($conflict['new_end_time'], 0, 5)
                    . "，第{$conflict['new_week_start']}–{$conflict['new_week_end']}周）。"
                );
            }

            // 插入选课记录
            $stmt = $pdo->prepare("
                INSERT INTO takes (student_id, section_id, status, enrolled_at)
                VALUES (?, ?, 'enrolled', NOW())
            ");
            $stmt->execute([$uid, $section_id]);

            $pdo->commit();
            $_SESSION['flash_success'] = "选课成功！";

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = $e->getMessage();
        }

        header("Location: course_select.php");
        exit;
    }

    // ── 退课 ──────────────────────────────────────────────────────────────
    if ($action === 'drop' && $section_id > 0) {
        try {
            $pdo->beginTransaction();

            // ── 【新增】退课时间窗口校验 ──────────────────────────────────
            $stmt = $pdo->prepare("
                SELECT enrollment_start, enrollment_end
                FROM section
                WHERE section_id = ?
            ");
            $stmt->execute([$section_id]);
            $sec_time = $stmt->fetch(PDO::FETCH_ASSOC);

            $now = date('Y-m-d H:i:s');
            if (
                $sec_time &&
                (
                    empty($sec_time['enrollment_start']) ||
                    empty($sec_time['enrollment_end'])   ||
                    $now < $sec_time['enrollment_start'] ||
                    $now > $sec_time['enrollment_end']
                )
            ) {
                throw new \Exception("当前不在选课开放时间内，无法退课。");
            }

            $stmt = $pdo->prepare("
                DELETE t FROM takes t
                JOIN section sec ON sec.section_id = t.section_id
                WHERE t.student_id  = ?
                  AND t.section_id  = ?
                  AND t.status      = 'enrolled'
                  AND sec.year      = ?
                  AND sec.semester  = ?
            ");
            $stmt->execute([$uid, $section_id, $current_year, $current_semester]);

            if ($stmt->rowCount() > 0) {
                $pdo->commit();
                $_SESSION['flash_success'] = "退课成功！";
            } else {
                $pdo->rollBack();
                $_SESSION['flash_error'] = "退课失败：该课程不在当前学期或当前状态不可退。";
            }

        } catch (\Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['flash_error'] = "系统错误：" . $e->getMessage();
        }

        header("Location: course_select.php");
        exit;
    }
}

// ── 查询我的已选课程 ───────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT sec.section_id,
           c.name    AS course_name,
           c.credit,
           u.name    AS teacher_name,
           t.status,
           sec.enrollment_start,
           sec.enrollment_end,
           (NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open
    FROM takes t
    JOIN section sec ON t.section_id = sec.section_id
    JOIN course  c   ON sec.course_id = c.course_id
    LEFT JOIN (
        SELECT section_id, MIN(teacher_id) AS primary_teacher_id
        FROM teaching
        GROUP BY section_id
    ) pri ON pri.section_id = sec.section_id
    LEFT JOIN user u ON u.user_id = pri.primary_teacher_id
    WHERE t.student_id = ?
      AND t.status     = 'enrolled'
      AND sec.year     = ?
      AND sec.semester = ?
    ORDER BY t.enrolled_at DESC
");
$stmt->execute([$uid, $current_year, $current_semester]);
$my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$my_credits = array_sum(array_map(fn($row) => (float)($row['credit'] ?? 0), $my_courses));

// ── 查询我当前学期的排课（用于前端冲突预判）────────────────────────────────
$stmt = $pdo->prepare("
    SELECT sch.day_of_week,
           sch.start_time,
           sch.end_time,
           sch.week_start,
           sch.week_end
    FROM takes t
    JOIN section  sec ON t.section_id  = sec.section_id
    JOIN schedule sch ON sch.section_id = sec.section_id
    WHERE t.student_id = ?
      AND t.status     = 'enrolled'
      AND sec.year     = ?
      AND sec.semester = ?
");
$stmt->execute([$uid, $current_year, $current_semester]);
$mySchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 查询本学期所有开放课程（含选课时间窗口）──────────────────────────────
$stmt = $pdo->prepare("
    SELECT sec.section_id,
           c.name  AS course_name,
           c.credit,
           u.name  AS teacher_name,
           sec.capacity,
           sec.enrollment_start,
           sec.enrollment_end,
           (NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open,
           (
               SELECT COUNT(*)
               FROM takes
               WHERE section_id = sec.section_id
                 AND status = 'enrolled'
           ) AS enrolled_count,
           (
               SELECT COUNT(*)
               FROM takes
               WHERE section_id = sec.section_id
                 AND student_id = ?
                 AND status     = 'enrolled'
           ) AS is_my_course
    FROM section sec
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN (
        SELECT section_id, MIN(teacher_id) AS primary_teacher_id
        FROM teaching
        GROUP BY section_id
    ) pri ON pri.section_id = sec.section_id
    LEFT JOIN user u ON u.user_id = pri.primary_teacher_id
    WHERE sec.year     = ?
      AND sec.semester = ?
    ORDER BY c.course_id ASC, sec.section_id ASC
");
$stmt->execute([$uid, $current_year, $current_semester]);
$available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── 前端冲突预判 ─────────────────────────────────────────────────────────
if (!empty($available_sections)) {
    $sectionIds = array_column($available_sections, 'section_id');

    if (!empty($sectionIds)) {
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));

        $stmt = $pdo->prepare("
            SELECT section_id, day_of_week, start_time, end_time, week_start, week_end
            FROM schedule
            WHERE section_id IN ($placeholders)
            ORDER BY section_id ASC
        ");
        $stmt->execute($sectionIds);
        $allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $scheduleMap = [];
        foreach ($allSchedules as $row) {
            $sid = (int)$row['section_id'];
            $scheduleMap[$sid][] = $row;
        }

        foreach ($available_sections as &$sec) {
            $sid = (int)$sec['section_id'];
            $sec['conflict_flag'] = 0;

            if ((int)$sec['is_my_course'] > 0) continue;
            if (empty($scheduleMap[$sid]) || empty($mySchedules)) continue;

            foreach ($scheduleMap[$sid] as $newSch) {
                foreach ($mySchedules as $oldSch) {
                    $sameDay     = (int)$newSch['day_of_week'] === (int)$oldSch['day_of_week'];
                    $weekOverlap = (int)$newSch['week_start'] <= (int)$oldSch['week_end']
                                && (int)$newSch['week_end']   >= (int)$oldSch['week_start'];
                    $timeOverlap = $newSch['start_time'] < $oldSch['end_time']
                                && $newSch['end_time']   > $oldSch['start_time'];

                    if ($sameDay && $weekOverlap && $timeOverlap) {
                        $sec['conflict_flag'] = 1;
                        break 2;
                    }
                }
            }
        }
        unset($sec);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>选课系统 · <?= $student && isset($student['name']) ? htmlspecialchars($student['name']) : '学生' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/course_select.css">
</head>
<body>

<?php include __DIR__ . '/../components/alerts.php'; ?>

<?php
$activePage = 'course_select';
include __DIR__ . '/../components/student_sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            选课系统
            <span style="font-family:'JetBrains Mono';font-size:13px;font-weight:400;color:var(--ink-muted);margin-left:10px;">
                <?= $current_year ?> · <?= $current_semester === 'Fall' ? '秋季学期' : '春季学期' ?>
            </span>
        </div>
        <div class="topbar-right">
            <div class="status-dot" title="在线"></div>
            <div class="topbar-time" id="clock"><?= date('Y-m-d H:i:s') ?></div>
        </div>
    </header>

    <div class="content">

        <div class="cs-overview-card fade-up">
            <div class="cs-ov-left">
                <div class="cs-ov-title">本学期选课概况</div>
                <div class="cs-ov-desc">
                    <?= $current_year ?> 年 <?= $current_semester === 'Fall' ? '秋季' : '春季' ?>学期
                </div>
            </div>
            <div class="cs-ov-right">
                <div class="cs-stat">
                    <span class="cs-val"><?= count($my_courses) ?></span>
                    <span class="cs-label">已选门数</span>
                </div>
                <div class="cs-divider"></div>
                <div class="cs-stat">
                    <span class="cs-val"><?= number_format($my_credits, 1) ?></span>
                    <span class="cs-label">已选学分</span>
                </div>
            </div>
        </div>

        <div class="bottom-row">

            <div class="cs-card fade-up delay-1">
                <div class="card-header">
                    <div class="card-header-title">📚 可选课程大厅</div>
                    <div style="font-size:12px;color:var(--ink-muted);">共 <?= count($available_sections) ?> 门开放</div>
                </div>
                <div class="table-responsive">
                    <table class="cs-table">
                        <thead>
                            <tr>
                                <th>课程名称</th>
                                <th>授课教师</th>
                                <th>学分</th>
                                <th>余量 / 容量</th>
                                <th>选课时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($available_sections as $sec):
                            $is_full     = (int)$sec['enrolled_count'] >= (int)$sec['capacity'];
                            $is_selected = (int)$sec['is_my_course'] > 0;
                            $is_conflict = !empty($sec['conflict_flag']);
                            $is_open     = (bool)$sec['is_open'];

                            // 生成时间窗口提示文字
                            $now = time();
                            $enroll_start = $sec['enrollment_start'] ? strtotime($sec['enrollment_start']) : null;
                            $enroll_end   = $sec['enrollment_end']   ? strtotime($sec['enrollment_end'])   : null;

                            if (!$enroll_start || !$enroll_end) {
                                $time_hint = '<span style="color:var(--ink-muted);font-size:12px;">未设置</span>';
                            } elseif ($now < $enroll_start) {
                                $time_hint = '<span style="color:#b45309;font-size:12px;">未开放<br>' . date('m-d H:i', $enroll_start) . ' 开始</span>';
                            } elseif ($now > $enroll_end) {
                                $time_hint = '<span style="color:#b42318;font-size:12px;">已截止<br>' . date('m-d H:i', $enroll_end) . '</span>';
                            } else {
                                $time_hint = '<span style="color:#027a48;font-size:12px;">进行中<br>截止 ' . date('m-d H:i', $enroll_end) . '</span>';
                            }
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:500;"><?= htmlspecialchars($sec['course_name'] ?? '') ?></div>
                                <?php if ($is_conflict && !$is_selected): ?>
                                    <div style="font-size:12px;color:#b42318;margin-top:4px;">时间冲突</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($sec['teacher_name'] ?? '待定') ?></td>
                            <td>
                                <span style="font-family:'JetBrains Mono';font-weight:600;">
                                    <?= htmlspecialchars($sec['credit'] ?? '0') ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($is_full): ?>
                                    <span class="cs-badge badge-red">已满员</span>
                                <?php else: ?>
                                    <span class="capacity-num">
                                        <span class="capacity-avail"><?= (int)$sec['capacity'] - (int)$sec['enrolled_count'] ?></span> / <?= (int)$sec['capacity'] ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?= $time_hint ?></td>
                            <td>
                                <?php if ($is_selected): ?>
                                    <span class="cs-badge badge-gray">已选修</span>
                                <?php elseif (!$is_open): ?>
                                    <button class="cs-btn-disabled" disabled>未开放</button>
                                <?php elseif ($is_full): ?>
                                    <button class="cs-btn-disabled" disabled>不可选</button>
                                <?php elseif ($is_conflict): ?>
                                    <button class="cs-btn-disabled" disabled>时间冲突</button>
                                <?php else: ?>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="action" value="enroll">
                                        <input type="hidden" name="section_id" value="<?= (int)$sec['section_id'] ?>">
                                        <button type="submit" class="cs-btn-enroll">选课</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="cs-card fade-up delay-2">
                <div class="card-header">
                    <div class="card-header-title">🎓 我的选课夹</div>
                </div>
                <?php if (empty($my_courses)): ?>
                <div class="empty-state">
                    <div class="es-icon">📭</div>
                    <div style="margin-top:8px;">您还没有选修任何课程</div>
                </div>
                <?php else: ?>
                <div class="my-course-list">
                    <?php foreach ($my_courses as $mc): ?>
                    <div class="my-course-item">
                        <div class="mci-info">
                            <div class="mci-title"><?= htmlspecialchars($mc['course_name'] ?? '') ?></div>
                            <div class="mci-meta">
                                👨‍🏫 <?= htmlspecialchars($mc['teacher_name'] ?? '待定') ?>
                                &nbsp;·&nbsp;
                                ⭐ <?= htmlspecialchars($mc['credit'] ?? '0') ?> 学分
                            </div>
                            <div style="margin-top:6px;">
                                <span class="cs-badge badge-green">已确认</span>
                            </div>
                        </div>
                        <div class="mci-action">
                            <?php if ((bool)$mc['is_open']): ?>
                            <form method="POST">
                                <input type="hidden" name="action" value="drop">
                                <input type="hidden" name="section_id" value="<?= (int)$mc['section_id'] ?>">
                                <button type="submit" class="cs-btn-drop" title="退课"
                                        onclick="return confirm('确认退出《<?= htmlspecialchars(addslashes($mc['course_name'] ?? '')) ?>》吗？')">
                                    <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                            </form>
                            <?php else: ?>
                            <button class="cs-btn-disabled" disabled title="不在选课时间窗口内，无法退课">
                                <svg width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="opacity:0.4;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                </svg>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</main>

<script src="/js/clock.js"></script>
</body>
</html>