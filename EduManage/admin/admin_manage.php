<?php
/**
 * admin/admin_manage.php
 * 管理员管理页面：仅供超管维护后台管理员账号。
 */
require 'common.php';
$pdo = app_require_pdo();
require_once '../components/logger.php';
admin_auth();

// 仅 super_admin 可访问
if (!function_exists('admin_is_super_admin') || !admin_is_super_admin()) {
    http_response_code(403);
    exit('无权限访问：仅超管可操作');
}

// 处理 POST 操作（添加/更新/删除）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $token = $_POST['csrf_token'] ?? null;
    if (!app_validate_csrf($token)) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => 'CSRF 验证失败']);
        exit;
    }

    try {
        if ($action === 'add') {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if ($msg = admin_validate_school_email($email, '管理员邮箱', '请输入用户邮箱')) throw new Exception($msg);
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $uid = $stmt->fetchColumn();

            // 如果基础用户已存在，不允许将其升级为管理员（也不允许重复注册）
            if ($uid) {
                throw new Exception('该邮箱已存在，不能注册或升级为管理员');
            }

            // 不存在则创建基础用户（默认密码 123456），然后赋予管理员权限
            $default_pass = password_hash('123456', PASSWORD_DEFAULT);
            $ins_user = $pdo->prepare("INSERT INTO user (email, password, name) VALUES (?, ?, '新管理员')");
            $ins_user->execute([$email, $default_pass]);
            $uid = $pdo->lastInsertId();

            // 只插入普通管理员
            $ins = $pdo->prepare("INSERT INTO admin (user_id, role) VALUES (?, 'admin') ON DUPLICATE KEY UPDATE role = 'admin'");
            $ins->execute([(int)$uid]);
            // 记录日志：新增管理员（涉及 user 与 admin 表）
            if (function_exists('sys_log')) {
                $desc = '新增管理员: ' . $email . ' (ID ' . (int)$uid . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user,admin', (int)$uid);
            }
            if (ob_get_level()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'message' => '注册并添加管理员成功']);
            exit;
        }

        if ($action === 'remove') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid <= 0) throw new Exception('无效用户ID');
            if ($uid === (int)$_SESSION['user_id']) throw new Exception('不能操作自己');

            try {
                $pdo->beginTransaction();

                // 检查 admin 记录是否存在并锁行，防止并发问题
                $chk = $pdo->prepare('SELECT role FROM admin WHERE user_id = ? FOR UPDATE');
                $chk->execute([$uid]);
                $role = $chk->fetchColumn();
                if ($role === false) {
                    $pdo->rollBack();
                    throw new Exception('管理员不存在');
                }
                if (app_is_super_admin_role($role)) {
                    $pdo->rollBack();
                    throw new Exception('不能移除超管账号');
                }

                // 删除 admin 记录
                $del = $pdo->prepare('DELETE FROM admin WHERE user_id = ?');
                $del->execute([$uid]);

                // 同步删除基础用户记录
                $delUser = $pdo->prepare('DELETE FROM user WHERE user_id = ?');
                $delUser->execute([$uid]);

                $pdo->commit();
                // 记录日志：移除管理员并删除用户（admin 与 user 两表）
                if (function_exists('sys_log')) {
                    $desc = '移除管理员并删除用户: ' . $uid;
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'admin,user', $uid);
                }
                if (ob_get_level()) ob_clean();
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'message' => '已移除管理员并删除用户']);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $e;
            }
        }

        throw new Exception('未知操作');
    } catch (Throwable $e) {
        if (ob_get_level()) ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// GET: 展示管理员列表 (过滤自己和除了自己以外的超管)
$stmt = $pdo->prepare('SELECT a.user_id, a.role, u.name, u.email FROM admin a LEFT JOIN user u ON a.user_id = u.user_id WHERE a.user_id != ? AND a.role != ? ORDER BY a.user_id DESC');
$stmt->execute([(int)$_SESSION['user_id'], app_super_admin_role()]);
$admins = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = '管理员管理 - 管理后台';
require 'layout_head.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2>管理员管理</h2>
</div>

<div class="card mb-3">
    <div class="card-body">
        <form id="addAdminForm" class="row g-2">
            <?php echo admin_csrf_input(); ?>
            <div class="col-auto">
                <input name="email" class="form-control" placeholder="用户邮箱" required inputmode="email" pattern="<?= h(admin_school_email_pattern()) ?>" title="<?= h(admin_school_email_title()) ?>">
            </div>
            <div class="col-auto">
                <button class="btn btn-primary" type="submit">添加普通管理员</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr><th>UID</th><th>姓名</th><th>邮箱</th><th>角色</th><th>操作</th></tr>
            </thead>
            <tbody>
                <?php foreach ($admins as $a): ?>
                <tr>
                    <td><?php echo h($a['user_id']); ?></td>
                    <td><?php echo h($a['name']); ?></td>
                    <td><?php echo h($a['email']); ?></td>
                    <td><span class="badge bg-secondary">普通管理员</span></td>
                    <td>
                        <button class="btn btn-sm btn-danger remove-admin" data-uid="<?php echo (int)$a['user_id']; ?>">移除</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.getElementById('addAdminForm').addEventListener('submit', function (ev) {
    ev.preventDefault();
    const form = ev.target;
    const data = new FormData(form);
    data.append('action', 'add');
    fetch(location.href, { method: 'POST', body: data })
    .then(r => r.text())
    .then(text => {
        try {
            const j = JSON.parse(text);
            window.AdminConfirm.alert(j.message || (j.ok ? '操作成功' : '操作失败')).then(() => {
                if (j.ok) location.reload();
            });
        } catch (e) {
            window.AdminConfirm.alert('JSON解析失败，服务器返回: ' + text.substring(0, 100));
            console.error('服务器原始响应:', text);
        }
    })
    .catch(e => window.AdminConfirm.alert('网络请求错误: ' + e));
});

document.querySelectorAll('.remove-admin').forEach(btn => btn.addEventListener('click', async function () {
    const ok = await window.AdminConfirm.confirm({
        title: '移除管理员',
        message: '确定移除该管理员并删除对应用户吗？此操作不可撤销。',
        confirmText: '移除',
        confirmClass: 'btn-danger'
    });
    if (!ok) return;
    const uid = this.dataset.uid;
    const data = new FormData();
    data.append('action','remove');
    data.append('user_id', uid);
    data.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);
    
    fetch(location.href, { method: 'POST', body: data })
    .then(r => r.text())
    .then(text => {
        try {
            const j = JSON.parse(text);
            window.AdminConfirm.alert(j.message || (j.ok ? '操作成功' : '操作失败')).then(() => {
                if (j.ok) location.reload();
            });
        } catch (e) {
            window.AdminConfirm.alert('JSON解析失败，服务器返回: ' + text.substring(0, 100));
            console.error('服务器原始响应:', text);
        }
    })
    .catch(e => window.AdminConfirm.alert('网络请求错误: ' + e));
}));
</script>

<?php include 'footer.php';
