<?php
require_once __DIR__ . '/../components/auth.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/student_data.php';

$uid = requireStudentLogin();

$student     = null;
$success_msg = '';
$error_msg   = '';

if ($pdo && !$db_error) {
    $student = getStudentBaseInfo($pdo, $uid);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {

    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
 $uploadDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}
        $allowTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $maxSize    = 2 * 1024 * 1024;
        $ftype      = mime_content_type($_FILES['avatar']['tmp_name']);
        $fsize      = $_FILES['avatar']['size'];

        if (!in_array($ftype, $allowTypes)) {
            $error_msg = '头像格式不支持，请上传 JPG / PNG / GIF / WEBP。';
        } elseif ($fsize > $maxSize) {
            $error_msg = '文件过大，请上传小于 2MB 的图片。';
        } else {
            $ext      = strtolower(pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION));
            $newName  = 'avatar_' . $uid . '_' . time() . '.' . $ext;
            $destPath = $uploadDir . $newName;

            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $destPath)) {
                $stmt = $pdo->prepare("SELECT image FROM user WHERE user_id = ?");
                $stmt->execute([$uid]);
                $oldImg = $stmt->fetchColumn();
                if ($oldImg && $oldImg !== 'RC.PNG' && file_exists($uploadDir . $oldImg)) {
                    unlink($uploadDir . $oldImg);
                }
                $stmt = $pdo->prepare("UPDATE user SET image = ? WHERE user_id = ?");
                $stmt->execute([$newName, $uid]);
                $success_msg = '头像已更新！';
            } else {
                $error_msg = '文件上传失败，请检查 uploads/ 目录权限。';
            }
        }
    }

    if (!$error_msg && isset($_POST['action']) && $_POST['action'] === 'update_info') {
        $phone = trim($_POST['phone'] ?? '');
        $stmt = $pdo->prepare("UPDATE user SET phone = ? WHERE user_id = ?");
        $stmt->execute([$phone ?: null, $uid]);
        if (!$success_msg) $success_msg = '个人信息已保存。';
    }

    $student = getStudentBaseInfo($pdo, $uid);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>个人信息 · <?= $student ? htmlspecialchars($student['name']) : '加载失败' ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Noto+Serif+SC:wght@400;600;700&family=Noto+Sans+SC:wght@300;400;500;600&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/css/base.css">
<link rel="stylesheet" href="/css/profile.css">
</head>
<body>

<?php
$activePage = 'profile';
include __DIR__ . '/../components/student_sidebar.php';
?>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">个人信息</div>
        <div class="topbar-right">
            <div class="status-dot" title="在线"></div>
            <div class="topbar-time" id="clock"><?= date('Y-m-d H:i:s') ?></div>
        </div>
    </header>

    <div class="content">
        <?php include __DIR__ . '/../components/alerts.php'; ?>

        <?php if ($student): ?>

        <div class="profile-top fade-up" id="avatar">
            <div class="profile-avatar-wrap">
                <?php if ($student['has_avatar']): ?>
                    <img class="profile-avatar-img" id="avatarPreview"
                         src="<?= htmlspecialchars($student['avatar_path']) ?>" alt="当前头像">
                <?php else: ?>
                    <div class="profile-avatar-placeholder" id="avatarPreview">
                        <?= htmlspecialchars($student['avatar_initials']) ?>
                    </div>
                <?php endif; ?>
                <div class="avatar-overlay" id="avatarOverlay" title="点击更换头像">📷</div>
            </div>
            <div class="profile-top-info">
                <div class="pt-name"><?= htmlspecialchars($student['name']) ?></div>
                <div class="pt-id"><?= htmlspecialchars($student['student_id']) ?></div>
                <div class="pt-dept"><?= htmlspecialchars($student['dept_name']) ?></div>
                <div class="pt-status <?= $student['status'] !== '正常' ? 'inactive' : '' ?>">
                    ● <?= htmlspecialchars($student['status']) ?>
                </div>
            </div>
        </div>

        <form class="avatar-form fade-up" id="avatarForm"
              action="profile.php" method="POST" enctype="multipart/form-data">
            <div class="avatar-form-inner">
                <label class="avatar-file-label" for="avatarInput">
                    选择图片（JPG / PNG / GIF / WEBP，≤ 2MB）
                </label>
                <input type="file" id="avatarInput" name="avatar"
                       accept="image/jpeg,image/png,image/gif,image/webp" style="display:none">
                <button type="submit" class="btn-primary" id="avatarSubmit" disabled>上传头像</button>
                <button type="button" class="btn-ghost" id="avatarCancel">取消</button>
            </div>
            <div class="avatar-preview-row" id="avatarPreviewRow" style="display:none">
                <img id="avatarNewPreview" src="" alt="预览">
                <span id="avatarFileName"></span>
            </div>
        </form>

        <div class="profile-section fade-up delay-1">
            <div class="ps-title">📋 基础信息 <span class="ps-hint">（以下字段由管理员维护，不可自行修改）</span></div>
            <div class="info-grid">
                <div class="ig-row"><span class="ig-k">姓名</span><span class="ig-v"><?= htmlspecialchars($student['name']) ?></span></div>
                <div class="ig-row"><span class="ig-k">学号</span><span class="ig-v mono"><?= htmlspecialchars($student['student_id']) ?></span></div>
                <div class="ig-row"><span class="ig-k">性别</span><span class="ig-v"><?= htmlspecialchars($student['gender']) ?></span></div>
                <div class="ig-row"><span class="ig-k">院系</span><span class="ig-v"><?= htmlspecialchars($student['dept_name']) ?></span></div>
                <div class="ig-row"><span class="ig-k">年级</span><span class="ig-v"><?= htmlspecialchars($student['grade_label']) ?></span></div>
                <div class="ig-row"><span class="ig-k">入学年份</span><span class="ig-v"><?= htmlspecialchars($student['enrollment_year']) ?> 年</span></div>
                <div class="ig-row"><span class="ig-k">账号状态</span>
                    <span class="ig-v">
                        <span class="status-pill <?= $student['status'] !== '正常' ? 'inactive' : 'active' ?>">
                            <?= htmlspecialchars($student['status']) ?>
                        </span>
                    </span>
                </div>
            </div>
        </div>

        <div class="profile-section fade-up delay-2">
            <div class="ps-title">📬 联系方式 <span class="ps-hint">（可自行修改）</span></div>
            <form action="profile.php" method="POST" class="edit-form" id="infoForm">
                <input type="hidden" name="action" value="update_info">
                <div class="form-row">
                    <label class="form-label" for="phoneInput">手机号码</label>
                    <input class="form-input" type="tel" id="phoneInput" name="phone"
                           value="<?= htmlspecialchars($student['phone']) ?>"
                           placeholder="请输入手机号">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn-primary">保存修改</button>
                    <button type="button" class="btn-ghost" onclick="resetInfoForm()">撤销更改</button>
                </div>
            </form>
        </div>

        <div class="profile-section fade-up delay-3">
            <div class="ps-title">🔗 其他操作</div>
            <div class="quick-links">
                <a class="quick-link-card" href="change_pwd.php">
                    <span class="ql-icon">🔒</span>
                    <div>
                        <div class="ql-title">修改密码</div>
                        <div class="ql-desc">定期更换密码保障账号安全</div>
                    </div>
                    <span class="ql-arrow">→</span>
                </a>
                <a class="quick-link-card" href="student_portal.php">
                    <span class="ql-icon">🏠</span>
                    <div>
                        <div class="ql-title">返回首页</div>
                        <div class="ql-desc">查看学业概览与功能导航</div>
                    </div>
                    <span class="ql-arrow">→</span>
                </a>
            </div>
        </div>

        <?php else: ?>
        <div style="text-align:center;padding:80px 0;color:var(--ink-muted);">
            <div style="font-size:48px;margin-bottom:16px;">🔌</div>
            <div style="font-size:16px;font-weight:600;">无法加载学生数据</div>
        </div>
        <?php endif; ?>

    </div><!-- /content -->
</main>

<script src="/js/clock.js"></script>
<script src="/js/profile.js"></script>
</body>
</html>