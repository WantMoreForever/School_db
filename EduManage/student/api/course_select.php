<?php
require_once __DIR__ . '/helpers.php';
student_api_bootstrap();

require_once __DIR__ . '/../../components/auth.php';
require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();
require_once __DIR__ . '/../../components/student_data.php';
require_once __DIR__ . '/../../components/logger.php'; // 记录操作日志及清理

$uid = student_api_require_login();

// load central config for current term
$STU_CFG = include __DIR__ . '/config.php';
$current_year = $STU_CFG['term']['current_year'] ?? (int)date('Y');
$current_semester = $STU_CFG['term']['current_semester'] ?? student_term_default_semester();

// helper: ensure utf-8 (replaced by student_api_utf8)

// capture alerts html
ob_start();
include __DIR__ . '/../../components/alerts.php';
$alerts_html = ob_get_clean();

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($pdo === null) throw new Exception('数据库连接失败');

    if ($method === 'POST') {
        // CSRF 验证：优先读取 POST 字段，其次取 X-CSRF-Token HTTP 头
        $posted_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!student_api_validate_csrf($posted_token)) {
            student_api_json_error('CSRF 验证失败', 419, ['error' => 'invalid_csrf']);
        }

        $action = $_POST['action'] ?? '';
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;

        if ($action === 'enroll' && $section_id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT sec.section_id, sec.year, sec.semester, sec.capacity, sec.enrollment_start, sec.enrollment_end, COUNT(tk.student_id) AS current_enrolled FROM section sec LEFT JOIN takes tk ON tk.section_id = sec.section_id WHERE sec.section_id = ? GROUP BY sec.section_id, sec.year, sec.semester, sec.capacity, sec.enrollment_start, sec.enrollment_end FOR UPDATE");
                $stmt->execute([$section_id]);
                $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$section_info) throw new Exception('选课失败：未找到开课节信息');

                if ((int)$section_info['year'] !== (int)$current_year || $section_info['semester'] !== $current_semester) {
                    throw new Exception('选课失败：仅允许选择当前学期开课');
                }

                $now = date('Y-m-d H:i:s');
                if (empty($section_info['enrollment_start']) || empty($section_info['enrollment_end']) || $now < $section_info['enrollment_start'] || $now > $section_info['enrollment_end']) {
                    throw new Exception('选课失败：当前不在选课时间范围内');
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM takes WHERE student_id = ? AND section_id = ?");
                $stmt->execute([$uid, $section_id]);
                if ((int)$stmt->fetchColumn() > 0) throw new Exception('选课失败：已经选修过该课程');

                if ((int)$section_info['current_enrolled'] >= (int)$section_info['capacity']) throw new Exception('选课失败：该开课节容量已满');

                // 院系或专业限制检查
                $stmt = $pdo->prepare("SELECT major_id FROM restriction WHERE section_id = ?");
                $stmt->execute([$section_id]);
                $req_majors = $stmt->fetchAll(PDO::FETCH_COLUMN);
                if (!empty($req_majors)) {
                    $stmt_major = $pdo->prepare("SELECT major_id FROM student WHERE user_id = ?");
                    $stmt_major->execute([$uid]);
                    $stu_major = $stmt_major->fetchColumn();
                    if (!$stu_major || !in_array((string)$stu_major, $req_majors)) {
                        throw new Exception('选课失败：你的所属专业不在该课程允许的限选专业范围内！');
                    }
                }

                // 时间冲突检查（数据库方式）
                $stmt = $pdo->prepare("SELECT c_old.name AS conflict_course_name, sch_new.day_of_week AS conflict_day, sch_new.start_time AS new_start_time, sch_new.end_time AS new_end_time, sch_new.week_start AS new_week_start, sch_new.week_end AS new_week_end FROM section new_sec LEFT JOIN schedule sch_new ON sch_new.section_id = new_sec.section_id JOIN section old_sec ON old_sec.year = new_sec.year AND old_sec.semester = new_sec.semester AND old_sec.section_id <> new_sec.section_id JOIN takes tk ON tk.section_id = old_sec.section_id AND tk.student_id = ? LEFT JOIN schedule sch_old ON sch_old.section_id = old_sec.section_id JOIN course c_old ON c_old.course_id = old_sec.course_id WHERE new_sec.section_id = ? AND sch_new.day_of_week = sch_old.day_of_week AND sch_new.week_start <= sch_old.week_end AND sch_new.week_end >= sch_old.week_start AND sch_new.start_time < sch_old.end_time AND sch_new.end_time > sch_old.start_time LIMIT 1");
                $stmt->execute([$uid, $section_id]);
                $conflict = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($conflict) {
                    throw new Exception('选课失败：与已选课程时间冲突（' . $conflict['conflict_course_name'] . '，周' . $conflict['conflict_day'] . ' ' . substr($conflict['new_start_time'], 0, 5) . '-' . substr($conflict['new_end_time'], 0, 5) . '）');
                }

                $stmt = $pdo->prepare("INSERT INTO takes (student_id, section_id, enrolled_at) VALUES (?, ?, NOW())");
                $stmt->execute([$uid, $section_id]);

                // 记录日志
                sys_log($pdo, $uid, sys_log_build('选课成功', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                ]), 'takes', $section_id);
                
                $pdo->commit();
                student_api_json_ok(['ok'=>true,'message'=>'选课成功']);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();

                // 失败也记录关联信息，方便追溯
                sys_log($pdo, $uid, sys_log_build('选课失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $e->getMessage(),
                ]), 'takes', $section_id);
                
                student_api_json_ok(['ok'=>false,'message'=>$e->getMessage()]);
            }
        }

        if ($action === 'drop' && $section_id > 0) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT enrollment_start, enrollment_end FROM section WHERE section_id = ?");
                $stmt->execute([$section_id]);
                $sec_time = $stmt->fetch(PDO::FETCH_ASSOC);
                $now = date('Y-m-d H:i:s');
                if ($sec_time && (empty($sec_time['enrollment_start']) || empty($sec_time['enrollment_end']) || $now < $sec_time['enrollment_start'] || $now > $sec_time['enrollment_end'])) {
                    throw new Exception('不在退选时间范围内，禁止退选');
                }

                $stmt = $pdo->prepare("DELETE t FROM takes t JOIN section sec ON sec.section_id = t.section_id WHERE t.student_id = ? AND t.section_id = ? AND sec.year = ? AND sec.semester = ?");
                $stmt->execute([$uid, $section_id, $current_year, $current_semester]);
                if ($stmt->rowCount() > 0) {
                    // 退选成功
                    sys_log($pdo, $uid, sys_log_build('退选成功', [
                        'user_id' => $uid,
                        'section_id' => $section_id,
                    ]), 'takes', $section_id);
                    $pdo->commit();
                    student_api_json_ok(['ok'=>true,'message'=>'退选成功']);
                } else {
                    sys_log($pdo, $uid, sys_log_build('退选失败', [
                        'user_id' => $uid,
                        'section_id' => $section_id,
                        'reason' => '未找到该课程',
                    ]), 'takes', $section_id);
                    $pdo->rollBack();
                    student_api_json_ok(['ok'=>false,'message'=>'未找到您可以退选的该课程记录，或不在允许的学期内。']);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                sys_log($pdo, $uid, sys_log_build('退选失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $e->getMessage(),
                ]), 'takes', $section_id);
                student_api_json_ok(['ok'=>false,'message'=>'退选异常：'.$e->getMessage()]);
            }
        }

        student_api_json_ok(['ok'=>false,'message'=>'无效的请求操作']);
    }

    // GET: assemble data
    // my courses
    try {
        $my_courses = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_student_current_courses', [$uid, $current_year, $current_semester])
            : [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("SELECT sec.section_id, c.name AS course_name, c.credit, u.name AS teacher_name, sec.enrollment_start, sec.enrollment_end, (NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open FROM takes t JOIN section sec ON t.section_id = sec.section_id JOIN course c ON sec.course_id = c.course_id LEFT JOIN (SELECT section_id, MIN(teacher_id) AS primary_teacher_id FROM teaching GROUP BY section_id) pri ON pri.section_id = sec.section_id LEFT JOIN user u ON u.user_id = pri.primary_teacher_id WHERE t.student_id = ? AND sec.year = ? AND sec.semester = ? ORDER BY t.enrolled_at DESC");
        $stmt->execute([$uid, $current_year, $current_semester]);
        $my_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $my_credits = array_sum(array_map(function($row) { return (float)($row['credit'] ?? 0); }, $my_courses));

    // my schedules
    try {
        $mySchedules = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_student_current_schedules', [$uid, $current_year, $current_semester])
            : [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("SELECT sch.section_id, sch.day_of_week, sch.start_time, sch.end_time, CONCAT(cr.building, '-', cr.room_number) AS location, sch.week_start, sch.week_end FROM takes t JOIN section sec ON t.section_id = sec.section_id LEFT JOIN schedule sch ON sch.section_id = sec.section_id LEFT JOIN classroom cr ON cr.classroom_id = sch.classroom_id WHERE t.student_id = ? AND sec.year = ? AND sec.semester = ?");
        $stmt->execute([$uid, $current_year, $current_semester]);
        $mySchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // attach schedule to my_courses
    foreach ($my_courses as &$mc) {
        $mc['schedules'] = [];
        foreach ($mySchedules as $sch) {
            if ((int)$sch['section_id'] === (int)$mc['section_id']) {
                $mc['schedules'][] = $sch;
            }
        }
    }
    unset($mc);

    // available sections
    try {
        $available_sections = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_available_sections', [$uid, $current_year, $current_semester])
            : [];
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("SELECT sec.section_id, c.name AS course_name, c.credit, u.name AS teacher_name, sec.capacity, sec.enrollment_start, sec.enrollment_end, (NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open, (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id) AS enrolled_count, (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id AND student_id = ?) AS is_my_course FROM section sec JOIN course c ON sec.course_id = c.course_id LEFT JOIN (SELECT section_id, MIN(teacher_id) AS primary_teacher_id FROM teaching GROUP BY section_id) pri ON pri.section_id = sec.section_id LEFT JOIN user u ON u.user_id = pri.primary_teacher_id WHERE sec.year = ? AND sec.semester = ? AND (NOT EXISTS (SELECT 1 FROM restriction r WHERE r.section_id = sec.section_id) OR EXISTS (SELECT 1 FROM restriction r JOIN student s ON s.major_id = r.major_id WHERE r.section_id = sec.section_id AND s.user_id = ?)) ORDER BY c.course_id ASC, sec.section_id ASC");
        $stmt->execute([$uid, $current_year, $current_semester, $uid]);
        $available_sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // prefetch schedules for available sections
    $sectionIds = array_column($available_sections, 'section_id');
    $scheduleMap = [];
    if (!empty($sectionIds)) {
        $placeholders = implode(',', array_fill(0, count($sectionIds), '?'));
        $stmt = $pdo->prepare("SELECT sch.section_id, sch.day_of_week, sch.start_time, sch.end_time, CONCAT(cr.building, '-', cr.room_number) AS location, sch.week_start, sch.week_end FROM schedule sch LEFT JOIN classroom cr ON cr.classroom_id = sch.classroom_id WHERE sch.section_id IN ($placeholders) ORDER BY sch.section_id ASC");
        $stmt->execute($sectionIds);
        $allSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($allSchedules as $r) {
            $sid = (int)$r['section_id'];
            $scheduleMap[$sid][] = $r;
        }
    }

    // mark conflict flags and attach schedules
    foreach ($available_sections as &$sec) {
        $sid = (int)$sec['section_id'];
        $sec['conflict_flag'] = 0;
        $sec['schedules'] = $scheduleMap[$sid] ?? []; // attach schedules properly

        if ((int)$sec['is_my_course'] > 0) continue;
        if (empty($scheduleMap[$sid]) || empty($mySchedules)) continue;
        foreach ($scheduleMap[$sid] as $newSch) {
            foreach ($mySchedules as $oldSch) {
                $sameDay = (int)$newSch['day_of_week'] === (int)$oldSch['day_of_week'];
                $weekOverlap = (int)$newSch['week_start'] <= (int)$oldSch['week_end'] && (int)$newSch['week_end'] >= (int)$oldSch['week_start'];
                $timeOverlap = $newSch['start_time'] < $oldSch['end_time'] && $newSch['end_time'] > $oldSch['start_time'];
                if ($sameDay && $weekOverlap && $timeOverlap) { $sec['conflict_flag'] = 1; break 2; }
            }
        }
    }
    unset($sec);

    $data = [
        'student' => getStudentBaseInfo($pdo, $uid),
        'current_year' => $current_year,
        'current_semester' => $current_semester,
        'my_courses' => $my_courses,
        'my_credits' => $my_credits,
        'mySchedules' => $mySchedules,
        'available_sections' => $available_sections,
    ];

    // ensure utf-8
    $alerts_html  = student_api_utf8_string($alerts_html);
    $data = student_api_utf8($data);

    student_api_json_ok(['ok'=>true,'data'=>$data,'alerts_html'=>$alerts_html]);

} catch (Exception $e) {
    student_api_json_ok(['ok'=>false,'message'=>$e->getMessage()]);
}

