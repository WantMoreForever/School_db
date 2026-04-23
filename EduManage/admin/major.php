<?php
/**
 * admin/major.php
 * 专业管理页面：维护专业信息，并兼容不同 major 表结构。
 */
require 'common.php';
$pdo = app_require_pdo();
admin_auth();
$page_title = '专业管理 - 管理后台';
$adminApiUrl = app_catalog_url('admin', 'api', 'main');

$departments = admin_fetch_departments($pdo);

// 构建 dept_id -> dept_name 的映射，作为回填用
$deptMap = admin_index_rows_by_int_key($departments, 'dept_id', 'dept_name');

// 是否显示院系下拉（当有院系列表时显示，让管理员能选择）
$showDeptSelect = !empty($departments);

$majorData = admin_fetch_majors_for_management($pdo);
$majors = $majorData['rows'];
$hasDeptColumn = $majorData['columns']['has_dept'];
$hasMajorCode = $majorData['columns']['has_code'];
$hasMajorName = $majorData['columns']['has_name'];
require 'layout_head.php';
?>
<div class="admin-page">
    <?php $pageAlert = admin_page_alert(); ?>
    <?php if ($pageAlert): ?>
        <div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div>
    <?php endif; ?>

    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title">专业管理</h1>
        </div>
        <div class="admin-page-actions">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal">新增专业</button>
        </div>
    </section>

    <section class="admin-section-card admin-table-card">
        <div class="admin-section-head">
            <div>
                <h2 class="admin-section-title">专业列表</h2>
                <p class="admin-section-meta">共 <?= count($majors) ?> 个专业</p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped bg-white align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($hasMajorCode): ?><th>专业代码</th><?php endif; ?>
                        <th>专业名称</th>
                        <th>所属院系</th>
                        <th style="width: 200px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($majors as $row): ?>
                        <tr>
                            <td><?= h($row['major_id']) ?></td>
                            <?php if ($hasMajorCode): ?><td><?= h($row['major_code'] ?? '') ?></td><?php endif; ?>
                            <td><?= h($row['major_name'] ?? '') ?></td>
                            <td><?= h($row['dept_name'] ?? ($deptMap[(int)($row['dept_id'] ?? 0)] ?? '')) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editModal-<?= (int) $row['major_id'] ?>">编辑</button>
                                </div>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal-<?= (int) $row['major_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">编辑专业</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="<?= h($adminApiUrl) ?>?act=update_major" method="post" class="ajax-form">
                                        <div class="modal-body">
                                            <div class="alert alert-danger d-none modal-error" role="alert"></div>
                                            <?= admin_csrf_input() ?>
                                            <input type="hidden" name="major_id" value="<?= (int) $row['major_id'] ?>">
                                            <?php if ($hasMajorCode): ?>
                                            <div class="mb-3">
                                                <label class="form-label">专业代码</label>
                                                <input name="major_code" class="form-control" required maxlength="10" pattern="[A-Z]{1,10}" title="请输入1-10位大写字母专业代码" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()" value="<?= h($row['major_code'] ?? '') ?>">
                                            </div>
                                            <?php endif; ?>
                                            <div class="mb-3">
                                                <label class="form-label">专业名称</label>
                                                <input name="major_name" class="form-control" required value="<?= h($row['major_name']) ?>">
                                            </div>
                                            <?php if ($showDeptSelect): ?>
                                            <div class="mb-3">
                                                <label class="form-label">所属院系</label>
                                                <select name="dept_id" class="form-select" required>
                                                    <option value="">请选择院系</option>
                                                    <?php foreach ($departments as $dept): ?>
                                                        <option value="<?= (int) $dept['dept_id'] ?>" <?= ((int) $dept['dept_id'] === (int) ($row['dept_id'] ?? 0)) ? 'selected' : '' ?>><?= h($dept['dept_name']) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php if (!$hasDeptColumn): ?>
                                                    <div class="form-text text-warning">当前 `major` 表不包含 `dept_id` 字段，选择的院系不会被保存。</div>
                                                <?php endif; ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">保存</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if(empty($majors)): ?>
                    <tr><td colspan="<?= $hasMajorCode ? 5 : 4 ?>" class="admin-empty-row">暂无专业数据</td></tr>
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
                <h5 class="modal-title">新增专业</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= h($adminApiUrl) ?>?act=add_major" method="post" class="ajax-form">
                <div class="modal-body">
                    <div class="alert alert-danger d-none modal-error" role="alert"></div>
                    <?= admin_csrf_input() ?>
                    <?php if ($hasMajorCode): ?>
                    <div class="mb-3">
                        <label class="form-label">专业代码</label>
                        <input name="major_code" class="form-control" required maxlength="10" pattern="[A-Z]{1,10}" title="请输入1-10位大写字母专业代码" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()" placeholder="例如：CS">
                    </div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label">专业名称</label>
                        <input name="major_name" class="form-control" required placeholder="例如：计算机科学与技术">
                    </div>
                    <?php if ($showDeptSelect): ?>
                    <div class="mb-3">
                        <label class="form-label">所属院系</label>
                        <select name="dept_id" class="form-select" required>
                            <option value="">请选择院系</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['dept_id'] ?>"><?= h($dept['dept_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$hasDeptColumn): ?>
                            <div class="form-text text-warning">当前 `major` 表不包含 `dept_id` 字段，选择的院系不会被保存。</div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>
