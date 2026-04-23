<?php
/**
 * admin/lib/queries.php
 * 管理后台共享查询层：集中放置页面间复用的基础查询，便于后续按模块分工维护。
 */

declare(strict_types=1);

function admin_call_rows(PDO $pdo, string $procedure, array $params = []): ?array
{
    try {
        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        $stmt = $pdo->prepare('CALL ' . $procedure . '(' . $placeholders . ')');
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        return $rows;
    } catch (Throwable $e) {
        return null;
    }
}

function admin_call_multi_result_rows(PDO $pdo, string $procedure, array $params = []): ?array
{
    try {
        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        $stmt = $pdo->prepare('CALL ' . $procedure . '(' . $placeholders . ')');
        $stmt->execute($params);

        $sets = [];
        do {
            $sets[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } while ($stmt->nextRowset());

        $stmt->closeCursor();
        return $sets;
    } catch (Throwable $e) {
        return null;
    }
}

function admin_fetch_departments(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_departments');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query('SELECT dept_id, dept_name FROM department ORDER BY dept_name');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_majors(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_majors');
    if ($rows !== null) {
        return $rows;
    }

    try {
        $stmt = $pdo->query('SELECT major_id, major_name FROM major ORDER BY major_name');
        return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
        return [];
    }
}

function admin_index_rows_by_int_key(array $rows, string $idKey, string $labelKey): array
{
    $map = [];
    foreach ($rows as $row) {
        if (!isset($row[$idKey])) {
            continue;
        }

        $map[(int) $row[$idKey]] = (string) ($row[$labelKey] ?? '');
    }

    return $map;
}

function admin_fetch_dashboard_counts(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_dashboard_counts');
    if ($rows !== null && isset($rows[0])) {
        return array_map('intval', $rows[0]);
    }

    $queries = [
        'students' => 'SELECT COUNT(*) FROM student',
        'teachers' => 'SELECT COUNT(*) FROM teacher',
        'courses' => 'SELECT COUNT(*) FROM course',
        'announcements' => 'SELECT COUNT(*) FROM announcement',
        'classrooms' => 'SELECT COUNT(*) FROM classroom',
        'schedules' => 'SELECT COUNT(*) FROM schedule',
        'departments' => 'SELECT COUNT(*) FROM department',
        'majors' => 'SELECT COUNT(*) FROM major',
    ];

    $counts = [];
    foreach ($queries as $key => $sql) {
        try {
            $stmt = $pdo->query($sql);
            $counts[$key] = $stmt ? (int) $stmt->fetchColumn() : 0;
        } catch (Throwable $e) {
            $counts[$key] = 0;
        }
    }

    return $counts;
}

function admin_fetch_courses(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_courses');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query('SELECT * FROM course ORDER BY course_id DESC');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_classrooms(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_classrooms');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query('SELECT * FROM classroom ORDER BY building, room_number');
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_departments_with_stats(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_department_stats');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query("
        SELECT
            d.dept_id,
            d.dept_name,
            d.dept_code,
            (SELECT COUNT(*) FROM teacher t WHERE t.dept_id = d.dept_id) AS teacher_count,
            (SELECT COUNT(*) FROM student s WHERE s.dept_id = d.dept_id) AS student_count,
            (SELECT COUNT(*) FROM major m WHERE m.dept_id = d.dept_id) AS major_count
        FROM department d
        ORDER BY d.dept_code, d.dept_name, d.dept_id
    ");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_major_columns(PDO $pdo): array
{
    try {
        $colStmt = $pdo->query('SHOW COLUMNS FROM major');
        $columns = $colStmt ? $colStmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        $columns = [];
    }

    return [
        'has_dept' => in_array('dept_id', $columns, true),
        'has_code' => in_array('major_code', $columns, true),
        'has_name' => in_array('major_name', $columns, true),
    ];
}

function admin_fetch_majors_for_management(PDO $pdo): array
{
    $columns = admin_fetch_major_columns($pdo);

    if ($columns['has_dept']) {
        $stmt = $pdo->query("
            SELECT m.*, d.dept_name
            FROM major m
            LEFT JOIN department d ON m.dept_id = d.dept_id
            ORDER BY d.dept_name, m.major_code
        ");
    } elseif ($columns['has_code']) {
        $stmt = $pdo->query('SELECT m.* FROM major m ORDER BY m.major_code');
    } elseif ($columns['has_name']) {
        $stmt = $pdo->query('SELECT m.* FROM major m ORDER BY m.major_name');
    } else {
        $stmt = $pdo->query('SELECT m.* FROM major m ORDER BY m.major_id');
    }

    return [
        'columns' => $columns,
        'rows' => $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [],
    ];
}

function admin_fetch_manageable_admins(PDO $pdo, int $currentUserId): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_manageable_admins', [$currentUserId]);
    if ($rows !== null) {
        return $rows;
    }

    $superAdminRole = app_super_admin_role();
    $stmt = $pdo->prepare('
        SELECT a.user_id, a.role, u.name, u.email
        FROM admin a
        LEFT JOIN user u ON a.user_id = u.user_id
        WHERE a.user_id != ? AND a.role != ?
        ORDER BY a.user_id DESC
    ');
    $stmt->execute([$currentUserId, $superAdminRole]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function admin_fetch_profile_user(PDO $pdo, int $userId): ?array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_profile_user', [$userId]);
    if ($rows !== null) {
        return $rows[0] ?? null;
    }

    $stmt = $pdo->prepare('SELECT user_id, name, email, gender, phone, image FROM user WHERE user_id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function admin_fetch_recent_system_logs(PDO $pdo, int $limit = 20): array
{
    $limit = max(1, $limit);
    $rows = admin_call_rows($pdo, 'sp_admin_get_recent_system_logs', [$limit]);
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query("
        SELECT l.*, u.name AS user_name, NULL AS user_role
        FROM system_log l
        LEFT JOIN user u ON l.user_id = u.user_id
        ORDER BY l.log_id DESC
        LIMIT {$limit}
    ");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_user_avatar_path(PDO $pdo, int $userId): string
{
    $avatarPath = app_default_avatar_url();
    if ($userId <= 0) {
        return $avatarPath;
    }

    try {
        $stmt = $pdo->prepare('SELECT image FROM user WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $image = $stmt->fetchColumn();
        if (is_string($image)) {
            $image = trim($image);
            if ($image !== '' && $image !== '0') {
                $avatarFile = basename($image);
                $avatarFsPath = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR . $avatarFile;
                if (is_file($avatarFsPath)) {
                    return app_avatar_url($avatarFile);
                }
            }
        }
    } catch (Throwable $e) {
        return $avatarPath;
    }

    return $avatarPath;
}

function admin_schedule_day_name(int $day): string
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

function admin_fetch_schedule_total_weeks(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare("SELECT config_value FROM config WHERE config_key = 'schedule.total_weeks' LIMIT 1");
        $stmt->execute();
        $value = (int) $stmt->fetchColumn();
        if ($value >= 1 && $value <= 52) {
            return $value;
        }
    } catch (Throwable $e) {
    }

    return 20;
}

function admin_fetch_schedule_reference_data(PDO $pdo): array
{
    $sets = admin_call_multi_result_rows($pdo, 'sp_admin_get_schedule_reference_data');
    if ($sets !== null && count($sets) >= 5) {
        return [
            'courses' => $sets[0],
            'teachers' => $sets[1],
            'classrooms' => $sets[2],
            'timeSlots' => $sets[3],
            'maxWeeks' => (int) ($sets[4][0]['max_weeks'] ?? 20),
        ];
    }

    $courses = $pdo->query('SELECT course_id, name FROM course ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
    $teachers = $pdo->query("
        SELECT t.user_id, u.name, COALESCE(d.dept_name, '未分配院系') AS dept_name
        FROM teacher t
        JOIN user u ON t.user_id = u.user_id
        LEFT JOIN department d ON t.dept_id = d.dept_id
        ORDER BY d.dept_name, u.name
    ")->fetchAll(PDO::FETCH_ASSOC);
    $classrooms = $pdo->query("
        SELECT classroom_id, building, room_number, capacity, type
        FROM classroom
        ORDER BY building, room_number
    ")->fetchAll(PDO::FETCH_ASSOC);
    $timeSlots = $pdo->query("
        SELECT slot_id, slot_name, start_time, end_time
        FROM time_slot
        ORDER BY start_time, end_time, slot_id
    ")->fetchAll(PDO::FETCH_ASSOC);

    return [
        'courses' => $courses,
        'teachers' => $teachers,
        'classrooms' => $classrooms,
        'timeSlots' => $timeSlots,
        'maxWeeks' => admin_fetch_schedule_total_weeks($pdo),
    ];
}

function admin_fetch_students_for_management(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_students');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query("SELECT s.*, u.name, u.email, u.status, u.gender, u.phone, u.image, d.dept_name
                         FROM student s
                         JOIN user u ON s.user_id = u.user_id
                         LEFT JOIN department d ON s.dept_id = d.dept_id
                         ORDER BY s.user_id DESC");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_teachers_for_management(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_teachers');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query("SELECT t.*, u.name, u.email, u.status, u.gender, u.phone, u.image, d.dept_name
                         FROM teacher t
                         JOIN user u ON t.user_id = u.user_id
                         LEFT JOIN department d ON t.dept_id = d.dept_id
                         ORDER BY t.user_id DESC");
    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}

function admin_fetch_schedules_for_management(PDO $pdo): array
{
    $rows = admin_call_rows($pdo, 'sp_admin_get_schedule_list');
    if ($rows !== null) {
        return $rows;
    }

    $stmt = $pdo->query("
        SELECT
            sch.schedule_id,
            sch.section_id,
            sch.day_of_week,
            sch.start_time,
            sch.end_time,
            sch.week_start,
            sch.week_end,
            sec.course_id,
            sec.year,
            sec.semester,
            sec.capacity,
            c.name AS course_name,
            tea.teacher_id,
            tea.teacher_names,
            sch.classroom_id,
            cl.building,
            cl.room_number,
            ts_start.slot_id AS slot_start_id,
            ts_start.slot_name AS slot_start_name,
            ts_end.slot_id AS slot_end_id,
            ts_end.slot_name AS slot_end_name
        FROM schedule sch
        JOIN section sec ON sch.section_id = sec.section_id
        JOIN course c ON sec.course_id = c.course_id
        LEFT JOIN (
            SELECT
                tg.section_id,
                MIN(tg.teacher_id) AS teacher_id,
                GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR '、') AS teacher_names
            FROM teaching tg
            JOIN user u ON tg.teacher_id = u.user_id
            GROUP BY tg.section_id
        ) tea ON tea.section_id = sec.section_id
        LEFT JOIN classroom cl ON cl.classroom_id = sch.classroom_id
        LEFT JOIN time_slot ts_start ON ts_start.start_time = sch.start_time
        LEFT JOIN time_slot ts_end ON ts_end.end_time = sch.end_time
        ORDER BY sec.year DESC, sec.semester DESC, c.name ASC, sch.day_of_week ASC, sch.start_time ASC
    ");

    return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
}
