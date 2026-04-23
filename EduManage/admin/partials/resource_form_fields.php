<?php
/**
 * admin/partials/resource_form_fields.php
 * 资源表单字段片段：复用资源类页面的新增与编辑表单字段。
 */
$formContext = $formContext ?? [];
$resourceType = $formContext['type'] ?? 'department';
$isEdit = !empty($formContext['is_edit']);
$row = $formContext['row'] ?? [];
$typeOptions = [
    'normal' => '普通',
    'multimedia' => '多媒体',
    'lab' => '机房',
];
?>
<div class="alert alert-danger d-none modal-error" role="alert"></div>
<?= admin_csrf_input() ?>
<?php if ($resourceType === 'department'): ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="dept_id" value="<?= (int) ($row['dept_id'] ?? 0) ?>">
    <?php endif; ?>
    <div class="mb-3">
        <label class="form-label">院系代码</label>
        <input name="dept_code" class="form-control" required maxlength="10" pattern="[A-Z]{1,10}" title="请输入1-10位大写字母院系代码" style="text-transform:uppercase;" oninput="this.value=this.value.toUpperCase()" value="<?= h((string) ($row['dept_code'] ?? '')) ?>" placeholder="例如：CS">
    </div>
    <div class="mb-3">
        <label class="form-label">院系名称</label>
        <input name="dept_name" class="form-control" required maxlength="100" value="<?= h((string) ($row['dept_name'] ?? '')) ?>" placeholder="例如：计算机学院">
    </div>
    <?php if ($isEdit): ?>
        <div class="row g-2">
            <div class="col-md-4">
                <div class="form-control bg-light">教师：<?= h((string) ($row['teacher_count'] ?? 0)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="form-control bg-light">学生：<?= h((string) ($row['student_count'] ?? 0)) ?></div>
            </div>
            <div class="col-md-4">
                <div class="form-control bg-light">专业：<?= h((string) ($row['major_count'] ?? 0)) ?></div>
            </div>
        </div>
    <?php endif; ?>
<?php else: ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="classroom_id" value="<?= (int) ($row['classroom_id'] ?? 0) ?>">
    <?php endif; ?>
    <div class="mb-3">
        <label class="form-label">教学楼</label>
        <input name="building" class="form-control" required value="<?= h((string) ($row['building'] ?? '')) ?>" placeholder="例如：1号楼">
    </div>
    <div class="mb-3">
        <label class="form-label">房间号</label>
        <input name="room_number" class="form-control" required value="<?= h((string) ($row['room_number'] ?? '')) ?>" placeholder="例如：101">
    </div>
    <div class="mb-3">
        <label class="form-label">容量</label>
        <input type="number" name="capacity" class="form-control" required value="<?= h((string) ($row['capacity'] ?? 50)) ?>" min="1">
    </div>
    <div class="mb-3">
        <label class="form-label">类型</label>
        <select name="type" class="form-select" required>
            <?php foreach ($typeOptions as $value => $label): ?>
                <option value="<?= h($value) ?>" <?= (($row['type'] ?? 'normal') === $value) ? 'selected' : '' ?>><?= h($label) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
