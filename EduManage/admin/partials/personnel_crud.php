<?php
/**
 * admin/partials/personnel_crud.php
 * 人员通用 CRUD 片段：渲染学生/教师列表、状态切换和编辑弹窗。
 */
$crudConfig = $crudConfig ?? [];
$records = $records ?? [];
$personnelType = $crudConfig['type'] ?? 'student';
$entityLabel = $crudConfig['entity_label'] ?? ($personnelType === 'teacher' ? '教师' : '学生');
$pageHeading = $crudConfig['page_heading'] ?? ($entityLabel . '管理');
$pageSubtitle = $crudConfig['page_subtitle'] ?? '';
$listTitle = $crudConfig['list_title'] ?? ($entityLabel . '列表');
$addAction = $crudConfig['add_action'] ?? '';
$updateAction = $crudConfig['update_action'] ?? '';
$redirect = $crudConfig['redirect'] ?? '';
$importModalId = $crudConfig['import_modal_id'] ?? '';
$emptyText = $crudConfig['empty_text'] ?? ('暂无' . $entityLabel . '数据');
$countText = '共 ' . count($records) . ' 名' . $entityLabel;
$extraColumnLabel = $personnelType === 'teacher' ? '职称' : '学号';
$editTitle = '编辑' . $entityLabel;
$addTitle = '新增' . $entityLabel;
?>
<div class="admin-page">
    <?php $pageAlert = admin_page_alert(); ?>
    <?php if ($pageAlert): ?>
        <div class="alert alert-<?= h($pageAlert['type']) ?>"><?= h($pageAlert['message']) ?></div>
    <?php endif; ?>

    <section class="admin-page-header">
        <div>
            <h1 class="admin-page-title"><?= h($pageHeading) ?></h1>
            <p class="admin-page-subtitle"><?= h($pageSubtitle) ?></p>
        </div>
        <div class="admin-page-actions">
            <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#<?= h($importModalId) ?>">批量导入</button>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><?= h($addTitle) ?></button>
        </div>
    </section>

    <section class="admin-section-card admin-table-card">
        <div class="admin-section-head">
            <div>
                <h2 class="admin-section-title"><?= h($listTitle) ?></h2>
                <p class="admin-section-meta"><?= h($countText) ?></p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped bg-white align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <?php if ($personnelType === 'student'): ?>
                            <th><?= h($extraColumnLabel) ?></th>
                            <th>姓名</th>
                        <?php else: ?>
                            <th>姓名</th>
                            <th><?= h($extraColumnLabel) ?></th>
                        <?php endif; ?>
                        <th>院系</th>
                        <th>邮箱</th>
                        <th>状态</th>
                        <th style="width: 320px;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <tr>
                            <td><?= h($row['user_id']) ?></td>
                            <?php if ($personnelType === 'student'): ?>
                                <td><?= h($row['student_no']) ?></td>
                                <td><?= h($row['name']) ?></td>
                            <?php else: ?>
                                <td><?= h($row['name']) ?></td>
                                <td><?= h($row['title']) ?></td>
                            <?php endif; ?>
                            <td><?= h($row['dept_name']) ?></td>
                            <td><?= h($row['email']) ?></td>
                            <td><?= h($row['status']) ?></td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editModal-<?= (int) $row['user_id'] ?>">编辑</button>
                                    <form action="<?= h($adminApiUrl) ?>?act=toggle_status" method="post" class="d-inline">
                                        <?= admin_csrf_input() ?>
                                        <input type="hidden" name="id" value="<?= (int) $row['user_id'] ?>">
                                        <input type="hidden" name="redirect" value="<?= h($redirect) ?>">
                                        <button type="submit" class="btn btn-sm btn-warning"><?= $row['status'] === 'active' ? '禁用' : '启用' ?></button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-info btn-reset-pw" data-user-id="<?= (int) $row['user_id'] ?>" data-redirect="<?= h($redirect) ?>" data-name="<?= h($row['name']) ?>">重置密码</button>
                                </div>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal-<?= (int) $row['user_id'] ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?= h($editTitle) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="<?= h($adminApiUrl) ?>?act=<?= h($updateAction) ?>" method="post" class="ajax-form" enctype="multipart/form-data">
                                        <div class="modal-body">
                                            <?php
                                            $formContext = [
                                                'type' => $personnelType,
                                                'is_edit' => true,
                                                'row' => $row,
                                                'departments' => $departments,
                                            ];
                                            require app_admin_partial_path('personnel_form_fields');
                                            ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="submit" class="btn btn-primary">保存</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($records)): ?>
                        <tr><td colspan="7" class="admin-empty-row"><?= h($emptyText) ?></td></tr>
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
                <h5 class="modal-title"><?= h($addTitle) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= h($adminApiUrl) ?>?act=<?= h($addAction) ?>" method="post" class="ajax-form" enctype="multipart/form-data">
                <div class="modal-body">
                    <?php
                    $formContext = [
                        'type' => $personnelType,
                        'is_edit' => false,
                        'row' => [],
                        'departments' => $departments,
                    ];
                    require app_admin_partial_path('personnel_form_fields');
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
