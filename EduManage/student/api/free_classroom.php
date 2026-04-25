<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
student_api_bootstrap();
student_api_no_cache();

require_once __DIR__ . '/../../components/db.php';
$pdo = student_api_require_pdo();

$uid = student_api_require_login();
unset($uid);

$STU_CFG = include __DIR__ . '/config.php';
$TOTAL_WEEKS = max(1, (int) ($STU_CFG['schedule']['total_weeks'] ?? 16));
$CURRENT_YEAR = (int) ($STU_CFG['term']['current_year'] ?? date('Y'));
$CURRENT_SEMESTER = (string) ($STU_CFG['term']['current_semester'] ?? student_term_default_semester());

function free_room_current_week(array $cfg, int $totalWeeks): int
{
    $startDate = (string) ($cfg['term']['start_date'] ?? '');
    $startTs = strtotime($startDate);
    if (!$startTs) {
        return 1;
    }

    $todayTs = strtotime(date('Y-m-d'));
    if (!$todayTs || $todayTs <= $startTs) {
        return 1;
    }

    $dayDiff = (int) floor(($todayTs - $startTs) / 86400);
    $week = (int) floor($dayDiff / 7) + 1;
    if ($week < 1) {
        $week = 1;
    }
    if ($week > $totalWeeks) {
        $week = $totalWeeks;
    }
    return $week;
}

function free_room_day_name(int $day): string
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

function free_room_slot_map(PDO $pdo): array
{
    try {
        $rows = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_time_slots')
            : [];
    } catch (Throwable $e) {
        $stmt = $pdo->query("SELECT slot_id, slot_name, start_time, end_time FROM time_slot ORDER BY start_time, end_time, slot_id");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $map = [];
    foreach ($rows as $index => $row) {
        $row['slot_id'] = (int) $row['slot_id'];
        $row['_index'] = $index;
        $map[$row['slot_id']] = $row;
    }

    return $map;
}

function free_room_classrooms(PDO $pdo, int $classroomId = 0): array
{
    try {
        if (function_exists('app_call_rows')) {
            return app_call_rows($pdo, 'sp_project_get_classrooms', [$classroomId]);
        }
    } catch (Throwable $e) {
    }

    if ($classroomId > 0) {
        $stmt = $pdo->prepare("
            SELECT classroom_id, building, room_number, capacity, type
            FROM classroom
            WHERE classroom_id = ?
            ORDER BY building, room_number
        ");
        $stmt->execute([$classroomId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = $pdo->query("
        SELECT classroom_id, building, room_number, capacity, type
        FROM classroom
        ORDER BY building, room_number
    ");

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    if ($pdo === null) {
        student_api_json_error('数据库连接失败', 500, ['error' => 'db_error']);
    }

    $slotMap = free_room_slot_map($pdo);
    $classroomId = (int) ($_GET['classroom_id'] ?? 0);

    $basePayload = [
        'success' => true,
        'data' => [
            'total_weeks' => $TOTAL_WEEKS,
            'current_week' => free_room_current_week($STU_CFG, $TOTAL_WEEKS),
            'term_label' => ((string) ($STU_CFG['term']['current_year'] ?? date('Y'))) . ' 年 '
                . ((string) ($STU_CFG['term']['current_semester_cn'] ?? '当前学期')),
            'term_year' => $CURRENT_YEAR,
            'term_semester' => $CURRENT_SEMESTER,
            'time_slots' => student_api_utf8(array_values(array_map(static function ($slot) {
                unset($slot['_index']);
                return $slot;
            }, $slotMap))),
            'classrooms' => student_api_utf8(free_room_classrooms($pdo)),
        ],
    ];

    $searchMode = (string) ($_GET['action'] ?? '') === 'search';
    if (!$searchMode) {
        student_api_json_ok($basePayload);
    }

    $week = (int) ($_GET['week'] ?? 0);
    $dayOfWeek = (int) ($_GET['day_of_week'] ?? 0);
    $slotStartId = (int) ($_GET['slot_start_id'] ?? 0);
    $slotEndId = (int) ($_GET['slot_end_id'] ?? 0);

    if ($week < 1 || $week > $TOTAL_WEEKS) {
        student_api_json_error('查询周次不正确，请重新选择。');
    }
    if ($dayOfWeek < 1 || $dayOfWeek > 7) {
        student_api_json_error('星期参数不正确，请重新选择。');
    }
    if (!isset($slotMap[$slotStartId])) {
        student_api_json_error('开始节次不存在，请重新选择。');
    }
    if (!isset($slotMap[$slotEndId])) {
        student_api_json_error('结束节次不存在，请重新选择。');
    }
    if ($slotMap[$slotStartId]['_index'] > $slotMap[$slotEndId]['_index']) {
        student_api_json_error('结束节次不能早于开始节次。');
    }

    $classrooms = free_room_classrooms($pdo, $classroomId);
    if ($classroomId > 0 && empty($classrooms)) {
        student_api_json_error('指定教室不存在，请刷新页面后重试。');
    }

    $startTime = (string) $slotMap[$slotStartId]['start_time'];
    $endTime = (string) $slotMap[$slotEndId]['end_time'];

    try {
        $conflictRows = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_classroom_conflicts', [
                $week,
                $dayOfWeek,
                $startTime,
                $endTime,
                $CURRENT_YEAR,
                $CURRENT_SEMESTER,
                $TOTAL_WEEKS,
                $classroomId,
            ])
            : [];
    } catch (Throwable $e) {
        $conflictStmt = $pdo->prepare("
            SELECT
                sch.classroom_id,
                sch.schedule_id,
                sch.start_time,
                sch.end_time,
                COALESCE(sch.week_start, 1) AS week_start,
                COALESCE(sch.week_end, ?) AS week_end,
                c.name AS course_name,
                COALESCE(tea.teacher_names, '未安排教师') AS teacher_names
            FROM schedule sch
            JOIN section sec ON sec.section_id = sch.section_id
            JOIN course c ON c.course_id = sec.course_id
            LEFT JOIN (
                SELECT
                    tg.section_id,
                    GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR '、') AS teacher_names
                FROM teaching tg
                JOIN user u ON u.user_id = tg.teacher_id
                GROUP BY tg.section_id
            ) tea ON tea.section_id = sch.section_id
            WHERE sch.day_of_week = ?
              AND sec.year = ?
              AND sec.semester = ?
              AND ? < sch.end_time
              AND ? > sch.start_time
              AND ? BETWEEN COALESCE(sch.week_start, 1) AND COALESCE(sch.week_end, ?)
              AND (? = 0 OR sch.classroom_id = ?)
            ORDER BY sch.classroom_id, sch.start_time, sch.schedule_id
        ");
        $conflictStmt->execute([
            $TOTAL_WEEKS,
            $dayOfWeek,
            $CURRENT_YEAR,
            $CURRENT_SEMESTER,
            $startTime,
            $endTime,
            $week,
            $TOTAL_WEEKS,
            $classroomId,
            $classroomId,
        ]);
        $conflictRows = $conflictStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $conflictMap = [];
    foreach ($conflictRows as $row) {
        $cid = (int) $row['classroom_id'];
        $conflictMap[$cid][] = [
            'schedule_id' => (int) $row['schedule_id'],
            'course_name' => $row['course_name'],
            'teacher_names' => $row['teacher_names'],
            'start_time' => $row['start_time'],
            'end_time' => $row['end_time'],
            'week_start' => (int) $row['week_start'],
            'week_end' => (int) $row['week_end'],
        ];
    }

    $resultItems = [];
    $freeCount = 0;
    $occupiedCount = 0;
    foreach ($classrooms as $room) {
        $cid = (int) $room['classroom_id'];
        $conflicts = $conflictMap[$cid] ?? [];
        $isFree = empty($conflicts);
        if ($isFree) {
            $freeCount++;
        } else {
            $occupiedCount++;
        }

        $roomLabel = $room['building'] . '-' . $room['room_number'];
        $resultItems[] = [
            'classroom_id' => $cid,
            'room_label' => $roomLabel,
            'building' => $room['building'],
            'room_number' => $room['room_number'],
            'capacity' => (int) ($room['capacity'] ?? 0),
            'type' => $room['type'] ?? 'normal',
            'is_free' => $isFree,
            'status_text' => $isFree ? '空闲' : '占用中',
            'conflicts' => $conflicts,
        ];
    }

    $searchSummary = [
        'week' => $week,
        'day_of_week' => $dayOfWeek,
        'day_label' => free_room_day_name($dayOfWeek),
        'slot_start_id' => $slotStartId,
        'slot_end_id' => $slotEndId,
        'slot_start_name' => $slotMap[$slotStartId]['slot_name'],
        'slot_end_name' => $slotMap[$slotEndId]['slot_name'],
        'start_time' => $startTime,
        'end_time' => $endTime,
        'classroom_id' => $classroomId,
        'free_count' => $freeCount,
        'occupied_count' => $occupiedCount,
    ];

    $basePayload['data']['search'] = student_api_utf8($searchSummary);
    $basePayload['data']['results'] = student_api_utf8($resultItems);
    student_api_json_ok($basePayload);
} catch (Throwable $e) {
    student_api_json_error('空闲教室查询失败：' . $e->getMessage(), 500, ['error' => 'server_error']);
}
