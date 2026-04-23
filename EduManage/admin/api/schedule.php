<?php
/**
 * admin/api/schedule.php
 * 管理后台排课接口：处理排课校验、冲突检查以及新增、修改、删除排课动作。
 */

function admin_schedule_error_response(bool $isAjax, string $message, int $status = 400): void
{
    admin_api_error_response($isAjax, 'schedule_manage.php', $message, $status);
}

function admin_schedule_success_response(bool $isAjax, string $message): void
{
    if ($isAjax) {
        admin_api_json_response(true, ['message' => $message]);
    }

    admin_api_redirect('schedule_manage.php', ['success' => $message]);
}

function admin_schedule_day_name_cn(int $day): string
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

function admin_schedule_slot_map(PDO $pdo): array
{
    $stmt = $pdo->query('SELECT slot_id, slot_name, start_time, end_time FROM time_slot ORDER BY start_time, end_time, slot_id');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $index => $row) {
        $row['slot_id'] = (int) $row['slot_id'];
        $row['_index'] = $index;
        $map[(int) $row['slot_id']] = $row;
    }

    return $map;
}

function admin_schedule_resolve_slot_range(PDO $pdo, int $slotStartId, int $slotEndId, bool $isAjax): array
{
    $slots = admin_schedule_slot_map($pdo);
    if (!isset($slots[$slotStartId])) {
        admin_schedule_error_response($isAjax, '开始节次不存在，请重新选择。');
    }
    if (!isset($slots[$slotEndId])) {
        admin_schedule_error_response($isAjax, '结束节次不存在，请重新选择。');
    }

    $startSlot = $slots[$slotStartId];
    $endSlot = $slots[$slotEndId];
    if ($startSlot['_index'] > $endSlot['_index']) {
        admin_schedule_error_response($isAjax, '结束节次不能早于开始节次。请至少选择 1 个完整时间槽。');
    }

    return [
        'start_time' => $startSlot['start_time'],
        'end_time' => $endSlot['end_time'],
    ];
}

function admin_schedule_validate_common(PDO $pdo, array $input, bool $isAjax): array
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
        admin_schedule_error_response($isAjax, '请选择课程。');
    }
    if ($teacherId <= 0) {
        admin_schedule_error_response($isAjax, '请选择任课教师。');
    }
    if ($year < 2000 || $year > 2099) {
        admin_schedule_error_response($isAjax, '学年格式不正确，请填写 2000 到 2099 之间的年份。');
    }
    $semesterOptions = app_enum_keys('semester');
    if ($semesterOptions === []) {
        $semesterOptions = [app_default_current_semester()];
    }
    if (!in_array($semester, $semesterOptions, true)) {
        admin_schedule_error_response($isAjax, '学期只能是 ' . implode(' 或 ', $semesterOptions) . '。');
    }
    if ($capacity <= 0) {
        admin_schedule_error_response($isAjax, '容量必须大于 0。');
    }
    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        admin_schedule_error_response($isAjax, '星期参数不正确。');
    }
    if ($weekStart <= 0 || $weekEnd <= 0) {
        admin_schedule_error_response($isAjax, '周次必须为正整数。');
    }
    if ($weekStart > $weekEnd) {
        admin_schedule_error_response($isAjax, '开始周不能大于结束周。');
    }
    if ($classroomId <= 0) {
        admin_schedule_error_response($isAjax, '请选择教室。');
    }

    $slotRange = admin_schedule_resolve_slot_range($pdo, $slotStartId, $slotEndId, $isAjax);

    $courseStmt = $pdo->prepare('SELECT course_id, name FROM course WHERE course_id = ? LIMIT 1');
    $courseStmt->execute([$courseId]);
    $course = $courseStmt->fetch(PDO::FETCH_ASSOC);
    if (!$course) {
        admin_schedule_error_response($isAjax, '所选课程不存在，请刷新页面后重试。');
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
        admin_schedule_error_response($isAjax, '所选教师不存在，请刷新页面后重试。');
    }

    $classroomStmt = $pdo->prepare('SELECT classroom_id, building, room_number FROM classroom WHERE classroom_id = ? LIMIT 1');
    $classroomStmt->execute([$classroomId]);
    $classroom = $classroomStmt->fetch(PDO::FETCH_ASSOC);
    if (!$classroom) {
        admin_schedule_error_response($isAjax, '所选教室不存在，请刷新页面后重试。');
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

function admin_schedule_find_teacher_conflicts(PDO $pdo, int $teacherId, int $dayOfWeek, string $startTime, string $endTime, int $weekStart, int $weekEnd, int $excludeScheduleId = 0): array
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

function admin_schedule_find_room_conflicts(PDO $pdo, int $classroomId, int $dayOfWeek, string $startTime, string $endTime, int $weekStart, int $weekEnd, int $excludeScheduleId = 0): array
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

function admin_schedule_assert_no_conflicts(PDO $pdo, array $payload, bool $isAjax, int $excludeScheduleId = 0): void
{
    $teacherConflicts = admin_schedule_find_teacher_conflicts(
        $pdo,
        $payload['teacher_id'],
        $payload['day_of_week'],
        $payload['start_time'],
        $payload['end_time'],
        $payload['week_start'],
        $payload['week_end'],
        $excludeScheduleId
    );

    $roomConflicts = admin_schedule_find_room_conflicts(
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
        $room = (!empty($row['building']) && !empty($row['room_number']))
            ? ($row['building'] . '-' . $row['room_number'])
            : '未设置教室';
        $messages[] = '教师“' . $payload['teacher_name'] . '”在' . admin_schedule_day_name_cn($payload['day_of_week']) . ' '
            . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5)
            . '已安排《' . $row['course_name'] . '》'
            . '（第' . (int) $row['week_start'] . '-' . (int) $row['week_end'] . '周，教室 ' . $room . '），因此不能重复排课。';
    }

    foreach ($roomConflicts as $row) {
        $teacherName = $row['teacher_name'] ?: '未分配教师';
        $messages[] = '教室“' . $payload['classroom_name'] . '”在' . admin_schedule_day_name_cn($payload['day_of_week']) . ' '
            . substr((string) $row['start_time'], 0, 5) . '-' . substr((string) $row['end_time'], 0, 5)
            . '已被《' . $row['course_name'] . '》占用'
            . '（任课教师 ' . $teacherName . '，第' . (int) $row['week_start'] . '-' . (int) $row['week_end'] . '周），因此不能重复使用。';
    }

    if ($messages !== []) {
        admin_schedule_error_response($isAjax, '无法保存排课：' . implode('；', $messages));
    }
}

if (!in_array($act, ['add_schedule', 'update_schedule', 'del_schedule'], true)) {
    return false;
}

switch ($act) {
    case 'add_schedule':
        $payload = admin_schedule_validate_common($pdo, $_POST, $isAjax);

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
            admin_schedule_error_response(
                $isAjax,
                '《' . $payload['course_name'] . '》在 ' . $payload['year'] . ' ' . $payload['semester']
                . ' 学期的开课节已经存在（section_id=' . (int) $duplicateSection['section_id'] . $teacherLabel
                . '）。如需调整，请编辑现有排课，不要重复新增。'
            );
        }

        admin_schedule_assert_no_conflicts($pdo, $payload, $isAjax);

        try {
            $pdo->beginTransaction();

            if ($duplicateSection) {
                $sectionId = (int) $duplicateSection['section_id'];
                $pdo->prepare('UPDATE section SET capacity = ? WHERE section_id = ?')->execute([$payload['capacity'], $sectionId]);
                $pdo->prepare('DELETE FROM teaching WHERE section_id = ?')->execute([$sectionId]);
            } else {
                $sectionStmt = $pdo->prepare('INSERT INTO section (semester, year, course_id, capacity) VALUES (?, ?, ?, ?)');
                $sectionStmt->execute([$payload['semester'], $payload['year'], $payload['course_id'], $payload['capacity']]);
                $sectionId = (int) $pdo->lastInsertId();
            }

            $pdo->prepare('INSERT INTO teaching (teacher_id, section_id) VALUES (?, ?)')->execute([
                $payload['teacher_id'],
                $sectionId,
            ]);

            $scheduleStmt = $pdo->prepare('
                INSERT INTO schedule (section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');
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

            admin_schedule_success_response($isAjax, '排课新增成功。');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_schedule_error_response($isAjax, '排课保存失败，请稍后重试。', 500);
        }
        break;

    case 'update_schedule':
        $payload = admin_schedule_validate_common($pdo, $_POST, $isAjax);
        if ($payload['schedule_id'] <= 0) {
            admin_schedule_error_response($isAjax, '缺少 schedule_id，无法修改。');
        }

        $currentStmt = $pdo->prepare('SELECT sch.schedule_id, sch.section_id FROM schedule sch WHERE sch.schedule_id = ? LIMIT 1');
        $currentStmt->execute([$payload['schedule_id']]);
        $current = $currentStmt->fetch(PDO::FETCH_ASSOC);
        if (!$current) {
            admin_schedule_error_response($isAjax, '要修改的排课不存在。');
        }

        $dupStmt = $pdo->prepare('
            SELECT section_id
            FROM section
            WHERE course_id = ? AND year = ? AND semester = ? AND section_id <> ?
            LIMIT 1
        ');
        $dupStmt->execute([$payload['course_id'], $payload['year'], $payload['semester'], (int) $current['section_id']]);
        if ($dupStmt->fetch()) {
            admin_schedule_error_response($isAjax, '无法修改：该课程在同一学年和学期下已经存在其他开课节。请保持“课程 + 学年 + 学期”唯一。');
        }

        admin_schedule_assert_no_conflicts($pdo, $payload, $isAjax, $payload['schedule_id']);

        try {
            $pdo->beginTransaction();

            $sectionStmt = $pdo->prepare('
                UPDATE section
                SET course_id = ?, year = ?, semester = ?, capacity = ?
                WHERE section_id = ?
            ');
            $sectionStmt->execute([
                $payload['course_id'],
                $payload['year'],
                $payload['semester'],
                $payload['capacity'],
                (int) $current['section_id'],
            ]);

            $pdo->prepare('DELETE FROM teaching WHERE section_id = ?')->execute([(int) $current['section_id']]);
            $pdo->prepare('INSERT INTO teaching (teacher_id, section_id) VALUES (?, ?)')->execute([
                $payload['teacher_id'],
                (int) $current['section_id'],
            ]);

            $scheduleStmt = $pdo->prepare('
                UPDATE schedule
                SET day_of_week = ?, start_time = ?, end_time = ?, classroom_id = ?, week_start = ?, week_end = ?
                WHERE schedule_id = ?
            ');
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

            admin_schedule_success_response($isAjax, '排课修改成功。');
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_schedule_error_response($isAjax, '排课修改失败，请稍后重试。', 500);
        }
        break;

    case 'del_schedule':
        $scheduleId = (int) ($_POST['schedule_id'] ?? $_GET['schedule_id'] ?? 0);
        if ($scheduleId <= 0) {
            admin_schedule_error_response($isAjax, '缺少 schedule_id，无法删除。');
        }

        $stmt = $pdo->prepare('
            SELECT sch.schedule_id, sch.section_id, c.name AS course_name
            FROM schedule sch
            JOIN section sec ON sec.section_id = sch.section_id
            JOIN course c ON c.course_id = sec.course_id
            WHERE sch.schedule_id = ?
            LIMIT 1
        ');
        $stmt->execute([$scheduleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            admin_schedule_error_response($isAjax, '要删除的排课不存在。');
        }

        try {
            $pdo->prepare('DELETE FROM schedule WHERE schedule_id = ?')->execute([$scheduleId]);

            if (function_exists('sys_log')) {
                $desc = '删除排课: 《' . $row['course_name'] . '》 / ID ' . $scheduleId;
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'schedule', $scheduleId);
            }

            admin_schedule_success_response($isAjax, '排课删除成功。');
        } catch (Throwable $e) {
            admin_schedule_error_response($isAjax, '排课删除失败，请稍后重试。', 500);
        }
        break;
}

return true;
