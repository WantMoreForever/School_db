<?php
/**
 * Teacher Portal API
 * All routes call stored procedures / functions defined in teacher_procedures.sql
 * All operations require a logged-in teacher session.
 */
require_once __DIR__ . '/../../components/bootstrap.php';

app_start_session();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';

$pdo = get_pdo();
$TEACHER_ID = require_teacher_auth($pdo);

// ── Route ───────────────────────────────────────────────────
$action = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_profile'           => action_get_profile($pdo, $TEACHER_ID),
        'update_profile'        => action_update_profile($pdo, $TEACHER_ID),
        'upload_avatar'         => action_upload_avatar($pdo, $TEACHER_ID),
        'get_sections'          => action_get_sections($pdo, $TEACHER_ID),
        'get_section_students'  => action_get_section_students($pdo, $TEACHER_ID),
        'get_section_exams'     => action_get_section_exams($pdo, $TEACHER_ID),
        'save_exam'             => action_save_exam($pdo, $TEACHER_ID),
        'update_exam'           => action_update_exam($pdo, $TEACHER_ID),
        'delete_exam'           => action_delete_exam($pdo, $TEACHER_ID),
        'update_letter_grade'   => action_update_letter_grade($pdo, $TEACHER_ID),
        'auto_assign_grades'    => action_auto_assign_grades($pdo, $TEACHER_ID),
        'get_dashboard'         => action_get_dashboard($pdo, $TEACHER_ID),
        'batch_import_exam'     => action_batch_import_exam($pdo, $TEACHER_ID),
        'publish_exam'          => action_publish_exam($pdo, $TEACHER_ID),
        'get_exam_events'       => action_get_exam_events($pdo, $TEACHER_ID),
        'get_pending_exams'     => action_get_pending_exams($pdo, $TEACHER_ID),
        'get_entry_students'    => action_get_entry_students($pdo, $TEACHER_ID),
        'cancel_exam_event'     => action_cancel_exam_event($pdo, $TEACHER_ID),
        'change_password'       => action_change_password($pdo, $TEACHER_ID),
        default                 => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    json_err('Database error: ' . $e->getMessage(), 500);
} catch (Throwable $e) {
    json_err('Server error: ' . $e->getMessage(), 500);
}

function teacher_validate_phone(string $phone): ?string {
    if ($phone === '') return null;
    if (!preg_match('/^\d{11}$/', $phone)) return 'Phone must be exactly 11 digits';
    return null;
}

function teacher_validate_student_no(?string $studentNo): ?string {
    $studentNo = trim((string)$studentNo);
    if ($studentNo === '') return null;
    if (!preg_match('/^\d{8}$/', $studentNo)) return 'student_no must be exactly 8 digits';
    return null;
}

// ═══════════════════════════════════════════════════════════
// ACTION HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * GET /api/teacher.php?action=get_profile
 * Calls: sp_get_teacher_info(teacher_id)
 */
function action_get_profile(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_info(?)');
    $stmt->execute([$tid]);
    $row = $stmt->fetch();
    if (!$row) json_err('Teacher not found', 404);

    // Build avatar URL
    if ($row['image']) {
        $row['avatar_url'] = AVATAR_URL . $row['image'];
    } else {
        $row['avatar_url'] = app_default_avatar_url();
    }
    json_ok($row);
}

/**
 * POST /api/teacher.php?action=update_profile
 * Body: name, phone, gender
 * Calls: sp_update_teacher_profile(...)
 */
function action_update_profile(PDO $pdo, int $tid): void {
    $body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $name   = trim($body['name']   ?? '');
    $phone  = trim($body['phone']  ?? '');
    $gender = $body['gender'] ?? 'other';

    if (!$name)   json_err('Name is required');
    if ($msg = teacher_validate_phone($phone)) json_err($msg);
    if (!in_array($gender, ['male','female','other'])) $gender = 'other';

    $stmt = $pdo->prepare('CALL sp_update_teacher_profile(?, ?, ?, ?)');
    $stmt->execute([$tid, $name, $phone ?: null, $gender]);
    $result = $stmt->fetch();
    $affected = (int)($result['affected_rows'] ?? 0);
    sys_log($pdo, $tid, sys_log_build('更新个人资料', [
        'teacher_id' => $tid,
        'name' => $name,
    ]), 'user', $tid);
    json_ok(['affected' => $affected]);
}

/**
 * POST /api/teacher.php?action=upload_avatar
 * Multipart: file = avatar image
 * Calls: sp_update_teacher_avatar(teacher_id, filename)
 */
function action_upload_avatar(PDO $pdo, int $tid): void {
    if (empty($_FILES['file'])) json_err('No file uploaded');
    $file = $_FILES['file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        json_err('Upload error code: ' . $file['error']);
    }
    if ($file['size'] > AVATAR_MAX_SIZE) {
        json_err('File too large (max 2 MB)');
    }

    // Detect real MIME type via getimagesize (no fileinfo extension needed)
    $imgInfo = @getimagesize($file['tmp_name']);
    if (!$imgInfo) json_err('Unsupported or invalid image file');
    $mimeType = $imgInfo['mime'];

    if (!in_array($mimeType, AVATAR_ALLOWED)) {
        json_err('Unsupported image type: ' . $mimeType);
    }

    $ext      = match ($mimeType) {
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        default      => 'jpg',
    };
    $filename = sprintf('avatar_%d_%d.%s', $tid, time(), $ext);
    $destPath = AVATAR_DIR . $filename;

    if (!is_dir(AVATAR_DIR)) mkdir(AVATAR_DIR, 0755, true);
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        json_err('Failed to save file', 500);
    }

    // Delete old avatar (optional cleanup)
    $old = $pdo->prepare('SELECT image FROM user WHERE user_id = ?');
    $old->execute([$tid]);
    $oldImg = $old->fetchColumn();
    if ($oldImg && file_exists(AVATAR_DIR . $oldImg) && $oldImg !== $filename) {
        @unlink(AVATAR_DIR . $oldImg);
    }

    $stmt = $pdo->prepare('CALL sp_update_teacher_avatar(?, ?)');
    $stmt->execute([$tid, $filename]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    sys_log($pdo, $tid, sys_log_build('更新头像', [
        'teacher_id' => $tid,
        'filename' => $filename,
    ]), 'user', $tid);
    json_ok([
        'filename'   => $filename,
        'avatar_url' => AVATAR_URL . $filename,
    ]);
}

/**
 * GET /api/teacher.php?action=get_sections
 * Calls: sp_get_teacher_sections(teacher_id)
 */
function action_get_sections(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_sections(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/teacher.php?action=get_section_students&section_id=X
 * Calls: sp_get_section_students(section_id)
 */
function action_get_section_students(PDO $pdo, int $tid): void {
    $sid = (int)($_GET['section_id'] ?? 0);
    if (!$sid) json_err('section_id required');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT COUNT(*) FROM teaching WHERE teacher_id = ? AND section_id = ?');
    $check->execute([$tid, $sid]);
    if (!$check->fetchColumn()) json_err('Access denied', 403);

    $stmt = $pdo->prepare('CALL sp_get_section_students(?)');
    $stmt->execute([$sid]);
    $rows = $stmt->fetchAll();

    // Append avatar URLs
    foreach ($rows as &$r) {
        $r['avatar_url'] = $r['image'] ? AVATAR_URL . $r['image'] : app_default_avatar_url();
    }
    json_ok($rows);
}

/**
 * GET /api/teacher.php?action=get_section_exams&section_id=X
 * Calls: sp_get_section_exams(section_id)
 */
function action_get_section_exams(PDO $pdo, int $tid): void {
    $sid = (int)($_GET['section_id'] ?? 0);
    if (!$sid) json_err('section_id required');

    $check = $pdo->prepare('SELECT COUNT(*) FROM teaching WHERE teacher_id = ? AND section_id = ?');
    $check->execute([$tid, $sid]);
    if (!$check->fetchColumn()) json_err('Access denied', 403);

    $stmt = $pdo->prepare('CALL sp_get_section_exams(?)');
    $stmt->execute([$sid]);
    json_ok($stmt->fetchAll());
}

/**
 * POST /api/teacher.php?action=save_exam
 * Body: student_id, section_id, exam_type, score, exam_date
 * Calls: sp_save_exam(...)
 */
function action_save_exam(PDO $pdo, int $tid): void {
    $body       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $student_id = (int)($body['student_id'] ?? 0);
    $section_id = (int)($body['section_id'] ?? 0);
    $exam_type  = $body['exam_type'] ?? '';
    $score      = isset($body['score']) ? (float)$body['score'] : null;
    $exam_date  = $body['exam_date'] ?? date('Y-m-d');

    if (!$student_id || !$section_id)          json_err('student_id and section_id required');
    if (!in_array($exam_type, ['final','midterm','quiz'])) json_err('Invalid exam_type');
    if ($score !== null && ($score < 0 || $score > 100)) json_err('Score must be 0–100');

    // Verify teacher teaches this section
    $check = $pdo->prepare('SELECT COUNT(*) FROM teaching WHERE teacher_id = ? AND section_id = ?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetchColumn()) json_err('Access denied', 403);

    // Verify student is enrolled
    $enroll = $pdo->prepare('SELECT COUNT(*) FROM takes WHERE student_id = ? AND section_id = ?');
    $enroll->execute([$student_id, $section_id]);
    if (!$enroll->fetchColumn()) json_err('Student not enrolled in this section');

    $pdo->exec("SET @current_user_id = {$tid}");
    $stmt = $pdo->prepare('CALL sp_save_exam(?, ?, ?, ?, ?, ?, @exam_id)');
    $stmt->execute([$tid, $student_id, $section_id, $exam_type, $score, $exam_date]);
    consume_remaining_results($stmt);

    $idRow = $pdo->query('SELECT @exam_id AS exam_id')->fetch();
    $exam_id = (int)($idRow['exam_id'] ?? 0);
    if ($exam_id) {
        $desc = "新增考试成绩: 学生 ID $student_id (ID: $exam_id)";
        sys_log($pdo, $tid, sys_log_build('新增成绩', [
            'teacher_id' => $tid,
            'student_id' => $student_id,
            'section_id' => $section_id,
            'exam_id' => $exam_id,
            'exam_type' => $exam_type,
            'score' => $score,
            'exam_date' => $exam_date,
        ]), 'exam', $exam_id);
    }
    json_ok(['exam_id' => $exam_id]);
}

/**
 * POST /api/teacher.php?action=update_exam
 * Body: exam_id, score
 * Calls: sp_update_exam_score(exam_id, score, teacher_id)
 */
function action_update_exam(PDO $pdo, int $tid): void {
    $body    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $exam_id = (int)($body['exam_id'] ?? 0);
    $score   = isset($body['score']) ? (float)$body['score'] : null;

    if (!$exam_id) json_err('exam_id required');
    if ($score === null || $score < 0 || $score > 100) json_err('Score must be 0–100');

    $pdo->exec("SET @current_user_id = {$tid}");
    $stmt = $pdo->prepare('CALL sp_update_exam_score(?, ?, ?)');
    $stmt->execute([$exam_id, $score, $tid]);
    $result = $stmt->fetch();
    consume_remaining_results($stmt);
    $affected = (int)($result['affected_rows'] ?? 0);
    consume_remaining_results($stmt);
    sys_log($pdo, $tid, sys_log_build('更新成绩', [
        'teacher_id' => $tid,
        'exam_id' => $exam_id,
        'score' => $score,
    ]), 'exam', $exam_id);
    json_ok(['affected' => $affected]);
}

/**
 * POST /api/teacher.php?action=delete_exam
 * Body: exam_id
 * Calls: sp_delete_exam(exam_id, teacher_id)
 */
function action_delete_exam(PDO $pdo, int $tid): void {
    $body    = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $exam_id = (int)($body['exam_id'] ?? 0);
    if (!$exam_id) json_err('exam_id required');

    $pdo->exec("SET @current_user_id = {$tid}");
    $stmt = $pdo->prepare('CALL sp_delete_exam(?, ?)');
    $stmt->execute([$exam_id, $tid]);
    $result = $stmt->fetch();
    consume_remaining_results($stmt);
    $affected = (int)($result['affected_rows'] ?? 0);
    consume_remaining_results($stmt);
    sys_log($pdo, $tid, sys_log_build('删除成绩', [
        'teacher_id' => $tid,
        'exam_id' => $exam_id,
    ]), 'exam', $exam_id);
    json_ok(['affected' => $affected]);
}

/**
 * POST /api/teacher.php?action=update_letter_grade
 * Body: student_id, section_id, grade
 * Calls: sp_update_letter_grade(...)
 */
function action_update_letter_grade(PDO $pdo, int $tid): void {
    $body       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $student_id = (int)($body['student_id'] ?? 0);
    $section_id = (int)($body['section_id'] ?? 0);
    $grade      = trim($body['grade'] ?? '');

    if (!$student_id || !$section_id) json_err('student_id and section_id required');

    // Validate grade
    $valid_grades = ['A','A-','B+','B','B-','C+','C','C-','D','F',''];
    if (!in_array($grade, $valid_grades)) json_err('Invalid letter grade');

    $check = $pdo->prepare('SELECT COUNT(*) FROM teaching WHERE teacher_id = ? AND section_id = ?');
    $check->execute([$tid, $section_id]);
    if (!$check->fetchColumn()) json_err('Access denied', 403);

    $pdo->exec("SET @current_user_id = {$tid}");
    $stmt = $pdo->prepare('CALL sp_update_letter_grade(?, ?, ?)');
    $stmt->execute([$student_id, $section_id, $grade ?: null]);
    $result = $stmt->fetch();
    consume_remaining_results($stmt);
    $affected = (int)($result['affected_rows'] ?? 0);
    consume_remaining_results($stmt);
    sys_log($pdo, $tid, sys_log_build('更新字母成绩', [
        'teacher_id' => $tid,
        'student_id' => $student_id,
        'section_id' => $section_id,
        'grade' => $grade === '' ? 'NULL' : $grade,
    ]), 'takes', $student_id);
    json_ok(['affected' => $affected]);
}

/**
 * POST /api/teacher.php?action=auto_assign_grades
 * Body: section_id
 * Calls: sp_auto_assign_grades(section_id, teacher_id)
 */
function action_auto_assign_grades(PDO $pdo, int $tid): void {
    $body       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $section_id = (int)($body['section_id'] ?? 0);
    if (!$section_id) json_err('section_id required');

    $pdo->exec("SET @current_user_id = {$tid}");
    $stmt = $pdo->prepare('CALL sp_auto_assign_grades(?, ?)');
    $stmt->execute([$section_id, $tid]);
    $result = $stmt->fetch();
    consume_remaining_results($stmt);
    $updated = (int)($result['updated_count'] ?? 0);
    consume_remaining_results($stmt);
    sys_log($pdo, $tid, sys_log_build('自动分配等级', [
        'teacher_id' => $tid,
        'section_id' => $section_id,
        'updated' => $updated,
    ]), 'section', $section_id);
    json_ok(['updated' => $updated]);
}

/**
 * POST /api/teacher.php?action=batch_import_exam
 * Body: { section_id, exam_type, exam_date, records:[{student_no,score},...] }
 * Calls: sp_batch_import_exam(...)
 */
function action_batch_import_exam(PDO $pdo, int $tid): void {
    $b          = json_decode(file_get_contents('php://input'), true) ?? [];
    $section_id = (int)($b['section_id'] ?? 0);
    $exam_type  = $b['exam_type'] ?? '';
    $exam_date  = $b['exam_date'] ?? date('Y-m-d');
    $records    = $b['records']   ?? [];

    if (!$section_id)                                       json_err('section_id required');
    if (!in_array($exam_type, ['final','midterm','quiz']))  json_err('Invalid exam_type');
    if (!is_array($records) || !count($records))            json_err('records array required');

    // Validate each record client-side first
    foreach ($records as &$rec) {
        $rec['score']      = isset($rec['score'])      ? (float)$rec['score']      : null;
        $rec['student_id'] = isset($rec['student_id']) ? (int)$rec['student_id']   : null;
        $rec['student_no'] = isset($rec['student_no']) ? trim($rec['student_no'])  : null;
        if ($msg = teacher_validate_student_no($rec['student_no'])) {
            json_err($msg);
        }
        if ($rec['score'] === null || $rec['score'] < 0 || $rec['score'] > 100) {
            json_err('Each record must have a valid score (0–100)');
        }
        if (!$rec['student_id'] && !$rec['student_no']) {
            json_err('Each record must have student_id or student_no');
        }
    }
    unset($rec);

    $json = json_encode(array_values($records));
    $pdo->exec("SET @current_user_id = {$tid}");

    $stmt = $pdo->prepare('CALL sp_batch_import_exam(?,?,?,?,?,@saved,@skipped,@ok,@msg)');
    $stmt->execute([$tid, $section_id, $exam_type, $exam_date, $json]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @saved AS saved, @skipped AS skipped, @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    sys_log($pdo, $tid, sys_log_build('批量导入成绩', [
        'teacher_id' => $tid,
        'section_id' => $section_id,
        'exam_type' => $exam_type,
        'saved' => (int)$r['saved'],
        'skipped' => (int)$r['skipped'],
    ]), 'section', $section_id);
    json_ok(['saved' => (int)$r['saved'], 'skipped' => (int)$r['skipped'], 'message' => $r['msg']]);
}

/**
 * POST /api/teacher.php?action=publish_exam
 * Body: { section_id, exam_type, exam_date }
 */
function action_publish_exam(PDO $pdo, int $tid): void {
    $b          = json_decode(file_get_contents('php://input'), true) ?? [];
    $section_id = (int)($b['section_id'] ?? 0);
    $exam_type  = $b['exam_type'] ?? '';
    $exam_date  = $b['exam_date'] ?? '';

    if (!$section_id) json_err('section_id required');
    if (!in_array($exam_type, ['final','midterm','quiz'])) json_err('Invalid exam_type');
    if (!$exam_date) json_err('exam_date required');

    $stmt = $pdo->prepare('CALL sp_publish_exam(?,?,?,?,@inserted,@ok,@msg)');
    $stmt->execute([$tid, $section_id, $exam_type, $exam_date]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @inserted AS inserted, @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    sys_log($pdo, $tid, sys_log_build('发布考试', [
        'teacher_id' => $tid,
        'section_id' => $section_id,
        'exam_type' => $exam_type,
        'inserted' => (int)$r['inserted'],
    ]), 'section', $section_id);
    json_ok(['inserted' => (int)$r['inserted'], 'message' => $r['msg']]);
}

/**
 * GET /api/teacher.php?action=get_exam_events&section_id=X
 */
function action_get_exam_events(PDO $pdo, int $tid): void {
    $sid = (int)($_GET['section_id'] ?? 0);
    if (!$sid) json_err('section_id required');

    $stmt = $pdo->prepare('CALL sp_get_exam_events(?, ?)');
    $stmt->execute([$tid, $sid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/teacher.php?action=get_pending_exams
 */
function action_get_pending_exams(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_pending_exams(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET /api/teacher.php?action=get_entry_students&section_id=X&exam_type=Y&exam_date=Z
 */
function action_get_entry_students(PDO $pdo, int $tid): void {
    $section_id = (int)($_GET['section_id'] ?? 0);
    $exam_type  = $_GET['exam_type'] ?? '';
    $exam_date  = $_GET['exam_date'] ?? '';
    if (!$section_id || !$exam_type || !$exam_date) json_err('section_id, exam_type, exam_date required');

    $stmt = $pdo->prepare('CALL sp_get_exam_entry_students(?, ?, ?, ?)');
    $stmt->execute([$section_id, $tid, $exam_type, $exam_date]);
    $rows = $stmt->fetchAll();
    foreach ($rows as &$r) {
        $r['avatar_url'] = $r['image'] ? AVATAR_URL . $r['image'] : null;
    }
    json_ok($rows);
}

/**
 * POST /api/teacher.php?action=cancel_exam_event
 * Body: { section_id, exam_type, exam_date }
 */
function action_cancel_exam_event(PDO $pdo, int $tid): void {
    $b          = json_decode(file_get_contents('php://input'), true) ?? [];
    $section_id = (int)($b['section_id'] ?? 0);
    $exam_type  = $b['exam_type'] ?? '';
    $exam_date  = $b['exam_date'] ?? '';
    if (!$section_id || !$exam_type || !$exam_date) json_err('section_id, exam_type, exam_date required');

    $stmt = $pdo->prepare('CALL sp_cancel_exam_event(?,?,?,?,@ok,@msg)');
    $stmt->execute([$section_id, $tid, $exam_type, $exam_date]);
    do { $stmt->fetchAll(); } while ($stmt->nextRowset());

    $r = $pdo->query('SELECT @ok AS ok, @msg AS msg')->fetch();
    if (!(int)$r['ok']) json_err($r['msg']);
    json_ok(['message' => $r['msg']]);
}

/**
 * POST /api/teacher.php?action=change_password
 * Body: { old_password, new_password }
 * Updates password using password_hash algorithm.
 */
function action_change_password(PDO $pdo, int $tid): void {
    $b        = json_decode(file_get_contents('php://input'), true) ?? [];
    $old_pwd  = $b['old_password'] ?? '';
    $new_pwd  = $b['new_password'] ?? '';

    if (!$old_pwd) json_err('请输入当前密码');
    if (strlen($new_pwd) < 6) json_err('新密码至少需要 6 位');
    if ($old_pwd === $new_pwd) json_err('新密码不能与当前密码相同');

    $stmt = $pdo->prepare('SELECT password FROM user WHERE user_id = ?');
    $stmt->execute([$tid]);
    $stored_pwd = $stmt->fetchColumn();

    if (!$stored_pwd) json_err('用户不存在');

    $verified = false;
    if (str_starts_with($stored_pwd, '$2y$') || str_starts_with($stored_pwd, '$argon2')) {
        $verified = password_verify($old_pwd, $stored_pwd);
    } else {
        // Plaintext fallback
        if ($stored_pwd === $old_pwd) {
            $verified = true;
        }
    }

    if (!$verified) json_err('当前密码不正确');

    $new_hash = password_hash($new_pwd, PASSWORD_DEFAULT);
    if ($new_hash === false) json_err('密码应用失败，请稍后重试');

    $upd = $pdo->prepare('UPDATE user SET password = ? WHERE user_id = ?');
    $upd->execute([$new_hash, $tid]);

    json_ok(['message' => '密码已更新']);
}

/**
 * GET /api/teacher.php?action=get_dashboard
 * Calls: sp_get_dashboard_stats(teacher_id) + sp_get_teacher_sections
 */
function action_get_dashboard(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_dashboard_stats(?)');
    $stmt->execute([$tid]);
    $stats = $stmt->fetch();

    // Close result sets from CALL before next query
    while ($stmt->nextRowset()) {}

    $stmt2 = $pdo->prepare('CALL sp_get_teacher_sections(?)');
    $stmt2->execute([$tid]);
    $sections = $stmt2->fetchAll();

    json_ok([
        'stats'    => $stats,
        'sections' => $sections,
    ]);
}
