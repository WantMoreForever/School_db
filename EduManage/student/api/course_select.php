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
$total_weeks = (int)($STU_CFG['schedule']['total_weeks'] ?? 16);
$csrf_token = student_api_ensure_csrf_token();

// helper: ensure utf-8 (replaced by student_api_utf8)

// capture alerts html
ob_start();
include __DIR__ . '/../../components/alerts.php';
$alerts_html = ob_get_clean();

$method = $_SERVER['REQUEST_METHOD'];

function student_course_select_call_proc(PDO $pdo, string $procedure, array $params): ?array
{
    if (!function_exists('app_call_rows')) {
        return null;
    }

    try {
        $rows = app_call_rows($pdo, $procedure, $params);
    } catch (Throwable $e) {
        return null;
    }

    return isset($rows[0]) && is_array($rows[0]) ? $rows[0] : null;
}

function student_course_select_proc_ok(array $row): bool
{
    return isset($row['ok']) && ((string)$row['ok'] === '1' || $row['ok'] === true);
}

try {
    if ($pdo === null) throw new Exception('数据库连接失败');

    if ($method === 'POST') {
        // CSRF 验证：优先读取 POST 字段，其次取 X-CSRF-Token HTTP 头
        $posted_token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
        if (!student_api_validate_csrf($posted_token)) {
            student_api_json_error('CSRF 验证失败', 419, ['error' => 'invalid_csrf', 'csrf_token' => $csrf_token]);
        }

        $action = $_POST['action'] ?? '';
        $section_id = isset($_POST['section_id']) ? (int)$_POST['section_id'] : 0;

        if ($action === 'enroll' && $section_id > 0) {
            $proc_result = student_course_select_call_proc($pdo, 'sp_project_student_enroll_section', [
                $uid,
                $section_id,
                $current_year,
                $current_semester,
                $total_weeks,
            ]);
            if ($proc_result !== null) {
                $proc_ok = student_course_select_proc_ok($proc_result);
                $proc_message = (string)($proc_result['message'] ?? ($proc_ok ? '选课成功' : '选课失败'));
                sys_log($pdo, $uid, sys_log_build($proc_ok ? '选课成功' : '选课失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $proc_ok ? '' : $proc_message,
                    'source' => 'sp_project_student_enroll_section',
                ]), 'takes', $section_id);
                student_api_json_ok(['ok'=>$proc_ok,'message'=>$proc_message,'csrf_token'=>$csrf_token]);
            }

            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("SELECT sec.section_id, sec.year, sec.semester, sec.course_id, sec.capacity, sec.enrollment_start, sec.enrollment_end, (sec.enrollment_start IS NOT NULL AND sec.enrollment_end IS NOT NULL AND NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open FROM section sec WHERE sec.section_id = ? FOR UPDATE");
                $stmt->execute([$section_id]);
                $section_info = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$section_info) throw new Exception('选课失败：未找到开课节信息');

                if ((int)$section_info['year'] !== (int)$current_year || $section_info['semester'] !== $current_semester) {
                    throw new Exception('选课失败：仅允许选择当前学期开课');
                }

                if ((int)($section_info['is_open'] ?? 0) !== 1) {
                    throw new Exception('选课失败：当前不在选课时间范围内');
                }

                $stmt = $pdo->prepare("SELECT major_id FROM student WHERE user_id = ? LIMIT 1");
                $stmt->execute([$uid]);
                $student_major = $stmt->fetchColumn();
                if ($student_major === false) {
                    throw new Exception('选课失败：未找到学生信息');
                }

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM takes tk JOIN section old_sec ON old_sec.section_id = tk.section_id WHERE tk.student_id = ? AND old_sec.year = ? AND old_sec.semester = ? AND old_sec.course_id = ?");
                $stmt->execute([$uid, $current_year, $current_semester, (int)$section_info['course_id']]);
                if ((int)$stmt->fetchColumn() > 0) throw new Exception('选课失败：已经选修过该课程');

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM takes WHERE section_id = ?");
                $stmt->execute([$section_id]);
                if ((int)$stmt->fetchColumn() >= (int)$section_info['capacity']) throw new Exception('选课失败：该开课节容量已满');

                // 专业限制检查：restriction 表存在记录时，仅允许匹配学生 major_id 的专业选修。
                $stmt = $pdo->prepare("SELECT COUNT(*) AS restriction_count, SUM(CASE WHEN major_id = ? THEN 1 ELSE 0 END) AS allowed_count FROM restriction WHERE section_id = ?");
                $stmt->execute([$student_major !== null ? (int)$student_major : null, $section_id]);
                $restriction = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                if ((int)($restriction['restriction_count'] ?? 0) > 0 && (int)($restriction['allowed_count'] ?? 0) === 0) {
                    throw new Exception('选课失败：你的所属专业不在该课程允许的限选专业范围内！');
                }

                // 时间冲突检查（同一学期、周次重叠、时间段重叠）
                $stmt = $pdo->prepare("SELECT c_old.name AS conflict_course_name, sch_new.day_of_week AS conflict_day, sch_new.start_time AS new_start_time, sch_new.end_time AS new_end_time FROM schedule sch_new JOIN section new_sec ON new_sec.section_id = sch_new.section_id JOIN takes tk ON tk.student_id = ? JOIN section old_sec ON old_sec.section_id = tk.section_id AND old_sec.year = new_sec.year AND old_sec.semester = new_sec.semester AND old_sec.section_id <> new_sec.section_id JOIN schedule sch_old ON sch_old.section_id = old_sec.section_id JOIN course c_old ON c_old.course_id = old_sec.course_id WHERE sch_new.section_id = ? AND sch_new.day_of_week = sch_old.day_of_week AND COALESCE(sch_new.week_start, 1) <= COALESCE(sch_old.week_end, ?) AND COALESCE(sch_new.week_end, ?) >= COALESCE(sch_old.week_start, 1) AND sch_new.start_time < sch_old.end_time AND sch_new.end_time > sch_old.start_time LIMIT 1");
                $stmt->execute([$uid, $section_id, $total_weeks, $total_weeks]);
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
                student_api_json_ok(['ok'=>true,'message'=>'选课成功','csrf_token'=>$csrf_token]);
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();

                // 失败也记录关联信息，方便追溯
                sys_log($pdo, $uid, sys_log_build('选课失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $e->getMessage(),
                ]), 'takes', $section_id);
                
                student_api_json_ok(['ok'=>false,'message'=>$e->getMessage(),'csrf_token'=>$csrf_token]);
            }
        }

        if ($action === 'drop' && $section_id > 0) {
            $proc_result = student_course_select_call_proc($pdo, 'sp_project_student_drop_section', [
                $uid,
                $section_id,
                $current_year,
                $current_semester,
            ]);
            if ($proc_result !== null) {
                $proc_ok = student_course_select_proc_ok($proc_result);
                $proc_message = (string)($proc_result['message'] ?? ($proc_ok ? '退选成功' : '退选失败'));
                sys_log($pdo, $uid, sys_log_build($proc_ok ? '退选成功' : '退选失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $proc_ok ? '' : $proc_message,
                    'source' => 'sp_project_student_drop_section',
                ]), 'takes', $section_id);
                student_api_json_ok(['ok'=>$proc_ok,'message'=>$proc_message,'csrf_token'=>$csrf_token]);
            }

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
                    student_api_json_ok(['ok'=>true,'message'=>'退选成功','csrf_token'=>$csrf_token]);
                } else {
                    sys_log($pdo, $uid, sys_log_build('退选失败', [
                        'user_id' => $uid,
                        'section_id' => $section_id,
                        'reason' => '未找到该课程',
                    ]), 'takes', $section_id);
                    $pdo->rollBack();
                    student_api_json_ok(['ok'=>false,'message'=>'未找到您可以退选的该课程记录，或不在允许的学期内。','csrf_token'=>$csrf_token]);
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                sys_log($pdo, $uid, sys_log_build('退选失败', [
                    'user_id' => $uid,
                    'section_id' => $section_id,
                    'reason' => $e->getMessage(),
                ]), 'takes', $section_id);
                student_api_json_ok(['ok'=>false,'message'=>'退选异常：'.$e->getMessage(),'csrf_token'=>$csrf_token]);
            }
        }

        student_api_json_ok(['ok'=>false,'message'=>'无效的请求操作','csrf_token'=>$csrf_token]);
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
            ? app_call_rows($pdo, 'sp_project_get_available_sections', [$uid, $current_year, $current_semester, $total_weeks])
            : [];
        if (!empty($available_sections) && !array_key_exists('can_enroll', $available_sections[0])) {
            throw new RuntimeException('sp_project_get_available_sections is outdated');
        }
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("SELECT sec.section_id, sec.course_id, c.name AS course_name, c.credit, COALESCE(tea.teacher_names, '待定') AS teacher_name, sec.capacity, sec.enrollment_start, sec.enrollment_end, (sec.enrollment_start IS NOT NULL AND sec.enrollment_end IS NOT NULL AND NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open, (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id) AS enrolled_count, (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id AND student_id = ?) AS is_my_course, (SELECT COUNT(*) FROM takes tk JOIN section old_sec ON old_sec.section_id = tk.section_id WHERE tk.student_id = ? AND old_sec.year = sec.year AND old_sec.semester = sec.semester AND old_sec.course_id = sec.course_id) AS is_same_course_selected, (SELECT COUNT(*) FROM restriction r WHERE r.section_id = sec.section_id) AS restriction_count, (SELECT COUNT(*) FROM restriction r JOIN student s ON s.major_id = r.major_id AND s.user_id = ? WHERE r.section_id = sec.section_id) AS allowed_restriction_count FROM section sec JOIN course c ON sec.course_id = c.course_id LEFT JOIN (SELECT tg.section_id, GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR '、') AS teacher_names FROM teaching tg JOIN user u ON u.user_id = tg.teacher_id GROUP BY tg.section_id) tea ON tea.section_id = sec.section_id WHERE sec.year = ? AND sec.semester = ? ORDER BY c.course_id ASC, sec.section_id ASC");
        $stmt->execute([$uid, $uid, $uid, $current_year, $current_semester]);
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
        $sec['is_open'] = (int)($sec['is_open'] ?? 0);
        $sec['is_my_course'] = (int)($sec['is_my_course'] ?? 0) > 0 ? 1 : 0;
        $sec['is_same_course_selected'] = (int)($sec['is_same_course_selected'] ?? 0) > 0 ? 1 : 0;
        $sec['enrolled_count'] = (int)($sec['enrolled_count'] ?? 0);
        $sec['capacity'] = (int)($sec['capacity'] ?? 0);
        $sec['is_restricted'] = (int)($sec['restriction_count'] ?? 0) > 0 ? 1 : 0;
        $sec['is_major_allowed'] = ($sec['is_restricted'] === 0 || (int)($sec['allowed_restriction_count'] ?? 0) > 0) ? 1 : 0;

        if ($sec['is_my_course'] === 0 && $sec['is_same_course_selected'] === 0 && !empty($scheduleMap[$sid]) && !empty($mySchedules)) {
            foreach ($scheduleMap[$sid] as $newSch) {
                if (empty($newSch['day_of_week']) || empty($newSch['start_time']) || empty($newSch['end_time'])) continue;
                foreach ($mySchedules as $oldSch) {
                    if (empty($oldSch['day_of_week']) || empty($oldSch['start_time']) || empty($oldSch['end_time'])) continue;
                    $sameDay = (int)$newSch['day_of_week'] === (int)$oldSch['day_of_week'];
                    $newStartWeek = (int)($newSch['week_start'] ?? 1);
                    $newEndWeek = (int)($newSch['week_end'] ?? $total_weeks);
                    $oldStartWeek = (int)($oldSch['week_start'] ?? 1);
                    $oldEndWeek = (int)($oldSch['week_end'] ?? $total_weeks);
                    $weekOverlap = $newStartWeek <= $oldEndWeek && $newEndWeek >= $oldStartWeek;
                    $timeOverlap = $newSch['start_time'] < $oldSch['end_time'] && $newSch['end_time'] > $oldSch['start_time'];
                    if ($sameDay && $weekOverlap && $timeOverlap) { $sec['conflict_flag'] = 1; break 2; }
                }
            }
        }

        $sec['can_enroll'] = 0;
        $sec['disabled_reason'] = '';
        if ($sec['is_my_course'] === 1 || $sec['is_same_course_selected'] === 1) {
            $sec['disabled_reason'] = '已选此课';
        } elseif ($sec['is_major_allowed'] !== 1) {
            $sec['disabled_reason'] = '专业限制';
        } elseif ($sec['is_open'] !== 1) {
            $sec['disabled_reason'] = '不在选课时间';
        } elseif ($sec['capacity'] <= 0 || $sec['enrolled_count'] >= $sec['capacity']) {
            $sec['disabled_reason'] = '名额已满';
        } elseif ((int)$sec['conflict_flag'] === 1) {
            $sec['disabled_reason'] = '时间冲突';
        } else {
            $sec['can_enroll'] = 1;
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
        'csrf_token' => $csrf_token,
    ];

    // ensure utf-8
    $alerts_html  = student_api_utf8_string($alerts_html);
    $data = student_api_utf8($data);

    student_api_json_ok(['ok'=>true,'data'=>$data,'alerts_html'=>$alerts_html,'csrf_token'=>$csrf_token]);

} catch (Exception $e) {
    student_api_json_ok(['ok'=>false,'message'=>$e->getMessage(),'csrf_token'=>$csrf_token]);
}

