<?php
/**
 * admin/index.php
 * 管理后台首页：展示学生、教师、课程等核心数据的仪表盘概览。
 */
require 'common.php';
$pdo = app_require_pdo();
$dashboardCounts = admin_fetch_dashboard_counts($pdo);
$page_title = '管理后台';
require 'layout_head.php';
?>

<div class="admin-page">
    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title">管理后台总览</h1>
            <p class="admin-page-subtitle">统一查看学生、教师、课程、公告、教室、排课、院系和专业等核心数据。</p>
        </div>
    </section>

    <?php require app_admin_partial_path('dashboard_stats'); ?>
</div>

<?php require 'footer.php'; ?>
