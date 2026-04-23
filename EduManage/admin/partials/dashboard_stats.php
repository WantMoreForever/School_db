<?php
/**
 * admin/partials/dashboard_stats.php
 * 管理后台首页统计卡片片段。
 */
$dashboardCounts = $dashboardCounts ?? [];
$dashboardStats = [
    ['label' => '学生总数', 'key' => 'students', 'class' => 'text-primary'],
    ['label' => '教师总数', 'key' => 'teachers', 'class' => 'text-success'],
    ['label' => '课程总数', 'key' => 'courses', 'class' => 'text-info'],
    ['label' => '公告总数', 'key' => 'announcements', 'class' => 'text-warning'],
    ['label' => '教室数量', 'key' => 'classrooms', 'class' => 'text-secondary'],
    ['label' => '排课数量', 'key' => 'schedules', 'class' => 'text-danger'],
    ['label' => '院系数量', 'key' => 'departments', 'class' => 'text-primary-emphasis'],
    ['label' => '专业数量', 'key' => 'majors', 'class' => 'text-dark'],
];
?>
<section class="admin-dashboard-grid">
    <?php foreach ($dashboardStats as $stat): ?>
        <article class="admin-stat-card">
            <p class="admin-stat-label"><?= h($stat['label']) ?></p>
            <h2 class="admin-stat-value <?= h($stat['class']) ?>"><?= h($dashboardCounts[$stat['key']] ?? 0) ?></h2>
        </article>
    <?php endforeach; ?>
</section>
