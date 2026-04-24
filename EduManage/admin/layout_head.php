<?php
/**
 * admin/layout_head.php
 * 管理后台布局头模板：输出页面基础 HTML 头部、样式资源与容器起始结构。
 */
// 统一的 admin 布局头部，可通过设置 $page_title 自定义标题。
$title = $page_title ?? '管理后台';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../css/portal-common.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_admin_css_url(), ENT_QUOTES, 'UTF-8') ?>">
    <script src="<?= htmlspecialchars(app_frontend_paths_script_url(), ENT_QUOTES, 'UTF-8') ?>"></script>
</head>
<body class="admin-shell portal-shell portal-admin">
<?php include 'header.php'; ?>
