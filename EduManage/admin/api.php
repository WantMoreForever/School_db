<?php
require 'common.php';
$pdo = app_require_pdo();

if ($pdo === null) {
    http_response_code(500);
    exit('数据库连接失败');
}

admin_auth();
require_once __DIR__ . '/../components/logger.php';

$act = $_GET['act'] ?? '';
// 简单判断是否为 AJAX 请求
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
if ($isAjax) {
    header('Content-Type: application/json; charset=utf-8');
}

// 管理后台统一 CSRF 校验（对会修改数据的操作生效）
function admin_require_csrf(): void
{
    global $isAjax;
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!app_validate_csrf($token)) {
        if ($isAjax) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'error' => 'CSRF 验证失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: index.php?error=' . urlencode('CSRF 验证失败'));
        exit;
    }
}

// 针对危险操作启用 CSRF 强制检查
$unsafeActs = ['add_student','del_student','add_teacher','del_teacher','toggle_status','reset_password','update_student','update_teacher','add_announcement','update_announcement','delete_announcement','pin_announcement','add_schedule','update_schedule','del_schedule','add_department','update_department'];
if (in_array($act, $unsafeActs, true)) {
    admin_require_csrf();
}

// helper: handle avatar upload, return filename or null; on error returns array with error key
function handle_avatar_upload() {
    if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) return null;
    $f = $_FILES['avatar'];
    if ($f['error'] !== UPLOAD_ERR_OK) return ['error' => '上传头像失败'];
    if ($f['size'] > 2 * 1024 * 1024) return ['error' => '头像文件过大，最大 2MB'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($f['tmp_name']);
    $allowed = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp'];
    if (!isset($allowed[$mime])) return ['error' => '不支持的图片格式'];
    $ext = $allowed[$mime];
    $uploadDir = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR;
    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $dest = $uploadDir . $filename;
    if (!move_uploaded_file($f['tmp_name'], $dest)) return ['error' => '保存头像失败'];
    return $filename;
}

require_once __DIR__ . '/api/shared.php';

// ==================== 排课辅助函数 ====================
function admin_action_error(string $redirect, string $message, int $status = 400): void
{
    global $isAjax;

    if ($isAjax) {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: ' . $redirect . '?error=' . urlencode($message));
    exit;
}

function admin_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function admin_validate_school_email(string $email): ?string
{
    if ($email === '') {
        return '邮箱不能为空';
    }
    if (!preg_match('/^[A-Za-z0-9._%+-]+@school\.edu$/', $email)) {
        return '邮箱必须是以 @school.edu 结尾的有效地址';
    }

    return null;
}

function admin_validate_phone(string $phone): ?string
{
    if ($phone === '') {
        return null;
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        return '手机号必须为11位数字';
    }

    return null;
}

function admin_validate_student_no(string $studentNo): ?string
{
    if (!preg_match('/^\d{8}$/', $studentNo)) {
        return '学号必须为8位数字';
    }

    return null;
}

function admin_normalize_major_code(string $majorCode): string
{
    return strtoupper(trim($majorCode));
}

function admin_validate_major_code(string $majorCode): ?string
{
    if (!preg_match('/^[A-Z]{1,10}$/', $majorCode)) {
        return '专业代码必须为1-10位大写字母';
    }

    return null;
}

function admin_normalize_department_code(string $deptCode): string
{
    return strtoupper(trim($deptCode));
}

function admin_validate_department_code(string $deptCode): ?string
{
    if (!preg_match('/^[A-Z]{1,10}$/', $deptCode)) {
        return '院系代码必须为 1-10 位大写字母';
    }

    return null;
}

function schedule_response_error(string $message, int $status = 400): void
{
    global $isAjax;

    if ($isAjax) {
        http_response_code($status);
        echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: schedule_manage.php?error=' . urlencode($message));
    exit;
}

function schedule_response_success(string $message): void
{
    global $isAjax;

    if ($isAjax) {
        echo json_encode(['ok' => true, 'message' => $message], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: schedule_manage.php?success=' . urlencode($message));
    exit;
}

function schedule_day_name_cn(int $day): string
{
    $days = [
        1 => '星期一',
        2 => '星期二',
        3 => '星期三',
        4 => '星期四',
        5 => '星期五',
        6 => '星期六',
        7 => '星期日',
    ];

    return $days[$day] ?? ('星期' . $day);
}

function schedule_slot_map(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT slot_id, slot_name, start_time, end_time FROM time_slot ORDER BY start_time, end_time, slot_id");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $index => $row) {
        $row['slot_id'] = (int) $row['slot_id'];
        $row['_index'] = $index;
        $map[$row['slot_id']] = $row;
    }

    return $map;
}

function schedule_resolve_slot_range(PDO $pdo, int $slotStartId, int $slotEndId): array
{
    $slots = schedule_slot_map($pdo);
    if (!isset($slots[$slotStartId])) {
        schedule_response_error('开始节次不存在，请重新选择。');
    }
    if (!isset($slots[$slotEndId])) {
        schedule_response_error('结束节次不存在，请重新选择。');
    }

    $startSlot = $slots[$slotStartId];
    $endSlot = $slots[$slotEndId];
    if ($startSlot['_index'] > $endSlot['_index']) {
        schedule_response_error('结束节次不能早于开始节次。请至少选择 1 个完整时间槽。');
    }

    return [
        'start_time' => $startSlot['start_time'],
        'end_time' => $endSlot['end_time'],
    ];
}

function schedule_validate_common(PDO $pdo, array $input): array
{
    $scheduleId = (int) ($input['schedule_id'] ?? 0);
    $courseId = (int) ($input['course_id'] ?? 0);
    $teacherId = (int) ($input['teacher_id'] ?? 0);
    $year = (int) ($input['year'] ?? 0);
    $semester = trim((string) ($input['semester'] ?? ''));
    $capacity = (int) ($input['capacity'] ?? 0);
    $dayOfWeek = (int) ($input['day_of_week'] ?? 0);
    $slotStartId = (int) ($input['slot_start_id'] ?? 0);
    $slotEndId = (int) ($input['slot_end_id'] ?? 0);
    $weekStart = (int) ($input['week_start'] ?? 0);
    $weekEnd = (int) ($input['week_end'] ?? 0);
    $classroomId = (int) ($input['classroom_id'] ?? 0);

    if ($courseId <= 0) {
        schedule_response_error('请选择课程。');
    }
    if ($teacherId <= 0) {
        schedule_response_error('请选择任课教师。');
    }
    if ($year < 2000 || $year > 2099) {
        schedule_response_error('学年格式不正确，请填写 2000 到 2099 之间的年份。');
    }
    if (!in_array($semester, ['Spring', 'Fall'], true)) {
        schedule_response_error('学期只能是 Spring 或 Fall。');
    }
    if ($capacity <= 0) {
        schedule_response_error('容量必须大于 0。');
    }
    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        schedule_response_error('星期参数不正确。');
    }
    if ($weekStart <= 0 || $weekEnd <= 0) {
        schedule_response_error('周次必须为正整数。');
    }
    if ($weekStart > $weekEnd) {
        schedule_response_error('开始周不能大于结束周。');
    }
    if ($classroomId <= 0) {
        schedule_response_error('请选择教室。');
    }

    $slotRange = schedule_resolve_slot_range($pdo, $slotStartId, $slotEndId);

    $courseStmt = $pdo->prepare("SELECT course_id, name FROM course WHERE course_id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        schedule_response_error('所选课程不存在，请刷新页面后重试。');
    }

    $teacherStmt = $pdo->prepare("
        SELECT t.user_id, u.name
        FROM teacher t
        JOIN user u ON u.user_id = t.user_id
        WHERE t.user_id = ?
        LIMIT 1
    ");
    $teacherStmt->execute([$teacherId]);
    $teacher = $teacherStmt->fetch(PDO::FETCH_ASSOC);
    if (!$teacher) {
        schedule_response_error('所选教师不存在，请刷新页面后重试。');
    }

    $classroomStmt = $pdo->prepare("SELECT classroom_id, building, room_number FROM classroom WHERE classroom_id = ? LIMIT 1");
    $classroomStmt->execute([$classroomId]);
    $classroom = $classroomStmt->fetch(PDO::FETCH_ASSOC);
    if (!$classroom) {
        schedule_response_error('所选教室不存在，请刷新页面后重试。');
    }

    return [
        'schedule_id' => $scheduleId,
        'course_id' => $courseId,
        'teacher_id' => $teacherId,
        'year' => $year,
        'semester' => $semester,
        'capacity' => $capacity,
        'day_of_week' => $dayOfWeek,
        'slot_start_id' => $slotStartId,
        'slot_end_id' => $slotEndId,
        'week_start' => $weekStart,
        'week_end' => $weekEnd,
        'classroom_id' => $classroomId,
        'start_time' => $slotRange['start_time'],
        'end_time' => $slotRange['end_time'],
        'course_name' => $course['name'],
        'teacher_name' => $teacher['name'],
        'classroom_name' => $classroom['building'] . '-' . $classroom['room_number'],
    ];
}

function schedule_find_teacher_conflicts(PDO $pdo, int $teacherId, int $dayOfWeek, string $startTime, string $endTime, int $weekStart, int $weekEnd, int $excludeScheduleId = 0): array
{
    $stmt = $pdo->prepare("
        SELECT
            sch.schedule_id,
            c.name AS course_name,
            COALESCE(sch.week_start, 1) AS week_start,
            COALESCE(sch.week_end, 20) AS week_end,
            sch.start_time,
            sch.end_time,
            cl.building,
            cl.room_number
        FROM schedule sch
        JOIN section sec ON sec.section_id = sch.section_id
        JOIN course c ON c.course_id = sec.course_id
        JOIN teaching tg ON tg.section_id = sch.section_id
        LEFT JOIN classroom cl ON cl.classroom_id = sch.classroom_id
        WHERE tg.teacher_id = ?
          AND sch.day_of_week = ?
          AND sch.schedule_id <> ?
          AND ? < sch.end_time
          AND ? > sch.start_time
          AND ? <= COALESCE(sch.week_end, 20)
          AND ? >= COALESCE(sch.week_start, 1)
        ORDER BY sch.start_time, sch.schedule_id
    ");
    $stmt->execute([$teacherId, $dayOfWeek, $excludeScheduleId, $startTime, $endTime, $weekStart, $weekEnd]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function schedule_find_room_conflicts(PDO $pdo, int $classroomId, int $dayOfWeek, string $startTime, string $endTime, int $weekStart, int $weekEnd, int $excludeScheduleId = 0): array
{
    $stmt = $pdo->prepare("
        SELECT
            sch.schedule_id,
            c.name AS course_name,
            COALESCE(sch.week_start, 1) AS week_start,
            COALESCE(sch.week_end, 20) AS week_end,
            sch.start_time,
            sch.end_time,
            MAX(u.name) AS teacher_name
        FROM schedule sch
        JOIN section sec ON sec.section_id = sch.section_id
        JOIN course c ON c.course_id = sec.course_id
        LEFT JOIN teaching tg ON tg.section_id = sch.section_id
        LEFT JOIN user u ON u.user_id = tg.teacher_id
        WHERE sch.classroom_id = ?
          AND sch.day_of_week = ?
          AND sch.schedule_id <> ?
          AND ? < sch.end_time
          AND ? > sch.start_time
          AND ? <= COALESCE(sch.week_end, 20)
          AND ? >= COALESCE(sch.week_start, 1)
        GROUP BY sch.schedule_id, c.name, sch.week_start, sch.week_end, sch.start_time, sch.end_time
        ORDER BY sch.start_time, sch.schedule_id
    ");
    $stmt->execute([$classroomId, $dayOfWeek, $excludeScheduleId, $startTime, $endTime, $weekStart, $weekEnd]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function schedule_assert_no_conflicts(PDO $pdo, array $payload, int $excludeScheduleId = 0): void
{
    $teacherConflicts = schedule_find_teacher_conflicts(
        $pdo,
        $payload['teacher_id'],
        $payload['day_of_week'],
        $payload['start_time'],
        $payload['end_time'],
        $payload['week_start'],
        $payload['week_end'],
        $excludeScheduleId
    );

    $roomConflicts = schedule_find_room_conflicts(
        $pdo,
        $payload['classroom_id'],
        $payload['day_of_week'],
        $payload['start_time'],
        $payload['end_time'],
        $payload['week_start'],
        $payload['week_end'],
        $excludeScheduleId
    );

    $messages = [];
    foreach ($teacherConflicts as $row) {
        $room = (!empty($row['building']) && !empty($row['room_number'])) ? ($row['building'] . '-' . $row['room_number']) : '未设置教室';
        $messages[] = '教师“' . $payload['teacher_name'] . '”在' . schedule_day_name_cn($payload['day_of_week']) . ' '
            . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5)
            . '已安排《' . $row['course_name'] . '》'
            . '（第' . (int) $row['week_start'] . '-' . (int) $row['week_end'] . '周，教室 ' . $room . '），因此不能重复排课。';
    }

    foreach ($roomConflicts as $row) {
        $teacherName = $row['teacher_name'] ?: '未分配教师';
        $messages[] = '教室“' . $payload['classroom_name'] . '”在' . schedule_day_name_cn($payload['day_of_week']) . ' '
            . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5)
            . '已被《' . $row['course_name'] . '》占用'
            . '（任课教师 ' . $teacherName . '，第' . (int) $row['week_start'] . '-' . (int) $row['week_end'] . '周），因此不能重复使用。';
    }

    if (!empty($messages)) {
        schedule_response_error('无法保存排课：' . implode('；', $messages));
    }
}

// ==================== 学生相关（完全保留，适配你们的表） ====================
if ($act === 'add_student') {
    $name = trim($_POST['name'] ?? '');
    $email = admin_normalize_email($_POST['email'] ?? '');
    $pwd = $_POST['pwd'] ?? '';
    $student_no = trim($_POST['student_no'] ?? '');
    $dept_id = $_POST['dept_id'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $phone = trim($_POST['phone'] ?? '');

    if ($dept_id === '') $dept_id = null;
    if ($msg = admin_validate_school_email($email)) admin_action_error('student.php', $msg);
    if ($msg = admin_validate_phone($phone)) admin_action_error('student.php', $msg);
    if ($msg = admin_validate_student_no($student_no)) admin_action_error('student.php', $msg);

    // 唯一性检查：邮箱
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = '此邮箱已被占用，请使用其他邮箱';
        if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
        header('Location: student.php?error=' . urlencode($msg));
        exit;
    }

    // 唯一性检查：手机号（非空时）
    if ($phone !== '') {
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $msg = '该手机号已被占用，请检查后重试';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
            header('Location: student.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 学号唯一性检查（非空）
    if ($student_no !== '') {
        $stmt = $pdo->prepare("SELECT user_id FROM student WHERE student_no = ? LIMIT 1");
        $stmt->execute([$student_no]);
        if ($stmt->fetch()) {
            $msg = '该学号已被占用，请检查后重试';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
            header('Location: student.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 处理头像上传（可选）
    $avatarResult = handle_avatar_upload();
    if (is_array($avatarResult) && isset($avatarResult['error'])) {
        $msg = $avatarResult['error'];
        if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: student.php?error=' . urlencode($msg));
        exit;
    }
    $avatarFile = is_string($avatarResult) ? $avatarResult : null;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO user (name, email, password, status, gender, phone, image) VALUES (?, ?, ?, 'active', ?, ?, ?)");
        $stmt->execute([$name, $email, password_hash($pwd, PASSWORD_DEFAULT), $gender ?: null, $phone ?: null, $avatarFile]);
        $uid = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO student (user_id, student_no, dept_id) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $student_no, $dept_id]);
        $pdo->commit();
        // 记录日志：新增学生（涉及 user 与 student 表）
        if (function_exists('sys_log')) {
            $desc = "新增学生: " . ($name ?: $email) . " (ID: $uid)";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'student', $uid);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = '创建失败，请稍后重试或联系管理员';
        if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: student.php?error=' . urlencode($msg));
        exit;
    }

    if ($isAjax) { echo json_encode(['ok' => true, 'message' => '创建成功']); exit; }
    header("Location: student.php");
    exit;
} elseif ($act === 'del_student') {
    $id = $_GET['id'] ?? 0;
    $pdo->prepare("DELETE FROM student WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM user WHERE user_id = ?")->execute([$id]);
    // 记录日志：删除学生
    if (function_exists('sys_log')) {
        $desc = "删除学生: (ID: $id)";
        sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'student', $id);
    }
    header("Location: student.php");
    exit;
}

// ==================== 教师相关（完全保留，适配你们的表） ====================
if ($act === 'add_teacher') {
    $name = trim($_POST['name'] ?? '');
    $email = admin_normalize_email($_POST['email'] ?? '');
    $pwd = $_POST['pwd'] ?? '';
    $title = $_POST['title'] ?? '';
    $dept_id = $_POST['dept_id'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $phone = trim($_POST['phone'] ?? '');

    if ($dept_id === '') $dept_id = null;
    if ($msg = admin_validate_school_email($email)) admin_action_error('teacher.php', $msg);
    if ($msg = admin_validate_phone($phone)) admin_action_error('teacher.php', $msg);

    // 唯一性检查：邮箱
    $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $msg = '此邮箱已被占用，请使用其他邮箱';
        if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
        header('Location: teacher.php?error=' . urlencode($msg));
        exit;
    }

    // 手机号唯一性检查（非空）
    if ($phone !== '') {
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            $msg = '该手机号已被占用，请检查后重试';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok' => false, 'error' => $msg]); exit; }
            header('Location: teacher.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 处理头像上传（可选）
    $avatarResult = handle_avatar_upload();
    if (is_array($avatarResult) && isset($avatarResult['error'])) {
        $msg = $avatarResult['error'];
        if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: teacher.php?error=' . urlencode($msg));
        exit;
    }
    $avatarFile = is_string($avatarResult) ? $avatarResult : null;

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO user (name, email, password, status, gender, phone, image) VALUES (?, ?, ?, 'active', ?, ?, ?)");
        $stmt->execute([$name, $email, password_hash($pwd, PASSWORD_DEFAULT), $gender ?: null, $phone ?: null, $avatarFile]);
        $uid = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO teacher (user_id, title, dept_id) VALUES (?, ?, ?)");
        $stmt->execute([$uid, $title, $dept_id]);
        $pdo->commit();
        // 记录日志：新增教师
        if (function_exists('sys_log')) {
            $desc = "新增教师: " . ($name ?: $email) . " (ID: $uid)";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'teacher', $uid);
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = '创建失败，请稍后重试或联系管理员';
        if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: teacher.php?error=' . urlencode($msg));
        exit;
    }

    if ($isAjax) { echo json_encode(['ok' => true, 'message' => '创建成功']); exit; }
    header("Location: teacher.php");
    exit;
} elseif ($act === 'del_teacher') {
    $id = $_GET['id'] ?? 0;
    $pdo->prepare("DELETE FROM teacher WHERE user_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM user WHERE user_id = ?")->execute([$id]);
    // 记录日志：删除教师
    if (function_exists('sys_log')) {
        $desc = "删除教师: (ID: $id)";
        sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'teacher', $id);
    }
    header("Location: teacher.php");
    exit;
}

// 切换用户状态（active <-> inactive）
// 切换用户状态（active <-> inactive）
if ($act === 'toggle_status') {
    // 支持 POST 或 GET（表单通常使用 POST）
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id) {
        $stmt = $pdo->prepare("SELECT status, name, email FROM user WHERE user_id = ?");
        $stmt->execute([$id]);
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $new = ($u['status'] === 'active') ? 'inactive' : 'active';
            $stmt = $pdo->prepare("UPDATE user SET status = ? WHERE user_id = ?");
            $stmt->execute([$new, $id]);
            // 记录日志
            if (function_exists('sys_log')) {
                $display = $u['name'] ?? $u['email'] ?? ('ID ' . $id);
                $action_name = ($new === 'inactive') ? '禁用用户' : '启用用户';
                $desc = "$action_name: $display (ID: $id)";
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user', $id);
            }
        }
    }
    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';
    // 简单清理换行，防止 header 注入
    $redirect = preg_replace('/[\r\n].*/', '', $redirect);
    header("Location: " . $redirect);
    exit;
}

if ($act === 'reset_password') {
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        $msg = '缺少用户 ID';
        if ($isAjax) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';
        $sep = strpos($redirect, '?') !== false ? '&' : '?';
        header('Location: ' . $redirect . $sep . 'error=' . urlencode($msg));
        exit;
    }

    try {
        $tmpPwd = '123456';
        $newHash = password_hash($tmpPwd, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
        $stmt->execute([$newHash, $id]);

        if (function_exists('sys_log')) {
            $desc = "重置用户密码: (ID: $id)";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user', $id);
        }
    } catch (PDOException $e) {
        $msg = '重置密码失败';
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => $msg], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';
        $sep = strpos($redirect, '?') !== false ? '&' : '?';
        header('Location: ' . $redirect . $sep . 'error=' . urlencode($msg));
        exit;
    }

    $msg = '密码已重置，临时密码为：' . $tmpPwd;
    $msg = '密码已重置为：' . $tmpPwd;
    if ($isAjax) {
        echo json_encode(['ok' => true, 'message' => $msg, 'temp_password' => $tmpPwd], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $redirect = $_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php';
    $sep = strpos($redirect, '?') !== false ? '&' : '?';
    header('Location: ' . $redirect . $sep . 'success=' . urlencode($msg));
    exit;
}

// 重置密码（管理员操作），使用随机临时密码替代固定明文
if ($act === 'reset_password') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id) {
        try {
            // 生成强随机临时密码（12 字节 hex -> 24 字符，可根据需要调整）
            $tmpPwd = substr(bin2hex(random_bytes(8)), 0, 12);
            $newHash = password_hash($tmpPwd, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE user SET password = ? WHERE user_id = ?");
            $stmt->execute([$newHash, $id]);

            // 记录管理员重置密码操作
            if (function_exists('sys_log')) {
                $desc = "重置用户密码: (ID: $id)";
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user', $id);
            }
        } catch (PDOException $e) {
            $msg = '重置密码失败';
            if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            $redirect = $_GET['redirect'] ?? 'index.php';
            $sep = strpos($redirect, '?') !== false ? '&' : '?';
            header('Location: ' . $redirect . $sep . 'error=' . urlencode($msg));
            exit;
        }
    }
    $msg = isset($tmpPwd) ? ('密码已重置为 ' . $tmpPwd) : '密码已重置';
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>$msg]); exit; }
    $redirect = $_GET['redirect'] ?? 'index.php';
    $sep = strpos($redirect, '?') !== false ? '&' : '?';
    header('Location: ' . $redirect . $sep . 'success=' . urlencode($msg));
    exit;
}

// 更新学生信息（可编辑除主键外字段；密码留空则不改）
if ($act === 'update_student') {
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = admin_normalize_email($_POST['email'] ?? '');
    $statusPresent = array_key_exists('status', $_POST);
    $status = $statusPresent ? $_POST['status'] : null;
    $student_no = trim($_POST['student_no'] ?? '');
    $dept_id = $_POST['dept_id'] ?? null;
    $pwd = $_POST['pwd'] ?? '';
    $gender = $_POST['gender'] ?? null;
    $phone = trim($_POST['phone'] ?? '');

    if ($dept_id === '') $dept_id = null;
    if ($msg = admin_validate_school_email($email)) admin_action_error('student.php', $msg);
    if ($msg = admin_validate_phone($phone)) admin_action_error('student.php', $msg);
    if ($msg = admin_validate_student_no($student_no)) admin_action_error('student.php', $msg);

    if ($id) {
        // 检查邮箱是否被其他用户占用
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['user_id'] !== $id) {
            $msg = '此邮箱已被其他账户使用，请使用其它邮箱或联系管理员';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: student.php?error=' . urlencode($msg));
            exit;
        }

        // 手机号唯一性检查（非空）
        if ($phone !== '') {
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $r2 = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r2 && (int)$r2['user_id'] !== $id) {
                $msg = '该手机号已被其他账户使用，请使用其它手机号或联系管理员';
                if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
                header('Location: student.php?error=' . urlencode($msg));
                exit;
            }
        }

        // 学号唯一性检查（非空时）
        if ($student_no !== '') {
            $stmt = $pdo->prepare("SELECT user_id FROM student WHERE student_no = ? LIMIT 1");
            $stmt->execute([$student_no]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r && (int)$r['user_id'] !== $id) {
                $msg = '该学号已被其他学生使用，请核对后重试';
                if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
                header('Location: student.php?error=' . urlencode($msg));
                exit;
            }
        }

        // 处理头像上传（可选），若上传成功则替换并删除旧文件
        $avatarResult = handle_avatar_upload();
        if (is_array($avatarResult) && isset($avatarResult['error'])) {
            $msg = $avatarResult['error'];
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: student.php?error=' . urlencode($msg));
            exit;
        }
        $avatarFile = is_string($avatarResult) ? $avatarResult : null;

        try {
            $pdo->beginTransaction();
            // 先取出旧头像（若有），以便后续删除
            $old = null;
            if ($avatarFile) {
                $oldStmt = $pdo->prepare("SELECT image FROM user WHERE user_id = ?");
                $oldStmt->execute([$id]);
                $old = $oldStmt->fetchColumn();
            }

            // 动态构建 UPDATE user 的字段，只有当表单提交了 status 时才更新 status
            $sets = ['name = ?', 'email = ?'];
            $params = [$name, $email];
            if ($statusPresent) { $sets[] = 'status = ?'; $params[] = $status; }
            if (!empty($pwd)) { $sets[] = 'password = ?'; $params[] = password_hash($pwd, PASSWORD_DEFAULT); }
            $sets[] = 'gender = ?'; $params[] = $gender ?: null;
            $sets[] = 'phone = ?'; $params[] = $phone ?: null;
            if ($avatarFile) { $sets[] = 'image = ?'; $params[] = $avatarFile; }

            $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 如果上传了新头像，删除旧头像文件（若存在）
            if ($avatarFile && $old) {
                $oldPath = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR . basename((string) $old);
                if (is_file($oldPath)) @unlink($oldPath);
            }

            $stmt = $pdo->prepare("UPDATE student SET student_no = ?, dept_id = ? WHERE user_id = ?");
            $stmt->execute([$student_no, $dept_id, $id]);
            $pdo->commit();
           
               // 记录日志：管理员编辑了学生信息
               if (function_exists('sys_log')) {
                   $desc = '编辑学生信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
                   // 涉及 user 与 student 两张表，使用单条记录包含多个表名
                   sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user,student', $id);
               }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = '更新失败，请稍后重试或联系管理员';
            if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: student.php?error=' . urlencode($msg));
            exit;
        }
    }

    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header("Location: student.php");
    exit;
}

// 更新教师信息（可编辑除主键外字段；密码留空则不改）
if ($act === 'update_teacher') {
    $id = (int)($_POST['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $email = admin_normalize_email($_POST['email'] ?? '');
    $statusPresent = array_key_exists('status', $_POST);
    $status = $statusPresent ? $_POST['status'] : null;
    $title = $_POST['title'] ?? '';
    $dept_id = $_POST['dept_id'] ?? null;
    $pwd = $_POST['pwd'] ?? '';
    $gender = $_POST['gender'] ?? null;
    $phone = trim($_POST['phone'] ?? '');

    if ($dept_id === '') $dept_id = null;
    if ($msg = admin_validate_school_email($email)) admin_action_error('teacher.php', $msg);
    if ($msg = admin_validate_phone($phone)) admin_action_error('teacher.php', $msg);

    if ($id) {
        // 邮箱唯一性检查
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && (int)$row['user_id'] !== $id) {
            $msg = '此邮箱已被其他账户使用，请使用其它邮箱或联系管理员';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: teacher.php?error=' . urlencode($msg));
            exit;
        }
        // 手机号唯一性检查（非空）
        if ($phone !== '') {
            $stmt = $pdo->prepare("SELECT user_id FROM user WHERE phone = ? LIMIT 1");
            $stmt->execute([$phone]);
            $r2 = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($r2 && (int)$r2['user_id'] !== $id) {
                $msg = '该手机号已被其他账户使用，请使用其它手机号或联系管理员';
                if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
                header('Location: teacher.php?error=' . urlencode($msg));
                exit;
            }
        }

        // 处理头像上传（可选）
        $avatarResult = handle_avatar_upload();
        if (is_array($avatarResult) && isset($avatarResult['error'])) {
            $msg = $avatarResult['error'];
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: teacher.php?error=' . urlencode($msg));
            exit;
        }
        $avatarFile = is_string($avatarResult) ? $avatarResult : null;

        try {
            $pdo->beginTransaction();
            // 先取出旧头像（若有）
            $old = null;
            if ($avatarFile) {
                $oldStmt = $pdo->prepare("SELECT image FROM user WHERE user_id = ?");
                $oldStmt->execute([$id]);
                $old = $oldStmt->fetchColumn();
            }

            // 动态构建 UPDATE user 的字段，只有当表单提交了 status 时才更新 status
            $sets = ['name = ?', 'email = ?'];
            $params = [$name, $email];
            if ($statusPresent) { $sets[] = 'status = ?'; $params[] = $status; }
            if (!empty($pwd)) { $sets[] = 'password = ?'; $params[] = password_hash($pwd, PASSWORD_DEFAULT); }
            $sets[] = 'gender = ?'; $params[] = $gender ?: null;
            $sets[] = 'phone = ?'; $params[] = $phone ?: null;
            if ($avatarFile) { $sets[] = 'image = ?'; $params[] = $avatarFile; }

            $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
            $params[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            // 如果上传了新头像，删除旧头像文件（若存在）
            if ($avatarFile && $old) {
                $oldPath = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR . basename((string) $old);
                if (is_file($oldPath)) @unlink($oldPath);
            }

            $stmt = $pdo->prepare("UPDATE teacher SET title = ?, dept_id = ? WHERE user_id = ?");
            $stmt->execute([$title, $dept_id, $id]);
            $pdo->commit();
            // 记录日志：管理员编辑了教师信息（涉及 user 与 teacher 两张表）
            if (function_exists('sys_log')) {
                $desc = '编辑教师信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user,teacher', $id);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = '更新失败，请稍后重试或联系管理员';
            if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: teacher.php?error=' . urlencode($msg));
            exit;
        }
    }

    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header("Location: teacher.php");
    exit;
}

// 管理员更新自己信息（不允许修改主键或邮箱），支持头像上传
if ($act === 'update_self') {
    $id = (int)($_SESSION['user_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $pwd = $_POST['pwd'] ?? '';
    $gender = $_POST['gender'] ?? null;
    $phone = trim($_POST['phone'] ?? '');
    if ($msg = admin_validate_phone($phone)) admin_action_error('profile.php', $msg);

    // 手机号唯一性检查（非空）
    if ($phone !== '') {
        $stmt = $pdo->prepare("SELECT user_id FROM user WHERE phone = ? LIMIT 1");
        $stmt->execute([$phone]);
        $r = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($r && (int)$r['user_id'] !== $id) {
            $msg = '该手机号已被其他账户使用，请使用其它手机号或联系管理员';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: profile.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 处理头像上传（可选）
    $avatarResult = handle_avatar_upload();
    if (is_array($avatarResult) && isset($avatarResult['error'])) {
        $msg = $avatarResult['error'];
        if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: profile.php?error=' . urlencode($msg));
        exit;
    }
    $avatarFile = is_string($avatarResult) ? $avatarResult : null;

    try {
        $pdo->beginTransaction();
        // 先取出旧头像（若有）
        $old = null;
        if ($avatarFile) {
            $oldStmt = $pdo->prepare("SELECT image FROM user WHERE user_id = ?");
            $oldStmt->execute([$id]);
            $old = $oldStmt->fetchColumn();
        }

        // 构建 UPDATE user 的字段（不允许修改 email 或 user_id）
        $sets = ['name = ?'];
        $params = [$name];
        if (!empty($pwd)) { $sets[] = 'password = ?'; $params[] = password_hash($pwd, PASSWORD_DEFAULT); }
        $sets[] = 'gender = ?'; $params[] = $gender ?: null;
        $sets[] = 'phone = ?'; $params[] = $phone ?: null;
        if ($avatarFile) { $sets[] = 'image = ?'; $params[] = $avatarFile; }

        $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
        $params[] = $id;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // 删除旧头像文件（若有）
        if ($avatarFile && $old) {
            $oldPath = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR . basename((string) $old);
            if (is_file($oldPath)) @unlink($oldPath);
        }

        $pdo->commit();

        // 记录日志：管理员编辑了自己的信息
        if (function_exists('sys_log')) {
            $desc = '编辑管理员信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user', $id);
        }

        // 更新 session 中的名字（便于 header 显示即时生效）
        $_SESSION['user_name'] = $name;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $msg = '更新失败，请稍后重试或联系管理员';
        if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
        header('Location: profile.php?error=' . urlencode($msg));
        exit;
    }

    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header('Location: profile.php?success=1');
    exit;
}

// ==================== 课程相关（100%适配你们团队的course表结构） ====================
// 更新课程信息（编辑除主键外字段）
if ($act === 'update_course') {
    $id = (int)($_POST['course_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $credit = $_POST['credit'] ?? null;
    $hours = $_POST['hours'] ?? null;
    $description = $_POST['description'] ?? '';

    if ($id) {
        try {
            $pdo->prepare("UPDATE course SET name = ?, credit = ?, hours = ?, description = ? WHERE course_id = ?")
                ->execute([$name, $credit !== '' ? $credit : null, $hours !== '' ? $hours : null, $description, $id]);
            // 记录日志
            if (function_exists('sys_log')) {
                $desc = "修改课程: " . ($name ?: "ID $id") . " (ID: $id)";
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'course', $id);
            }
        } catch (PDOException $e) {
            $msg = '更新课程失败';
            if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: course.php?error=' . urlencode($msg));
            exit;
        }
    }
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header('Location: course.php');
    exit;
}

if ($act === 'add_course') {
    // 严格对应你们course表的字段：name / credit / hours / description
    $name = $_POST['name'] ?? '';          // 对应course.name
    $credit = $_POST['credit'] ?? 0;       // 对应course.credit
    $hours = $_POST['hours'] ?? 0;         // 对应course.hours
    $description = $_POST['description'] ?? ''; // 对应course.description

    // 严格对应你们的course表SQL，字段完全匹配
    $stmt = $pdo->prepare("INSERT INTO course (name, credit, hours, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $credit, $hours, $description]);
    // 记录日志
    if (function_exists('sys_log')) {
        $newCourseId = $pdo->lastInsertId();
        $desc = "新增课程: " . ($name ?: "ID $newCourseId") . " (ID: $newCourseId)";
        sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'course', $newCourseId);
    }

    header("Location: course.php");
    exit;
}

$announcementHandled = require __DIR__ . '/api/announcement.php';
if ($announcementHandled) {
    exit;
}

// ==================== 教室相关 ====================
if ($act === 'add_classroom') {
    $building = trim($_POST['building'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 50);
    $type = $_POST['type'] ?? 'normal';

    $stmt = $pdo->prepare("SELECT classroom_id FROM classroom WHERE building = ? AND room_number = ? LIMIT 1");
    $stmt->execute([$building, $room_number]);
    if ($stmt->fetch()) {
        if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'该教学楼的房间号已存在']); exit; }
        header('Location: classroom.php?error=' . urlencode('该教学楼的房间号已存在'));
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO classroom (building, room_number, capacity, type) VALUES (?, ?, ?, ?)");
        $stmt->execute([$building, $room_number, $capacity, $type]);
        // 记录日志
        if (function_exists('sys_log')) {
            $newClassId = $pdo->lastInsertId();
            $desc = "新增教室: $building-$room_number (ID: $newClassId)";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'classroom', $newClassId);
        }
    } catch (PDOException $e) {
        if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'添加失败']); exit; }
    }
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'添加成功']); exit; }
    header("Location: classroom.php");
    exit;
} elseif ($act === 'update_classroom') {
    $id = (int)($_POST['classroom_id'] ?? 0);
    $building = trim($_POST['building'] ?? '');
    $room_number = trim($_POST['room_number'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 50);
    $type = $_POST['type'] ?? 'normal';

    if ($id) {
        $stmt = $pdo->prepare("SELECT classroom_id FROM classroom WHERE building = ? AND room_number = ? AND classroom_id != ? LIMIT 1");
        $stmt->execute([$building, $room_number, $id]);
        if ($stmt->fetch()) {
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'修改后的教学楼和房间号与其他教室冲突']); exit; }
            exit;
        }
        $pdo->prepare("UPDATE classroom SET building = ?, room_number = ?, capacity = ?, type = ? WHERE classroom_id = ?")
            ->execute([$building, $room_number, $capacity, $type, $id]);
        // 记录日志
        if (function_exists('sys_log')) {
            $desc = "修改教室: $building-$room_number (ID: $id)";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'classroom', $id);
        }
    }
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header("Location: classroom.php");
    exit;
}

// ==================== 专业相关 ====================
if ($act === 'add_schedule') {
    $payload = schedule_validate_common($pdo, $_POST);

    $dupStmt = $pdo->prepare("
        SELECT s.section_id, u.name AS teacher_name, COUNT(sch.schedule_id) AS schedule_count
        FROM section s
        LEFT JOIN teaching tg ON tg.section_id = s.section_id
        LEFT JOIN user u ON u.user_id = tg.teacher_id
        LEFT JOIN schedule sch ON sch.section_id = s.section_id
        WHERE s.course_id = ? AND s.year = ? AND s.semester = ?
        GROUP BY s.section_id, u.name
        LIMIT 1
    ");
    $dupStmt->execute([$payload['course_id'], $payload['year'], $payload['semester']]);
    $duplicateSection = $dupStmt->fetch(PDO::FETCH_ASSOC);
    if ($duplicateSection && (int) $duplicateSection['schedule_count'] > 0) {
        $teacherLabel = $duplicateSection['teacher_name'] ? ('，当前教师为 ' . $duplicateSection['teacher_name']) : '';
        schedule_response_error(
            '《' . $payload['course_name'] . '》在 ' . $payload['year'] . ' ' . $payload['semester']
            . ' 学期的开课节已经存在（section_id=' . (int) $duplicateSection['section_id'] . $teacherLabel
            . '）。如需调整，请编辑现有排课，不要重复新增。'
        );
    }

    schedule_assert_no_conflicts($pdo, $payload);

    try {
        $pdo->beginTransaction();

        if ($duplicateSection) {
            $sectionId = (int) $duplicateSection['section_id'];
            $pdo->prepare("
                UPDATE section
                SET capacity = ?
                WHERE section_id = ?
            ")->execute([$payload['capacity'], $sectionId]);
            $pdo->prepare("DELETE FROM teaching WHERE section_id = ?")->execute([$sectionId]);
        } else {
            $sectionStmt = $pdo->prepare("
                INSERT INTO section (semester, year, course_id, capacity)
                VALUES (?, ?, ?, ?)
            ");
            $sectionStmt->execute([$payload['semester'], $payload['year'], $payload['course_id'], $payload['capacity']]);
            $sectionId = (int) $pdo->lastInsertId();
        }

        $teachingStmt = $pdo->prepare("INSERT INTO teaching (teacher_id, section_id) VALUES (?, ?)");
        $teachingStmt->execute([$payload['teacher_id'], $sectionId]);

        $scheduleStmt = $pdo->prepare("
            INSERT INTO schedule (section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $scheduleStmt->execute([
            $sectionId,
            $payload['day_of_week'],
            $payload['start_time'],
            $payload['end_time'],
            $payload['classroom_id'],
            $payload['week_start'],
            $payload['week_end'],
        ]);
        $scheduleId = (int) $pdo->lastInsertId();

        $pdo->commit();

        if (function_exists('sys_log')) {
            $desc = '新增排课: 《' . $payload['course_name'] . '》 / ' . $payload['teacher_name'] . ' / ID ' . $scheduleId;
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'schedule', $scheduleId);
        }

        schedule_response_success('排课新增成功。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        schedule_response_error('排课保存失败，请稍后重试。', 500);
    }
} elseif ($act === 'update_schedule') {
    $payload = schedule_validate_common($pdo, $_POST);
    if ($payload['schedule_id'] <= 0) {
        schedule_response_error('缺少 schedule_id，无法修改。');
    }

    $currentStmt = $pdo->prepare("
        SELECT sch.schedule_id, sch.section_id
        FROM schedule sch
        WHERE sch.schedule_id = ?
        LIMIT 1
    ");
    $currentStmt->execute([$payload['schedule_id']]);
    $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
    if (!$current) {
        schedule_response_error('要修改的排课不存在。');
    }

    $dupStmt = $pdo->prepare("
        SELECT section_id
        FROM section
        WHERE course_id = ? AND year = ? AND semester = ? AND section_id <> ?
        LIMIT 1
    ");
    $dupStmt->execute([$payload['course_id'], $payload['year'], $payload['semester'], (int) $current['section_id']]);
    if ($dupStmt->fetch()) {
        schedule_response_error(
            '无法修改：该课程在同一学年和学期下已经存在其他开课节。请保持“课程 + 学年 + 学期”唯一。'
        );
    }

    schedule_assert_no_conflicts($pdo, $payload, $payload['schedule_id']);

    try {
        $pdo->beginTransaction();

        $sectionStmt = $pdo->prepare("
            UPDATE section
            SET course_id = ?, year = ?, semester = ?, capacity = ?
            WHERE section_id = ?
        ");
        $sectionStmt->execute([
            $payload['course_id'],
            $payload['year'],
            $payload['semester'],
            $payload['capacity'],
            (int) $current['section_id'],
        ]);

        $pdo->prepare("DELETE FROM teaching WHERE section_id = ?")->execute([(int) $current['section_id']]);
        $pdo->prepare("INSERT INTO teaching (teacher_id, section_id) VALUES (?, ?)")->execute([
            $payload['teacher_id'],
            (int) $current['section_id'],
        ]);

        $scheduleStmt = $pdo->prepare("
            UPDATE schedule
            SET day_of_week = ?, start_time = ?, end_time = ?, classroom_id = ?, week_start = ?, week_end = ?
            WHERE schedule_id = ?
        ");
        $scheduleStmt->execute([
            $payload['day_of_week'],
            $payload['start_time'],
            $payload['end_time'],
            $payload['classroom_id'],
            $payload['week_start'],
            $payload['week_end'],
            $payload['schedule_id'],
        ]);

        $pdo->commit();

        if (function_exists('sys_log')) {
            $desc = '修改排课: 《' . $payload['course_name'] . '》 / ' . $payload['teacher_name'] . ' / ID ' . $payload['schedule_id'];
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'schedule', $payload['schedule_id']);
        }

        schedule_response_success('排课修改成功。');
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        schedule_response_error('排课修改失败，请稍后重试。', 500);
    }
} elseif ($act === 'del_schedule') {
    $scheduleId = (int) ($_POST['schedule_id'] ?? $_GET['schedule_id'] ?? 0);
    if ($scheduleId <= 0) {
        schedule_response_error('缺少 schedule_id，无法删除。');
    }

    $stmt = $pdo->prepare("
        SELECT sch.schedule_id, sch.section_id, c.name AS course_name
        FROM schedule sch
        JOIN section sec ON sec.section_id = sch.section_id
        JOIN course c ON c.course_id = sec.course_id
        WHERE sch.schedule_id = ?
        LIMIT 1
    ");
    $stmt->execute([$scheduleId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        schedule_response_error('要删除的排课不存在。');
    }

    try {
        $pdo->prepare("DELETE FROM schedule WHERE schedule_id = ?")->execute([$scheduleId]);

        if (function_exists('sys_log')) {
            $desc = '删除排课: 《' . $row['course_name'] . '》 / ID ' . $scheduleId;
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'schedule', $scheduleId);
        }

        schedule_response_success('排课删除成功。');
    } catch (Throwable $e) {
        schedule_response_error('排课删除失败，请稍后重试。', 500);
    }
}

if ($act === 'add_department') {
    $dept_code = admin_normalize_department_code($_POST['dept_code'] ?? '');
    $dept_name = trim($_POST['dept_name'] ?? '');

    if ($msg = admin_validate_department_code($dept_code)) {
        admin_action_error('department.php', $msg);
    }
    if ($dept_name === '') {
        admin_action_error('department.php', '院系名称不能为空');
    }

    $stmt = $pdo->prepare("SELECT dept_id FROM department WHERE dept_code = ? OR dept_name = ? LIMIT 1");
    $stmt->execute([$dept_code, $dept_name]);
    if ($stmt->fetch()) {
        admin_action_error('department.php', '院系代码或院系名称已存在');
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO department (dept_code, dept_name) VALUES (?, ?)");
        $stmt->execute([$dept_code, $dept_name]);

        if (function_exists('sys_log')) {
            $newDeptId = $pdo->lastInsertId();
            $desc = "新增院系: {$dept_name} ({$dept_code})";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'department', $newDeptId);
        }
    } catch (PDOException $e) {
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => '新增院系失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: department.php?error=' . urlencode('新增院系失败'));
        exit;
    }

    if ($isAjax) {
        echo json_encode(['ok' => true, 'message' => '新增成功'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: department.php");
    exit;
} elseif ($act === 'update_department') {
    $id = (int)($_POST['dept_id'] ?? 0);
    $dept_code = admin_normalize_department_code($_POST['dept_code'] ?? '');
    $dept_name = trim($_POST['dept_name'] ?? '');

    if ($id <= 0) {
        admin_action_error('department.php', '缺少院系ID');
    }
    if ($msg = admin_validate_department_code($dept_code)) {
        admin_action_error('department.php', $msg);
    }
    if ($dept_name === '') {
        admin_action_error('department.php', '院系名称不能为空');
    }

    $stmt = $pdo->prepare("SELECT dept_id FROM department WHERE (dept_code = ? OR dept_name = ?) AND dept_id != ? LIMIT 1");
    $stmt->execute([$dept_code, $dept_name, $id]);
    if ($stmt->fetch()) {
        admin_action_error('department.php', '院系代码或院系名称冲突');
    }

    try {
        $stmt = $pdo->prepare("UPDATE department SET dept_code = ?, dept_name = ? WHERE dept_id = ?");
        $stmt->execute([$dept_code, $dept_name, $id]);

        if (function_exists('sys_log')) {
            $desc = "修改院系: {$dept_name} ({$dept_code})";
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'department', $id);
        }
    } catch (PDOException $e) {
        if ($isAjax) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => '更新院系失败'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        header('Location: department.php?error=' . urlencode('更新院系失败'));
        exit;
    }

    if ($isAjax) {
        echo json_encode(['ok' => true, 'message' => '更新成功'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    header("Location: department.php");
    exit;
}

if ($act === 'add_major') {
    $major_code = admin_normalize_major_code($_POST['major_code'] ?? '');
    $major_name = trim($_POST['major_name'] ?? '');
    $dept_id = (int)($_POST['dept_id'] ?? 0);

    // 检查 major 表列，兼容不同数据库结构
    $majorHasDept = false;
    $majorHasCode = false;
    $majorHasName = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM major");
        $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        $majorHasDept = in_array('dept_id', $cols, true);
        $majorHasCode = in_array('major_code', $cols, true);
        $majorHasName = in_array('major_name', $cols, true);
    } catch (Exception $e) {
        $majorHasDept = $majorHasCode = $majorHasName = false;
    }

    if ($majorHasCode && ($msg = admin_validate_major_code($major_code))) {
        admin_action_error('major.php', $msg);
    }

    // 如果 major 表包含 dept_id，则校验提交的 dept_id
    if ($majorHasDept) {
        if ($dept_id <= 0) {
            $msg = '请选择院系';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: major.php?error=' . urlencode($msg));
            exit;
        }
        $dStmt = $pdo->prepare("SELECT dept_id FROM department WHERE dept_id = ? LIMIT 1");
        $dStmt->execute([$dept_id]);
        if (!$dStmt->fetch()) {
            $msg = '所选院系不存在';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: major.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 如果 major 表包含 dept_id，则必须校验提交的 dept_id 为正整数且在 department 表中存在
    if ($majorHasDept) {
        if ($dept_id <= 0) {
            $msg = '请选择院系';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: major.php?error=' . urlencode($msg));
            exit;
        }
        $dStmt = $pdo->prepare("SELECT dept_id FROM department WHERE dept_id = ? LIMIT 1");
        $dStmt->execute([$dept_id]);
        if (!$dStmt->fetch()) {
            $msg = '所选院系不存在';
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }
            header('Location: major.php?error=' . urlencode($msg));
            exit;
        }
    }

    // 唯一性检查：根据可用列动态构建
    $uniqueParts = [];
    $uniqueParams = [];
    if ($majorHasCode) { $uniqueParts[] = 'major_code = ?'; $uniqueParams[] = $major_code; }
    if ($majorHasName) { $uniqueParts[] = 'major_name = ?'; $uniqueParams[] = $major_name; }
    if (!empty($uniqueParts)) {
        $stmt = $pdo->prepare("SELECT major_id FROM major WHERE " . implode(' OR ', $uniqueParts) . " LIMIT 1");
        $stmt->execute($uniqueParams);
        if ($stmt->fetch()) {
            if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'专业代码或名称已存在']); exit; }
            header('Location: major.php?error=' . urlencode('专业代码或名称已存在'));
            exit;
        }
    }

    try {
        $fields = [];
        $placeholders = [];
        $values = [];
        if ($majorHasCode) { $fields[] = 'major_code'; $placeholders[] = '?'; $values[] = $major_code; }
        if ($majorHasName) { $fields[] = 'major_name'; $placeholders[] = '?'; $values[] = $major_name; }
        if ($majorHasDept) { $fields[] = 'dept_id'; $placeholders[] = '?'; $values[] = $dept_id; }

        if (!empty($fields)) {
            $sql = "INSERT INTO major (" . implode(',', $fields) . ") VALUES (" . implode(',', $placeholders) . ")";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($values);
            // 记录日志
            if (function_exists('sys_log')) {
                $newMajorId = $pdo->lastInsertId();
                $displayValue = $major_name ?: ($major_code ?: "ID $newMajorId");
                $desc = "新增专业: $displayValue (ID: $newMajorId)";
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'major', $newMajorId);
            }
        }
    } catch (PDOException $e) {
        if ($isAjax) { http_response_code(500); echo json_encode(['ok'=>false,'error'=>'添加失败']); exit; }
    }
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'添加成功']); exit; }
    header("Location: major.php");
    exit;
} elseif ($act === 'update_major') {
    $id = (int)($_POST['major_id'] ?? 0);
    $major_code = admin_normalize_major_code($_POST['major_code'] ?? '');
    $major_name = trim($_POST['major_name'] ?? '');
    $dept_id = (int)($_POST['dept_id'] ?? 0);

    // 再次检测列（保证和 add_major 行为一致）
    $majorHasDept = false;
    $majorHasCode = false;
    $majorHasName = false;
    try {
        $colStmt = $pdo->query("SHOW COLUMNS FROM major");
        $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);
        $majorHasDept = in_array('dept_id', $cols, true);
        $majorHasCode = in_array('major_code', $cols, true);
        $majorHasName = in_array('major_name', $cols, true);
    } catch (Exception $e) {
        $majorHasDept = $majorHasCode = $majorHasName = false;
    }

    if ($majorHasCode && ($msg = admin_validate_major_code($major_code))) {
        admin_action_error('major.php', $msg);
    }

    if ($id) {
        // 唯一性冲突检查（排除自身）
        $uniqueParts = [];
        $uniqueParams = [];
        if ($majorHasCode) { $uniqueParts[] = 'major_code = ?'; $uniqueParams[] = $major_code; }
        if ($majorHasName) { $uniqueParts[] = 'major_name = ?'; $uniqueParams[] = $major_name; }
        if (!empty($uniqueParts)) {
            $sql = "SELECT major_id FROM major WHERE (" . implode(' OR ', $uniqueParts) . ") AND major_id != ? LIMIT 1";
            $checkParams = $uniqueParams;
            $checkParams[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($checkParams);
            if ($stmt->fetch()) {
                if ($isAjax) { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'专业代码或名称冲突']); exit; }
                exit;
            }
        }

        $setParts = [];
        $setParams = [];
        if ($majorHasCode) { $setParts[] = 'major_code = ?'; $setParams[] = $major_code; }
        if ($majorHasName) { $setParts[] = 'major_name = ?'; $setParams[] = $major_name; }
        if ($majorHasDept) { $setParts[] = 'dept_id = ?'; $setParams[] = $dept_id; }

        if (!empty($setParts)) {
            $sql = 'UPDATE major SET ' . implode(', ', $setParts) . ' WHERE major_id = ?';
            $setParams[] = $id;
            $pdo->prepare($sql)->execute($setParams);
            // 记录日志
            if (function_exists('sys_log')) {
                $displayValue = $major_name ?: "ID $id";
                $desc = "修改专业: $displayValue (ID: $id)";
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'major', $id);
            }
        }
    }
    if ($isAjax) { echo json_encode(['ok'=>true,'message'=>'更新成功']); exit; }
    header("Location: major.php");
    exit;
}

// 默认跳转
header("Location: index.php");
exit;
?>
