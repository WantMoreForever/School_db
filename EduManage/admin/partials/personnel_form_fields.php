<?php
/**
 * admin/partials/personnel_form_fields.php
 * 人员表单字段片段：复用学生/教师新增与编辑表单的通用输入项。
 */
$formContext = $formContext ?? [];
$personnelType = $formContext['type'] ?? 'student';
$isEdit = !empty($formContext['is_edit']);
$row = $formContext['row'] ?? [];
$departments = $formContext['departments'] ?? [];
$titleOptions = ['教授', '副教授', '辅导员', '讲师'];
$selectedGender = (string) ($row['gender'] ?? 'male');
$selectedDeptId = (int) ($row['dept_id'] ?? 0);
?>
<div class="alert alert-danger d-none modal-error" role="alert"></div>
<?= admin_csrf_input() ?>
<?php if ($isEdit): ?>
    <input type="hidden" name="user_id" value="<?= (int) ($row['user_id'] ?? 0) ?>">
    <?php
    $hasAvatar = false;
    if (!empty($row['image'])) {
        $avatarFsPath = app_avatar_dir() . DIRECTORY_SEPARATOR . basename((string) $row['image']);
        $hasAvatar = is_file($avatarFsPath);
    }
    ?>
    <div class="mb-2">当前头像：
        <?php if ($hasAvatar): ?>
            <img src="<?= h(app_avatar_url((string) $row['image'])) ?>" alt="avatar" style="width:48px;height:48px;border-radius:4px;object-fit:cover;">
        <?php else: ?>
            <span>无</span>
        <?php endif; ?>
    </div>
<?php endif; ?>
<div class="mb-3">
    <input name="name" class="form-control" placeholder="姓名" required value="<?= h((string) ($row['name'] ?? '')) ?>">
</div>
<div class="mb-3">
    <input name="email" type="email" class="form-control" placeholder="邮箱" required inputmode="email" pattern="[A-Za-z0-9._%+-]+@school\.edu" title="请输入以 @school.edu 结尾的邮箱" value="<?= h((string) ($row['email'] ?? '')) ?>">
</div>
<div class="mb-3">
    <?php if ($isEdit): ?>
        <div class="input-group">
            <input name="pwd" type="text" class="form-control pwd-input" placeholder="留空则不修改密码">
            <button type="button" class="btn btn-outline-secondary gen-pwd">生成随机密码</button>
        </div>
        <div class="form-text">系统不显示原密码，如需改密可在这里直接生成并保存。</div>
    <?php else: ?>
        <input name="pwd" class="form-control" placeholder="密码" required>
    <?php endif; ?>
</div>
<?php if ($personnelType === 'teacher'): ?>
    <div class="mb-3">
        <label class="form-label">职称</label>
        <select name="title" class="form-select" required>
            <?php foreach ($titleOptions as $title): ?>
                <option value="<?= h($title) ?>" <?= (($row['title'] ?? '') === $title) ? 'selected' : '' ?>><?= h($title) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<div class="mb-3">
    <label class="form-label">性别</label>
    <select name="gender" class="form-select">
        <option value="male" <?= $selectedGender === 'male' ? 'selected' : '' ?>>男</option>
        <option value="female" <?= $selectedGender === 'female' ? 'selected' : '' ?>>女</option>
        <option value="other" <?= $selectedGender === 'other' ? 'selected' : '' ?>>其他</option>
    </select>
</div>
<div class="mb-3">
    <input name="phone" class="form-control" placeholder="手机号" inputmode="numeric" maxlength="11" pattern="\d{11}" title="请输入11位数字手机号" value="<?= h((string) ($row['phone'] ?? '')) ?>">
</div>
<div class="mb-3">
    <label class="form-label">头像（可选）</label>
    <input type="file" name="avatar" accept="image/*" class="form-control">
</div>
<?php if ($personnelType === 'student'): ?>
    <div class="mb-3">
        <input name="student_no" class="form-control" placeholder="学号" required inputmode="numeric" maxlength="8" pattern="\d{8}" title="请输入8位数字学号" value="<?= h((string) ($row['student_no'] ?? '')) ?>">
    </div>
<?php endif; ?>
<div class="mb-3">
    <label class="form-label">所属院系</label>
    <select name="dept_id" class="form-select" <?= $isEdit ? '' : 'required' ?>>
        <option value="">请选择院系</option>
        <?php foreach ($departments as $dept): ?>
            <option value="<?= (int) $dept['dept_id'] ?>" <?= ((int) $dept['dept_id'] === $selectedDeptId) ? 'selected' : '' ?>><?= h($dept['dept_name']) ?></option>
        <?php endforeach; ?>
    </select>
</div>
