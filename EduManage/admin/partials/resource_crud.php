<?php
/**
 * admin/partials/resource_crud.php
 * 资源通用 CRUD 片段：渲染课程、教室、院系、专业等资源列表与表单。
 */
$resourceConfig = $resourceConfig ?? [];
$records = $records ?? [];
$resourceType = $resourceConfig['type'] ?? 'department';
$entityLabel = $resourceConfig['entity_label'] ?? ($resourceType === 'classroom' ? '教室' : '院系');
$pageHeading = $resourceConfig['page_heading'] ?? ($entityLabel . '管理');
$pageSubtitle = $resourceConfig['page_subtitle'] ?? '';
$listTitle = $resourceConfig['list_title'] ?? ($entityLabel . '列表');
$addAction = $resourceConfig['add_action'] ?? '';
$updateAction = $resourceConfig['update_action'] ?? '';
$emptyText = $resourceConfig['empty_text'] ?? ('暂无' . $entityLabel . '数据');
$countUnit = $resourceConfig['count_unit'] ?? '个';
$idField = $resourceConfig['id_field'] ?? ($resourceType === 'classroom' ? 'classroom_id' : 'dept_id');
$actionWidth = $resourceConfig['action_width'] ?? ($resourceType === 'classroom' ? '200px' : '160px');
$addTitle = '新增' . $entityLabel;
$editTitle = '编辑' . $entityLabel;
$classroomTypeLabels = [
    'normal' => '普通',
    'multimedia' => '多媒体',
    'lab' => '机房',
];
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
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addModal"><?= h($addTitle) ?></button>
        </div>
    </section>

    <section class="admin-section-card admin-table-card">
        <div class="admin-section-head">
            <div>
                <h2 class="admin-section-title"><?= h($listTitle) ?></h2>
                <p class="admin-section-meta">共 <?= count($records) ?> <?= h($countUnit . $entityLabel) ?></p>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-bordered table-striped bg-white align-middle">
                <thead>
                    <?php if ($resourceType === 'department'): ?>
                        <tr>
                            <th>ID</th>
                            <th>院系代码</th>
                            <th>院系名称</th>
                            <th>教师数</th>
                            <th>学生数</th>
                            <th>专业数</th>
                            <th style="width: <?= h($actionWidth) ?>;">操作</th>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th>ID</th>
                            <th>教学楼</th>
                            <th>房间号</th>
                            <th>容量</th>
                            <th>类型</th>
                            <th style="width: <?= h($actionWidth) ?>;">操作</th>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($records as $row): ?>
                        <?php $recordId = (int) ($row[$idField] ?? 0); ?>
                        <tr>
                            <?php if ($resourceType === 'department'): ?>
                                <td><?= h($row['dept_id']) ?></td>
                                <td><?= h($row['dept_code']) ?></td>
                                <td><?= h($row['dept_name']) ?></td>
                                <td><?= h($row['teacher_count']) ?></td>
                                <td><?= h($row['student_count']) ?></td>
                                <td><?= h($row['major_count']) ?></td>
                            <?php else: ?>
                                <td><?= h($row['classroom_id']) ?></td>
                                <td><?= h($row['building']) ?></td>
                                <td><?= h($row['room_number']) ?></td>
                                <td><?= h($row['capacity']) ?></td>
                                <td><?= h($classroomTypeLabels[$row['type'] ?? 'normal'] ?? '普通') ?></td>
                            <?php endif; ?>
                            <td>
                                <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#editModal-<?= $recordId ?>">编辑</button>
                            </td>
                        </tr>

                        <div class="modal fade" id="editModal-<?= $recordId ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><?= h($editTitle) ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form action="<?= h($adminApiUrl) ?>?act=<?= h($updateAction) ?>" method="post" class="ajax-form">
                                        <div class="modal-body">
                                            <?php
                                            $formContext = [
                                                'type' => $resourceType,
                                                'is_edit' => true,
                                                'row' => $row,
                                            ];
                                            require app_admin_partial_path('resource_form_fields');
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
                        <tr><td colspan="<?= $resourceType === 'department' ? '7' : '6' ?>" class="admin-empty-row"><?= h($emptyText) ?></td></tr>
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
            <form action="<?= h($adminApiUrl) ?>?act=<?= h($addAction) ?>" method="post" class="ajax-form">
                <div class="modal-body">
                    <?php
                    $formContext = [
                        'type' => $resourceType,
                        'is_edit' => false,
                        'row' => [],
                    ];
                    require app_admin_partial_path('resource_form_fields');
                    ?>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
</div>
