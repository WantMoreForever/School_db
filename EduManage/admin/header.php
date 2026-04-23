<?php
/**
 * admin/header.php
 * 管理后台通用页头：渲染导航菜单、当前管理员信息与退出入口。
 */
$pdo = app_require_pdo();
admin_auth();

$activePage = admin_current_page_key();
$avatarPath = admin_fetch_user_avatar_path($pdo, (int) ($_SESSION['user_id'] ?? 0));

$isSuperAdmin = false;
if (function_exists('admin_is_super_admin')) {
    try {
        $isSuperAdmin = admin_is_super_admin();
    } catch (Throwable $e) {
        $isSuperAdmin = false;
    }
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4 admin-topbar">
    <div class="container admin-topbar-inner">
        <a class="navbar-brand admin-topbar-brand" href="<?= h(app_catalog_url('admin', 'pages', 'index')) ?>">管理后台</a>

        <div class="admin-topbar-main">
            <ul class="navbar-nav admin-topbar-menu">
                <?php foreach (admin_nav_pages($isSuperAdmin) as $pageKey => $pageMeta): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $activePage === $pageKey ? 'active' : '' ?>" href="<?= h(admin_page_url($pageKey)) ?>">
                            <?= h((string) $pageMeta['label']) ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <div class="admin-topbar-actions">
                <a href="<?= h(app_catalog_url('admin', 'pages', 'profile')) ?>" class="admin-topbar-profile text-white text-decoration-none <?= $activePage === 'profile' ? 'is-active' : '' ?>">
                    <img src="<?= h($avatarPath) ?>" alt="头像" class="rounded-circle admin-topbar-avatar">
                    <span class="admin-topbar-username"><?= h($_SESSION['user_name'] ?? '') ?></span>
                </a>
                <a href="<?= h(app_logout_url()) ?>" class="btn btn-sm btn-light admin-topbar-logout">退出登录</a>
            </div>
        </div>
    </div>
</nav>
<main class="container admin-main-shell mb-5">
