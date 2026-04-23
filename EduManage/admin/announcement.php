<?php
/**
 * admin/announcement.php
 * 公告管理页面：负责公告的查询、发布、编辑、删除和置顶。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$adminApiUrl = app_catalog_url('admin', 'api', 'main');

$majors = admin_fetch_majors($pdo);

// build majors map for display
$majorsMap = admin_index_rows_by_int_key($majors, 'major_id', 'major_name');

// Filters and pagination
$q = trim((string)($_GET['q'] ?? ''));
$filter_target = $_GET['filter_target'] ?? '';
$filter_major = (int)($_GET['filter_major'] ?? 0);
$filter_pinned = $_GET['filter_pinned'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = (int)($_GET['per_page'] ?? 20);
if ($per_page <= 0) $per_page = 20;
$offset = ($page - 1) * $per_page;


$where = [];
$params = [];

// search
if ($q !== '') {
    $where[] = '(a.title LIKE ? OR a.content LIKE ?)';
    $params[] = "%$q%";
    $params[] = "%$q%";
}

// pinned filter
if ($filter_pinned === '1') { $where[] = 'a.is_pinned = 1'; }
if ($filter_pinned === '0') { $where[] = 'a.is_pinned = 0'; }

// target filter via EXISTS
if ($filter_target !== '') {
    if ($filter_target === 'major' && $filter_major > 0) {
        $where[] = 'EXISTS (SELECT 1 FROM announcement_target at2 WHERE at2.announcement_id = a.announcement_id AND at2.target_type = "major" AND at2.target_id = ?)';
        $params[] = $filter_major;
    } else {
        $where[] = 'EXISTS (SELECT 1 FROM announcement_target at2 WHERE at2.announcement_id = a.announcement_id AND at2.target_type = ?)';
        $params[] = $filter_target;
    }
}

// build where clause
$whereSql = '';
if (!empty($where)) { $whereSql = 'WHERE ' . implode(' AND ', $where); }

// total count
$countSql = 'SELECT COUNT(DISTINCT a.announcement_id) FROM announcement a ' . $whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

// fetch page
$announcements = [];
if ($total > 0) {
    $sql = 'SELECT a.announcement_id, a.author_user_id, u.name AS author_name, a.title, a.content, a.status, a.is_pinned, a.published_at, a.created_at, a.updated_at, GROUP_CONCAT(CONCAT(at.target_type,":",at.target_id) SEPARATOR ",") AS targets FROM announcement a LEFT JOIN user u ON a.author_user_id = u.user_id LEFT JOIN announcement_target at ON at.announcement_id = a.announcement_id ' . $whereSql . ' GROUP BY a.announcement_id ORDER BY a.is_pinned DESC, a.published_at DESC, a.created_at DESC LIMIT ? OFFSET ?';
    $stmt = $pdo->prepare($sql);
    $execParams = array_merge($params, [$per_page, $offset]);
    $stmt->execute($execParams);
    $announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// helper to render targets
// duplicate block removed; single implementation exists later in file

// helper to render targets
function render_targets_label($targetsStr, $majorsMap) {
    if (empty($targetsStr)) return '—';
    $parts = array_unique(explode(',', $targetsStr));
    $labels = [];
    foreach ($parts as $p) {
        [$t, $id] = array_pad(explode(':', $p, 2), 2, '');
        if ($t === 'all') $labels[] = '全体';
        elseif ($t === 'students') $labels[] = '学生';
        elseif ($t === 'teachers') $labels[] = '教师';
        elseif ($t === 'major') $labels[] = '专业: ' . h($majorsMap[(int)$id] ?? ('ID ' . (int)$id));
        elseif ($t === 'section') $labels[] = '班级: ' . h($id ?: '');
        else $labels[] = h($p);
    }
    return implode(', ', $labels);
}
?>
<?php $page_title = '公告管理 - 管理后台'; require 'layout_head.php'; ?>
<div class="container mt-4 announcement-page">
    <?php $pageAlert = admin_page_alert(); ?>
    <?php if ($pageAlert): ?>
        <div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div>
    <?php endif; ?>

    <section class="announcement-card mb-4">
        <div class="announcement-card-header">
            <div>
                <h4 class="mb-1">发布公告</h4>
            
        </div>
        <form action="<?= h($adminApiUrl) ?>?act=add_announcement" method="post" class="ajax-form">
            <?= admin_csrf_input() ?>
            <div class="row g-3">
                <div class="col-lg-8">
                    <label class="form-label">标题</label>
                    <input name="title" class="form-control" placeholder="请输入公告标题" required>
                </div>
                <div class="col-lg-4">
                    <label class="form-label">投放目标</label>
                    <select name="target" id="target-select" class="form-select">
                        <option value="all">全体人员</option>
                        <option value="students">仅学生</option>
                        <option value="teachers">仅教师</option>
                        <option value="major">指定专业</option>
                    </select>
                </div>
                <div class="col-12" id="major-select" style="display:none;">
                    <label class="form-label">选择专业</label>
                    <select name="major_id" class="form-select">
                        <option value="">请选择专业</option>
                        <?php foreach ($majors as $m): ?>
                            <option value="<?= (int)$m['major_id'] ?>"><?= h($m['major_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label">内容</label>
                    <textarea name="content" class="form-control" rows="6" placeholder="请输入公告内容" required></textarea>
                </div>
                <div class="col-12 announcement-form-footer">
                    <div class="form-check mb-0">
                        <input type="checkbox" name="is_pinned" value="1" class="form-check-input" id="isPinned">
                        <label class="form-check-label" for="isPinned">优先显示公告</label>
                    </div>
                    <button type="submit" class="btn btn-primary">发布公告</button>
                </div>
            </div>
        </form>
    </section>

    <section class="announcement-card">
        <div class="announcement-card-header announcement-card-header-stack">
            <div>
                <h4 class="mb-1">公告列表</h4>
                <p class="text-muted mb-0">按关键词、目标和显示方式快速筛选公告。</p>
            </div>
            <div class="announcement-summary">
                共 <strong><?= $total ?></strong> 条公告
            </div>
        </div>

        <form class="row g-2 mb-3 announcement-filter-bar" method="get" id="filter-form">
            <div class="col-lg-4">
                <input name="q" value="<?= h($q) ?>" class="form-control" placeholder="搜索标题或内容">
            </div>
            <div class="col-md-6 col-lg-2">
                <select name="filter_target" id="filter-target" class="form-select">
                    <option value="">全部目标</option>
                    <option value="all" <?= $filter_target === 'all' ? 'selected' : '' ?>>全体</option>
                    <option value="students" <?= $filter_target === 'students' ? 'selected' : '' ?>>学生</option>
                    <option value="teachers" <?= $filter_target === 'teachers' ? 'selected' : '' ?>>教师</option>
                    <option value="major" <?= $filter_target === 'major' ? 'selected' : '' ?>>专业</option>
                </select>
            </div>
            <div class="col-md-6 col-lg-2" id="filter-major-select" style="display: <?= $filter_target === 'major' ? '' : 'none' ?>;">
                <select name="filter_major" class="form-select">
                    <option value="">所有专业</option>
                    <?php foreach ($majors as $m): ?>
                        <option value="<?= (int)$m['major_id'] ?>" <?= $filter_major === (int)$m['major_id'] ? 'selected' : '' ?>><?= h($m['major_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 col-lg-3">
                <select name="filter_pinned" class="form-select">
                    <option value="" <?= $filter_pinned === '' ? 'selected' : '' ?>>全部显示方式</option>
                    <option value="1" <?= $filter_pinned === '1' ? 'selected' : '' ?>>优先显示</option>
                    <option value="0" <?= $filter_pinned === '0' ? 'selected' : '' ?>>普通显示</option>
                </select>
            </div>
            <div class="col-md-4 col-lg-1">
                <button class="btn btn-secondary w-100" type="submit">筛选</button>
            </div>
        </form>

        <div class="table-responsive announcement-table-wrap">
            <table class="table table-striped table-bordered announcement-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>标题</th>
                    <th>作者</th>
                    <th>投放目标</th>
                    <th>发布时间</th>
                    <th>优先显示</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($announcements as $row): ?>
                    <tr>
                        <td><?= (int)$row['announcement_id'] ?></td>
                        <td><?= h(mb_strimwidth($row['title'], 0, 60, '...')) ?></td>
                        <td><?= h($row['author_name'] ?? '') ?></td>
                        <td><?= render_targets_label($row['targets'] ?? '', $majorsMap) ?></td>
                        <td><?= h($row['published_at'] ?? $row['created_at']) ?></td>
                        <td><?= $row['is_pinned'] ? '<span class="badge bg-warning text-dark">优先显示</span>' : '' ?></td>
                        <td>
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-sm btn-secondary btn-edit" data-id="<?= (int)$row['announcement_id'] ?>">编辑</button>
                                <button type="button" class="btn btn-sm btn-danger btn-delete" data-id="<?= (int)$row['announcement_id'] ?>">删除</button>
                                <?php if ($row['is_pinned']): ?>
                                    <button type="button" class="btn btn-sm btn-outline-warning btn-pin" data-id="<?= (int)$row['announcement_id'] ?>" data-pin="0">取消优先显示</button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-sm btn-warning btn-pin" data-id="<?= (int)$row['announcement_id'] ?>" data-pin="1">设为优先显示</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <?php if ($total > 0): ?>
        <?php
            $pagination = admin_paginate($total, $page, $per_page);
            $totalPages = $pagination['total_pages'];
            $start = $pagination['start'];
            $end = $pagination['end'];
        ?>
        <nav aria-label="公告分页" class="mt-3">
          <ul class="pagination">
            <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h(admin_build_query(['page' => $page - 1])) ?>">上一页</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">上一页</span></li>
            <?php endif; ?>

            <?php if ($start > 1): ?>
                <li class="page-item"><a class="page-link" href="<?= h(admin_build_query(['page' => 1])) ?>">1</a></li>
                <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="<?= h(admin_build_query(['page' => $i])) ?>"><?= $i ?></a></li>
            <?php endfor; ?>

            <?php if ($end < $totalPages): ?>
                <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <li class="page-item"><a class="page-link" href="<?= h(admin_build_query(['page' => $totalPages])) ?>"><?= $totalPages ?></a></li>
            <?php endif; ?>

            <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="<?= h(admin_build_query(['page' => $page + 1])) ?>">下一页</a></li>
            <?php else: ?>
                <li class="page-item disabled"><span class="page-link">下一页</span></li>
            <?php endif; ?>
          </ul>
        </nav>
        <?php endif; ?>
    </section>
</div>

<!-- 编辑模态 -->
<div class="modal fade" id="editAnnouncementModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">编辑公告</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div id="edit-error" class="alert alert-danger d-none"></div>
        <form id="edit-ann-form">
            <?= admin_csrf_input() ?>
            <input type="hidden" name="announcement_id" id="edit-ann-id">
            <div class="mb-3">
                <input name="title" id="edit-title" class="form-control" placeholder="标题" required>
            </div>
            <div class="mb-3">
                <textarea name="content" id="edit-content" class="form-control" rows="6" placeholder="内容" required></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">投放目标</label>
                <select name="target" id="edit-target" class="form-select">
                    <option value="all">全体人员</option>
                    <option value="students">仅学生</option>
                    <option value="teachers">仅教师</option>
                    <option value="major">指定专业</option>
                </select>
            </div>
            <div class="mb-3" id="edit-major-box" style="display:none;">
                <label class="form-label">选择专业</label>
                <select name="major_id" id="edit-major" class="form-select">
                    <option value="">请选择专业</option>
                    <?php foreach ($majors as $m): ?>
                        <option value="<?= (int)$m['major_id'] ?>"><?= h($m['major_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" name="is_pinned" id="edit-pinned" class="form-check-input" value="1">
                <label class="form-check-label" for="edit-pinned">优先显示</label>
            </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
        <button type="button" class="btn btn-primary" id="save-edit">保存</button>
      </div>
    </div>
  </div>
</div>

<?php include 'footer.php'; ?>

<script>
const ADMIN_CSRF_TOKEN = <?= json_encode(app_ensure_csrf_token(), JSON_UNESCAPED_UNICODE) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const sel = document.getElementById('target-select');
    const majorBox = document.getElementById('major-select');
    if (sel) {
        sel.addEventListener('change', function () { majorBox.style.display = (sel.value === 'major') ? '' : 'none'; });
    }
    const filterSel = document.getElementById('filter-target');
    const filterMajorBox = document.getElementById('filter-major-select');
    if (filterSel) {
        filterSel.addEventListener('change', function () { if (filterMajorBox) filterMajorBox.style.display = (filterSel.value === 'major') ? '' : 'none'; });
    }

    // Edit button
    document.querySelectorAll('.btn-edit').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const id = btn.getAttribute('data-id');
            fetch(<?= json_encode($adminApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '?act=get_announcement&id=' + encodeURIComponent(id), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(r => r.json())
                .then(data => {
                    if (!data.ok) throw new Error(data.error || '无法获取公告');
                    const a = data.announcement;
                    document.getElementById('edit-ann-id').value = a.announcement_id;
                    document.getElementById('edit-title').value = a.title;
                    document.getElementById('edit-content').value = a.content;
                    document.getElementById('edit-pinned').checked = (a.is_pinned == 1 || a.is_pinned === '1');
                    // targets: pick first target
                    let tgt = 'all';
                    let majorId = '';
                    if (Array.isArray(data.targets) && data.targets.length) {
                        const first = data.targets[0];
                        tgt = first.target_type;
                        if (tgt === 'major') majorId = first.target_id;
                    }
                    document.getElementById('edit-target').value = tgt;
                    document.getElementById('edit-major').value = majorId;
                    document.getElementById('edit-major-box').style.display = (tgt === 'major') ? '' : 'none';
                    const modal = new bootstrap.Modal(document.getElementById('editAnnouncementModal'));
                    modal.show();
                }).catch(err => window.AdminConfirm.alert(err.message));
        });
    });

    document.getElementById('edit-target').addEventListener('change', function () {
        document.getElementById('edit-major-box').style.display = (this.value === 'major') ? '' : 'none';
    });

    document.getElementById('save-edit').addEventListener('click', function () {
        const id = document.getElementById('edit-ann-id').value;
        const title = document.getElementById('edit-title').value;
        const content = document.getElementById('edit-content').value;
        const target = document.getElementById('edit-target').value;
        const major_id = document.getElementById('edit-major').value;
        const is_pinned = document.getElementById('edit-pinned').checked ? 1 : 0;

        const body = new URLSearchParams();
        body.set('announcement_id', id);
        body.set('title', title);
        body.set('content', content);
        body.set('target', target);
        body.set('major_id', major_id);
        body.set('is_pinned', String(is_pinned));
        body.set('csrf_token', ADMIN_CSRF_TOKEN);

        window.AdminUI.postUrlEncoded(
            <?= json_encode($adminApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '?act=update_announcement',
            body
        ).then(result => {
            const data = result.data || null;
            if (!result.ok || !data || !data.ok) throw new Error(data && data.error ? data.error : '更新失败');
            location.reload();
        }).catch(err => {
            const el = document.getElementById('edit-error'); el.textContent = err.message; el.classList.remove('d-none');
        });
    });

    // Delete
    document.querySelectorAll('.btn-delete').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            const ok = await window.AdminConfirm.confirm({
                title: '删除公告',
                message: '确认删除该公告吗？此操作不可撤销。',
                confirmText: '删除',
                confirmClass: 'btn-danger'
            });
            if (!ok) return;
            const id = btn.getAttribute('data-id');
            const body = new URLSearchParams();
            body.set('announcement_id', id);
            body.set('csrf_token', ADMIN_CSRF_TOKEN);
            window.AdminUI.postUrlEncoded(
                <?= json_encode($adminApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '?act=delete_announcement',
                body
            ).then(result => {
                const data = result.data || null;
                if (!result.ok || !data || !data.ok) throw new Error(data && data.error ? data.error : '删除失败');
                location.reload();
            }).catch(err => window.AdminConfirm.alert(err.message));
        });
    });

    // Pin/unpin
    document.querySelectorAll('.btn-pin').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const id = btn.getAttribute('data-id');
            const pin = btn.getAttribute('data-pin');
            const body = new URLSearchParams(); body.set('announcement_id', id); body.set('pin', pin); body.set('csrf_token', ADMIN_CSRF_TOKEN);
            window.AdminUI.postUrlEncoded(
                <?= json_encode($adminApiUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?> + '?act=pin_announcement',
                body
            ).then(result => {
                const data = result.data || null;
                if (!result.ok || !data || !data.ok) throw new Error(data && data.error ? data.error : '操作失败');
                location.reload();
            }).catch(err => window.AdminConfirm.alert(err.message));
        });
    });
});
</script>
