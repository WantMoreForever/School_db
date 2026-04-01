<?php
// ============================================================
// 组件：学生门户侧边栏
// 使用前只需确保已定义 $activePage 和 $pdo（PDO连接）
// ============================================================

$activePage = $activePage ?? '';

// ── 在侧边栏内自行查询学生信息 ────────────────────────────
$student = null;
if ($pdo) {
    $stmt = $pdo->prepare("CALL sp_get_student_base_info(?)");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if ($row) {
$uploadDir     = __DIR__ . '/../uploads/';   // components/ 上一级，即根目录 uploads/
$defaultAvatar = '/uploads/RC.PNG';
$avatarFile    = $row['image'] ?? '';
$avatarPath    = ($avatarFile && file_exists($uploadDir . $avatarFile))
                 ? '/uploads/' . $avatarFile
                 : $defaultAvatar;

        $student = [
            'name'            => $row['name'],
            'student_id'      => $row['student_no'],
            'gender'          => $row['gender'] === 'male' ? '男' : ($row['gender'] === 'female' ? '女' : '其他'),
            'email'           => $row['email'] ?? '',
            'phone'           => $row['phone'] ?? '',
            'grade'           => $row['enrollment_year'],
            'dept_name'       => $row['dept_name'],
            'status'          => $row['status'] === 'active' ? '正常' : ($row['status'] === 'inactive' ? '停用' : '封禁'),
            'avatar_initials' => mb_substr($row['name'], 0, 1),
            'avatar_path'     => $avatarPath,
            'has_avatar'      => ($avatarFile && file_exists($uploadDir . $avatarFile)),
            'enrollment_year' => $row['enrollment_year'],
            'grade_label'     => $row['grade'] ?? '—',
        ];
    }
}
?>
<aside class="sidebar">
    <div class="sidebar-logo">
        <div class="school-name">吉林大学<br>教学管理系统</div>
        <div class="portal-tag">Jilin University Teaching Management System</div>
    </div>
    <div class="sidebar-profile">
        <?php if ($student && $student['has_avatar']): ?>
            <img class="avatar-img"
                 src="<?= htmlspecialchars($student['avatar_path']) ?>"
                 alt="头像"
                 style="width:44px;height:44px;min-width:44px;max-width:44px;border-radius:50%;object-fit:cover;flex-shrink:0;">
        <?php else: ?>
            <div class="avatar-circle"><?= $student ? htmlspecialchars($student['avatar_initials']) : '?' ?></div>
        <?php endif; ?>
        <div>
            <div class="pname"><?= $student ? htmlspecialchars($student['name']) : '未登录' ?></div>
            <div class="pid"><?= $student ? htmlspecialchars($student['student_id']) : '—' ?></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-section-label">概览</div>
        <a class="nav-item <?= $activePage === 'student_portal' ? 'active' : '' ?>" href="student_portal.php"><span class="icon">🏠</span> 首页</a>
        <div class="nav-section-label">学业</div>
        <a class="nav-item <?= $activePage === 'course_select'     ? 'active' : '' ?>" href="course_select.php"><span class="icon">📚</span> 选课系统</a>
        <a class="nav-item <?= $activePage === 'my_grades'  ? 'active' : '' ?>" href="my_grades.php"><span class="icon">📊</span> 成绩查询</a>
        <a class="nav-item <?= $activePage === 'schedule'  ? 'active' : '' ?>" href="schedule.php"><span class="icon">🗓</span> 课程表</a>
        <a class="nav-item <?= $activePage === 'exam_info'  ? 'active' : '' ?>" href="exam_info.php"><span class="icon">📝</span> 考试相关</a>
        <div class="nav-section-label">账户</div>
        <a class="nav-item <?= $activePage === 'profile'    ? 'active' : '' ?>" href="profile.php"><span class="icon">👤</span> 个人信息</a>
        <a class="nav-item <?= $activePage === 'change_pwd' ? 'active' : '' ?>" href="change_pwd.php"><span class="icon">🔒</span> 修改密码</a>
        <a class="nav-item" href="login.php" onclick="return confirm('确认退出登录？')"><span class="icon">🚪</span> 退出登录</a>
    </nav>
    <div class="sidebar-footer">v2.0 · school_db</div>
</aside>