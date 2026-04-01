<?php
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/student_data.php';
require_once __DIR__ . '/../components/grade_helpers.php';

$uid = requireStudentLogin();

$student       = null;
$stats         = ['enrolled' => 0, 'published' => 0, 'gpa' => 0.00, 'credits' => 0];
$recent_grades = [];
$enroll_open   = false;

if ($pdo && !$db_error) {
    $student = getStudentBaseInfo($pdo, $uid);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM takes WHERE student_id = ? AND status = 'enrolled'");
    $stmt->execute([$uid]);
    $stats['enrolled'] = (int)$stmt->fetchColumn();

    $grade_stats        = calcStudentStats($pdo, $uid);
    $stats['gpa']       = $grade_stats['gpa'];
    $stats['credits']   = $grade_stats['credits'];
    $stats['published'] = $grade_stats['published'];

    $stmt = $pdo->prepare("
        SELECT c.name AS course, c.credit,
               CONCAT(sec.year, '-', sec.semester) AS semester,
               MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) AS final_score,
               (MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) IS NOT NULL) AS is_published
        FROM takes t
        JOIN section sec ON sec.section_id = t.section_id
        JOIN course c ON c.course_id = sec.course_id
        LEFT JOIN exam e ON e.student_id = t.student_id
                         AND e.section_id = t.section_id
        WHERE t.student_id = ? AND t.status = 'enrolled'
        GROUP BY t.section_id, c.name, c.credit, sec.year, sec.semester, t.enrolled_at
        ORDER BY t.enrolled_at DESC
        LIMIT 5
    ");
    $stmt->execute([$uid]);
    $recent_grades = $stmt->fetchAll();

    $enroll_start = '2024-09-01 08:00:00';
    $enroll_end   = '2024-09-07 23:59:59';
    $now_str      = date('Y-m-d H:i:s');
    $enroll_open  = ($now_str >= $enroll_start && $now_str <= $enroll_end);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>学生门户 · <?= $student ? htmlspecialchars($student['name']) : '加载失败' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/student_portal.css">
</head>
<body>

<?php include __DIR__ . '/../components/alerts.php'; ?>

<?php
$activePage = 'student_portal';
include __DIR__ . '/../components/student_sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">学生门户首页</div>
        <div class="topbar-right">
            <div class="status-dot" title="在线"></div>
            <div class="topbar-time" id="clock"><?= date('Y-m-d H:i:s') ?></div>
        </div>
    </header>

    <div class="content">
        <?php if ($student): ?>

        <?php if ($enroll_open): ?>
        <div class="enroll-banner fade-up">
            <div>
                <div class="eb-text">📢 选课窗口已开放！</div>
                <div class="eb-sub">截止时间：<?= $enroll_end ?>，请尽快完成选课</div>
            </div>
            <a class="btn-enroll" href="course_select.php">立即选课 →</a>
        </div>
        <?php endif; ?>

        <div class="hero-banner fade-up">
            <div class="hero-text">
                <div class="greeting"><?= date('H') < 12 ? '早上好' : (date('H') < 18 ? '下午好' : '晚上好') ?> · WELCOME BACK</div>
                <div class="name"><?= htmlspecialchars($student['name']) ?></div>
                <div class="meta">
                    <span>🏛 <?= htmlspecialchars($student['dept_name']) ?></span>
                    <span>📅 <?= $student['grade'] ?> 级</span>
                </div>
            </div>
            <div class="hero-stats">
                <div class="hero-stat">
                    <span class="val"><?= number_format($stats['gpa'], 2) ?></span>
                    <span class="label">GPA</span>
                </div>
                <div class="hero-divider"></div>
                <div class="hero-stat">
                    <span class="val"><?= number_format($stats['credits'], 1) ?></span>
                    <span class="label">已修学分</span>
                </div>
                <div class="hero-divider"></div>
                <div class="hero-stat">
                    <span class="val"><?= $stats['enrolled'] ?></span>
                    <span class="label">总选课</span>
                </div>
            </div>
        </div>

        <div class="stats-row">
            <div class="stat-card fade-up delay-1">
                <div class="sc-icon">📚</div>
                <div class="sc-label">当前选课数</div>
                <div class="sc-val"><?= $stats['enrolled'] ?></div>
                <div class="sc-sub">门课程 · 本学期</div>
            </div>
            <div class="stat-card fade-up delay-2">
                <div class="sc-icon">📊</div>
                <div class="sc-label">已公布成绩</div>
                <div class="sc-val"><?= $stats['published'] ?></div>
                <div class="sc-sub">共 <?= $stats['enrolled'] ?> 门 · <?= max(0, $stats['enrolled'] - $stats['published']) ?> 门待公布</div>
            </div>
            <div class="stat-card fade-up delay-3">
                <div class="sc-icon">⭐</div>
                <div class="sc-label">综合 GPA</div>
                <div class="sc-val"><?= number_format($stats['gpa'], 2) ?></div>
                <div class="sc-sub">满分 4.0 · 本学期</div>
            </div>
            <div class="stat-card fade-up delay-4">
                <div class="sc-icon">🎓</div>
                <div class="sc-label">累计学分</div>
                <div class="sc-val"><?= number_format($stats['credits'], 1) ?></div>
                <div class="sc-sub">学分 · 已修</div>
            </div>
        </div>

        <div class="section-title fade-up delay-2">功能导航</div>
        <div class="func-grid fade-up delay-3">
            <a class="func-card fc-blue" href="profile.php">
                <div class="func-icon-wrap">👤</div>
                <div class="func-body">
                    <div class="ft">个人信息</div>
                    <div class="fd">查看并维护姓名、联系方式、所属院系等基础信息，支持上传头像与修改密码。</div>
                    <span class="ftag">Personal Information</span>
                </div>
                <div class="func-arrow">→</div>
            </a>
            <a class="func-card fc-jade" href="course_select.php">
                <div class="func-icon-wrap">📚</div>
                <div class="func-body">
                    <div class="ft">选课系统</div>
                    <div class="fd">浏览本学期开放课程，实时查看余量，一键完成选课、退课及待审核状态跟踪。</div>
                    <span class="ftag">Course Selection</span>
                </div>
                <div class="func-arrow">→</div>
            </a>
            <a class="func-card fc-gold" href="my_grades.php">
                <div class="func-icon-wrap">📊</div>
                <div class="func-body">
                    <div class="ft">成绩查询</div>
                    <div class="fd">查看各科平时分、期末分及综合成绩，支持多学期筛选与 GPA 统计。</div>
                    <span class="ftag">Grade Inquiry</span>
                </div>
                <div class="func-arrow">→</div>
            </a>
            <a class="func-card fc-rose" href="exam_info.php">
                <div class="func-icon-wrap">📝</div>
                <div class="func-body">
                    <div class="ft">考试相关</div>
                    <div class="fd">查看已选课程的考试安排、考场信息及时间，确认参考资格与相关注意事项。</div>
                    <span class="ftag">About Exam</span>
                </div>
                <div class="func-arrow">→</div>
            </a>
        </div>

        <div class="bottom-row fade-up delay-4">

            <div class="grade-card">
                <div class="card-header">
                    <div class="card-header-title">📊 最近成绩</div>
                    <a class="card-header-action" href="my_grades.php">查看全部 →</a>
                </div>
                <?php if (empty($recent_grades)): ?>
                    <div class="empty-state"><div class="es-icon">📭</div>暂无选课记录</div>
                <?php else: ?>
                <table class="grade-table">
                    <thead><tr><th>课程名称</th><th>学分</th><th>学期</th><th>成绩</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent_grades as $g): ?>
                        <tr>
                            <td><?= htmlspecialchars($g['course']) ?></td>
                            <td><?= htmlspecialchars($g['credit']) ?></td>
                            <td style="font-family:'JetBrains Mono',monospace;font-size:12px;color:var(--ink-muted)"><?= htmlspecialchars($g['semester']) ?></td>
                            <td>
                                <?php $sc = $g['final_score']; $pub = (int)$g['is_published']; ?>
                                <?php if ($pub && $sc !== null && $sc !== ''): ?>
                                    <span class="score-badge">
                                        <span class="score-letter" style="background:<?= scoreColor($sc) ?>"><?= scoreLetter($sc) ?></span>
                                        <span style="color:<?= scoreColor($sc) ?>"><?= displayScore($sc) ?></span>
                                    </span>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--ink-muted);font-style:italic">待公布</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <div class="info-avatar-section">
                    <?php if ($student['has_avatar']): ?>
                        <img class="info-avatar-img"
                             src="<?= htmlspecialchars($student['avatar_path']) ?>"
                             alt="头像">
                    <?php else: ?>
                        <div class="info-avatar"><?= htmlspecialchars($student['avatar_initials']) ?></div>
                    <?php endif; ?>
                    <div class="info-name-big"><?= htmlspecialchars($student['name']) ?></div>
                    <div class="info-id-badge"><?= htmlspecialchars($student['student_id']) ?></div>
                    <div class="info-status-badge <?= $student['status'] !== '正常' ? 'inactive' : '' ?>">
                        ● <?= htmlspecialchars($student['status']) ?>
                    </div>
                    <a class="avatar-edit-link" href="profile.php#avatar">✏ 修改头像</a>
                </div>
                <div class="info-list">
                    <div class="info-row"><span class="ik">院系</span><span class="iv"><?= htmlspecialchars($student['dept_name']) ?></span></div>
                    <div class="info-row"><span class="ik">年级</span><span class="iv"><?= htmlspecialchars($student['grade_label']) ?></span></div>
                    <div class="info-row"><span class="ik">入学年份</span><span class="iv"><?= htmlspecialchars($student['enrollment_year']) ?> 年</span></div>
                    <div class="info-row"><span class="ik">性别</span><span class="iv"><?= htmlspecialchars($student['gender']) ?></span></div>
                    <div class="info-row"><span class="ik">邮箱</span><span class="iv"><?= htmlspecialchars($student['email']) ?></span></div>
                    <div class="info-row"><span class="ik">手机</span><span class="iv"><?= htmlspecialchars($student['phone']) ?></span></div>
                </div>
            </div>

        </div>

        <?php else: ?>
        <div style="text-align:center;padding:80px 0;color:var(--ink-muted);">
            <div style="font-size:48px;margin-bottom:16px;">🔌</div>
            <div style="font-size:16px;font-weight:600;margin-bottom:8px;">无法加载学生数据</div>
            <div style="font-size:13px;">请检查数据库连接或确认测试数据已正确插入</div>
        </div>
        <?php endif; ?>

    </div>
</main>

<script src="/js/clock.js"></script>
</body>
</html>