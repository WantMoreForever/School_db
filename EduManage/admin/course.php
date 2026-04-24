<?php
/**
 * admin/course.php
 * 课程管理页面：负责课程的新增、编辑与列表展示。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$page_title = '课程管理 - 管理后台';
$adminApiUrl = app_catalog_url('admin', 'api', 'main');

$courses = admin_fetch_courses($pdo);
require 'layout_head.php';
?>
<div class="admin-page">
    <?php $pageAlert = admin_page_alert(); ?>
    <?php if ($pageAlert): ?>
        <div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div>
    <?php endif; ?>

    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title">课程管理</h1>

        </div>
        <div class="admin-page-actions">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">新增课程</button>
        </div>
    </section>

    <section class="admin-section-card admin-table-card">
        <div class="admin-section-head">
            <div>
                <h2 class="admin-section-title">课程列表</h2>
                <p class="admin-section-meta">共 <?= count($courses) ?> 门课程</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped bg-white align-middle">
                <thead>
                    <tr>
                        <th>课程 ID</th>
                        <th>课程名称</th>
                        <th>学分</th>
                        <th>学时</th>
                        <th>课程描述</th>
                        <th style="width: 180px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($courses as $row): ?>
                        <tr>
                            <td><?= h($row['course_id']) ?></td>
                            <td><?= h($row['name']) ?></td>
                            <td><?= h($row['credit']) ?></td>
                            <td><?= h($row['hours']) ?></td>
                            <td><?= h($row['description']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-secondary btn-edit-course"
                                        data-course-id="<?= h($row['course_id']) ?>"
                                        data-name="<?= h($row['name']) ?>"
                                        data-credit="<?= h($row['credit']) ?>"
                                        data-hours="<?= h($row['hours']) ?>"
                                        data-description="<?= h($row['description']) ?>">
                                        编辑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($courses)): ?>
                        <tr><td colspan="6" class="admin-empty-row">暂无课程数据</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">新增课程</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= h($adminApiUrl) ?>?act=add_course" method="post" class="ajax-form">
                <div class="modal-body">
                    <div class="alert alert-danger d-none modal-error" role="alert"></div>
                    <?= admin_csrf_input() ?>
                    <div class="mb-3">
                        <input name="name" class="form-control" placeholder="课程名称" required>
                    </div>
                    <div class="mb-3">
                        <input name="credit" type="number" step="0.1" class="form-control" placeholder="学分" required>
                    </div>
                    <div class="mb-3">
                        <input name="hours" type="number" class="form-control" placeholder="学时" required>
                    </div>
                    <div class="mb-3">
                        <textarea name="description" class="form-control" placeholder="课程描述" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">编辑课程</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="editForm" action="<?= h($adminApiUrl) ?>?act=update_course" method="post" class="ajax-form">
                <div class="modal-body">
                    <div class="alert alert-danger d-none modal-error" role="alert"></div>
                    <?= admin_csrf_input() ?>
                    <input type="hidden" name="course_id" id="edit_course_id">
                    <div class="mb-3">
                        <label class="form-label">课程名称</label>
                        <input name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">学分</label>
                        <input name="credit" id="edit_credit" type="number" step="0.1" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">学时</label>
                        <input name="hours" id="edit_hours" type="number" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">课程描述</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-edit-course').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('edit_course_id').value = this.getAttribute('data-course-id') || '';
            document.getElementById('edit_name').value = this.getAttribute('data-name') || '';
            document.getElementById('edit_credit').value = this.getAttribute('data-credit') || '';
            document.getElementById('edit_hours').value = this.getAttribute('data-hours') || '';
            document.getElementById('edit_description').value = this.getAttribute('data-description') || '';

            const modal = new bootstrap.Modal(document.getElementById('editModal'));
            modal.show();
        });
    });
});
</script>
<?php require 'footer.php'; ?>
