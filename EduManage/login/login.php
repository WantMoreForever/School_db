<?php
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/db.php';
require_once __DIR__ . '/../components/logger.php';

app_start_session();
$pdo = app_db();

$error_msg = trim((string) ($_GET['error'] ?? ''));
$email = '';
$login_images = [];
$loginImagePattern = rtrim(str_replace('\\', '/', app_login_images_dir()), '/') . '/*.{jpg,jpeg,png,webp,avif}';

foreach ((array) glob($loginImagePattern, GLOB_BRACE) as $path) {
    if (!is_file($path)) {
        continue;
    }

    $login_images[] = app_login_image_url(basename($path));
}

sort($login_images, SORT_NATURAL | SORT_FLAG_CASE);

if ($login_images === []) {
    $login_images[] = app_login_image_url('login.jpg');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($email === '' || $password === '') {
        $error_msg = '请输入邮箱和密码。';
    } elseif ($pdo === null) {
        $error_msg = '数据库连接失败，请稍后重试。';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM user WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !app_password_verify_compat($password, $user['password'] ?? '')) {
            // 登录失败：记录尝试的邮箱为标识，并标注关联表为 user 以便追踪
            $error_msg = '账号或密码错误。';
        } else {
            // 如果用户存在但状态非 active，拒绝登录
            $status = $user['status'] ?? null;
            if (!is_string($status) || $status !== 'active') {
                $error_msg = '账号已被停用或封禁，请联系管理员。';
            } else {
                app_login_user($pdo, $user);
                // 登录成功：关联到 user 表的该用户 ID
                sys_log($pdo, (int) $user['user_id'], sys_log_build('登录成功', [
                    'user_id' => (int) $user['user_id'],
                ]), 'user', (int)$user['user_id']);

                $role = app_find_user_role($pdo, (int) $user['user_id']);
                if ($role === null) {
                    $error_msg = '当前账号未分配系统角色，请联系管理员。';
                } else {
                    app_redirect_by_role($role);
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>教学管理系统登录</title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_login_css_url(), ENT_QUOTES, 'UTF-8') ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body>
    <main class="login-page">
        <div class="login-shell">
            <section
                class="login-showcase"
                aria-label="登录展示图片"
                data-images='<?= htmlspecialchars(json_encode($login_images, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>'
            >
                <div class="showcase-slider">
                    <?php foreach ($login_images as $index => $image): ?>
                        <button
                            type="button"
                            class="showcase-slide<?= $index === 0 ? ' is-active' : '' ?>"
                            data-slide-index="<?= $index ?>"
                            aria-label="切换到第<?= $index + 1 ?>张图片"
                            style="background-image: url('<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>');"
                        ></button>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="login-panel" aria-label="登录表单">
                <h2>吉林大学教学管理系统</h2>

                <?php if ($error_msg !== ''): ?>
                    <div class="alert alert-error" role="alert">
                        <?= htmlspecialchars($error_msg, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>

                <form class="login-form" action="" method="post" accept-charset="UTF-8">
                    <label class="form-field">
                        <span>邮箱</span>
                        <input
                            type="email"
                            name="email"
                            required
                            autocomplete="username"
                            placeholder="请输入邮箱地址"
                            value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </label>

                    <label class="form-field">
                        <span>密码</span>
                        <input
                            type="password"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="请输入登录密码"
                        >
                    </label>

                    <button type="submit" class="submit-btn">登录系统</button>
                </form>

                <p class="login-note">若账号状态异常或未分配角色，请联系系统管理员处理。</p>
            </section>
        </div>
    </main>
    <script>
        (function () {
            var showcase = document.querySelector('.login-showcase');
            if (!showcase) return;

            var slides = Array.prototype.slice.call(showcase.querySelectorAll('.showcase-slide'));
            if (slides.length <= 1) return;

            var current = 0;
            var autoTimer = null;
            var autoDelay = 10000;

            function render(index) {
                current = (index + slides.length) % slides.length;

                slides.forEach(function (slide, slideIndex) {
                    slide.classList.toggle('is-active', slideIndex === current);
                });
            }

            function stopAuto() {
                if (autoTimer) {
                    window.clearInterval(autoTimer);
                    autoTimer = null;
                }
            }

            function startAuto() {
                stopAuto();
                autoTimer = window.setInterval(function () {
                    render(current + 1);
                }, autoDelay);
            }

            function manualRender(index) {
                render(index);
                startAuto();
            }

            slides.forEach(function (slide) {
                slide.addEventListener('click', function () {
                    manualRender(current + 1);
                });
            });

            showcase.addEventListener('mouseenter', stopAuto);
            showcase.addEventListener('mouseleave', startAuto);

            document.addEventListener('visibilitychange', function () {
                if (document.hidden) {
                    stopAuto();
                } else {
                    startAuto();
                }
            });

            startAuto();
        }());
    </script>
</body>
</html>
