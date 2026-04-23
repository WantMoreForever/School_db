<?php
/**
 * admin/profile.php
 * 管理员个人资料页面：维护当前管理员姓名、密码、联系方式与头像。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();

$uid = (int) ($_SESSION['user_id'] ?? 0);
$user = admin_fetch_profile_user($pdo, $uid);
if (!$user) {
    header('Location: ' . app_catalog_url('admin', 'pages', 'index'));
    exit;
}

$page_title = '个人信息 - 管理后台';
$avatarUrl = admin_fetch_user_avatar_path($pdo, $uid);
$adminApiUrl = app_catalog_url('admin', 'api', 'main');
require 'layout_head.php';
?>

<div class="admin-page">
    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title">个人信息</h1>
            <p class="admin-page-subtitle">维护管理员账号资料、联系方式和头像。</p>
        </div>
    </section>

    <?php $pageAlert = admin_page_alert(); ?>
    <?php if ($pageAlert): ?><div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div><?php endif; ?>

    <div class="admin-profile-grid">
        <section class="admin-section-card admin-profile-card">
            <img src="<?= h($avatarUrl) ?>" alt="当前头像" class="admin-profile-avatar">
            <div class="admin-profile-meta">
                <div><strong><?= h($user['name']) ?></strong></div>
                <div class="mt-1"><?= h($user['email']) ?></div>
                <div class="mt-1">用户 ID：<?= h($user['user_id']) ?></div>
            </div>
        </section>

        <section class="admin-section-card">
            <div class="admin-section-head">
                <div>
                    <h2 class="admin-section-title">资料设置</h2>
                </div>
            </div>
            <div class="admin-section-body">
                <form action="<?= h($adminApiUrl) ?>?act=update_self" method="post" enctype="multipart/form-data">
                    <?= admin_csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">用户 ID</label>
                        <input class="form-control" value="<?= h($user['user_id']) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">邮箱（不可修改）</label>
                        <input class="form-control" value="<?= h($user['email']) ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">姓名</label>
                        <input name="name" class="form-control" value="<?= h($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">性别</label>
                        <select name="gender" class="form-select">
                            <option value="male" <?= $user['gender'] === 'male' ? 'selected' : '' ?>>男</option>
                            <option value="female" <?= $user['gender'] === 'female' ? 'selected' : '' ?>>女</option>
                            <option value="other" <?= $user['gender'] === 'other' ? 'selected' : '' ?>>其他</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">手机号</label>
                        <input name="phone" class="form-control" inputmode="numeric" maxlength="11" pattern="\d{11}" title="请输入11位数字手机号" value="<?= h($user['phone']) ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">新密码（留空则不修改）</label>
                        <input type="password" name="pwd" class="form-control">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">上传新头像（可选）</label>
                        <input type="file" name="avatar" accept="image/*" class="form-control">
                    </div>

                    <button type="submit" class="btn btn-primary">保存</button>
                </form>
            </div>
        </section>
    </div>
</div>

<?php require 'footer.php'; ?>
