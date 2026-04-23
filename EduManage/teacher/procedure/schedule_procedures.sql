/*
 Schedule Management — Stored Procedures & Functions
 Teacher Course Schedule Management (查课表、调课、周课表)
 These procedures do NOT modify school_db existing tables
 Target Schema: school_db
 Date: 2026-03-30
*/

USE school_db;
DELIMITER $$

-- ============================================================
-- PROCEDURE: sp_get_schedule
-- Get all schedule records for a section
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_schedule$$
CREATE PROCEDURE sp_get_schedule(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        TIMEDIFF(s.end_time, s.start_time) AS duration,
        CONCAT(
            CASE s.day_of_week
                WHEN 1 THEN 'Monday'
                WHEN 2 THEN 'Tuesday'
                WHEN 3 THEN 'Wednesday'
                WHEN 4 THEN 'Thursday'
                WHEN 5 THEN 'Friday'
                WHEN 6 THEN 'Saturday'
                WHEN 7 THEN 'Sunday'
            END,
            ' ',
            DATE_FORMAT(s.start_time, '%H:%i'),
            '-',
            DATE_FORMAT(s.end_time, '%H:%i')
        ) AS schedule_display
    FROM schedule s
    JOIN section sec ON s.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    WHERE s.section_id = p_section_id
    ORDER BY s.day_of_week, s.start_time;
END$$

-- ============================================================
-- PROCEDURE: sp_get_teacher_schedule
-- Get all schedules for classes taught by a teacher
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_teacher_schedule$$
CREATE PROCEDURE sp_get_teacher_schedule(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        u.name AS teacher_name,
        COUNT(DISTINCT tk.student_id) AS enrolled_count,
        CASE s.day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END AS day_name
    FROM teaching tg
    JOIN schedule s ON tg.section_id = s.section_id
    JOIN section sec ON s.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    JOIN user u ON tg.teacher_id = u.user_id
    LEFT JOIN takes tk ON s.section_id = tk.section_id AND tk.status = 'enrolled'
    WHERE tg.teacher_id = p_teacher_id
    GROUP BY s.schedule_id, s.section_id, s.day_of_week, s.start_time,
             s.end_time, s.classroom_id, cl.building, cl.room_number, s.week_start, s.week_end,
             c.course_id, c.name, sec.semester, sec.year, u.name
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), s.day_of_week, s.start_time;
END$$

-- ============================================================
-- FUNCTION: fn_check_room_conflict
-- Check if a room has conflicts for given time slot
-- Returns: number of conflicts (0 = no conflict)
-- ============================================================
DROP FUNCTION IF EXISTS fn_check_room_conflict$$
CREATE FUNCTION fn_check_room_conflict(
    p_classroom_id INT UNSIGNED,
    p_day_of_week INT,
    p_start_time TIME,
    p_end_time TIME,
    p_exclude_schedule_id INT UNSIGNED
)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count
    FROM schedule
    WHERE classroom_id = p_classroom_id
      AND day_of_week = p_day_of_week
      AND schedule_id != p_exclude_schedule_id
      AND (
          (p_start_time < end_time AND p_end_time > start_time)
      );

    RETURN v_count;
END$$

-- ============================================================
-- FUNCTION: fn_check_teacher_conflict
-- Check if teacher has conflicting schedules
-- Returns: number of conflicts (0 = no conflict)
-- ============================================================
DROP FUNCTION IF EXISTS fn_check_teacher_conflict$$
CREATE FUNCTION fn_check_teacher_conflict(
    p_teacher_id INT UNSIGNED,
    p_day_of_week INT,
    p_start_time TIME,
    p_end_time TIME,
    p_exclude_schedule_id INT UNSIGNED
)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE tg.teacher_id = p_teacher_id
      AND s.day_of_week = p_day_of_week
      AND s.schedule_id != p_exclude_schedule_id
      AND (
          (p_start_time < s.end_time AND p_end_time > s.start_time)
      );

    RETURN v_count;
END$$

-- ============================================================
-- PROCEDURE: sp_update_schedule
-- Update schedule (change location, time, or day)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_update_schedule$$
CREATE PROCEDURE sp_update_schedule(
    IN p_schedule_id INT UNSIGNED,
    IN p_day_of_week INT,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_classroom_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_room_conflict INT;
    DECLARE v_teacher_conflict INT;

    SELECT s.section_id INTO v_section_id
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE s.schedule_id = p_schedule_id AND tg.teacher_id = p_teacher_id;

    IF v_section_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Not authorized or schedule not found';
    ELSEIF p_end_time <= p_start_time THEN
        SET p_success = FALSE;
        SET p_message = 'End time must be after start time';
    ELSE
        SET v_room_conflict = fn_check_room_conflict(
            p_classroom_id, p_day_of_week, p_start_time, p_end_time, p_schedule_id
        );

        IF v_room_conflict > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Room is already reserved at this time';
        ELSE
            SET v_teacher_conflict = fn_check_teacher_conflict(
                p_teacher_id, p_day_of_week, p_start_time, p_end_time, p_schedule_id
            );

            IF v_teacher_conflict > 0 THEN
                SET p_success = FALSE;
                SET p_message = 'Teacher has conflicting schedule at this time';
            ELSE
                UPDATE schedule
                SET day_of_week = p_day_of_week,
                    start_time = p_start_time,
                    end_time = p_end_time,
                    classroom_id = p_classroom_id
                WHERE schedule_id = p_schedule_id;

                SET p_success = TRUE;
                SET p_message = 'Schedule updated successfully';
            END IF;
        END IF;
    END IF;
END$$

-- ============================================================
-- PROCEDURE: sp_add_schedule
-- Add a new schedule for a section
-- ============================================================
DROP PROCEDURE IF EXISTS sp_add_schedule$$
CREATE PROCEDURE sp_add_schedule(
    IN p_section_id INT UNSIGNED,
    IN p_day_of_week INT,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_classroom_id INT UNSIGNED,
    IN p_week_start INT UNSIGNED,
    IN p_week_end INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_schedule_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_room_conflict INT;
    DECLARE v_teacher_conflict INT;

    SELECT section_id INTO v_section_id
    FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_section_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Teacher does not teach this section';
        SET p_schedule_id = NULL;
    ELSEIF p_end_time <= p_start_time THEN
        SET p_success = FALSE;
        SET p_message = 'End time must be after start time';
        SET p_schedule_id = NULL;
    ELSEIF p_day_of_week < 1 OR p_day_of_week > 7 THEN
        SET p_success = FALSE;
        SET p_message = 'Day of week must be between 1 and 7';
        SET p_schedule_id = NULL;
    ELSE
        SET v_room_conflict = fn_check_room_conflict(
            p_classroom_id, p_day_of_week, p_start_time, p_end_time, 0
        );

        IF v_room_conflict > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Room is already reserved at this time';
            SET p_schedule_id = NULL;
        ELSE
            SET v_teacher_conflict = fn_check_teacher_conflict(
                p_teacher_id, p_day_of_week, p_start_time, p_end_time, 0
            );

            IF v_teacher_conflict > 0 THEN
                SET p_success = FALSE;
                SET p_message = 'Teacher has conflicting schedule at this time';
                SET p_schedule_id = NULL;
            ELSE
                INSERT INTO schedule (section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
                VALUES (p_section_id, p_day_of_week, p_start_time, p_end_time, p_classroom_id, p_week_start, p_week_end);

                SET p_schedule_id = LAST_INSERT_ID();
                SET p_success = TRUE;
                SET p_message = 'Schedule added successfully';
            END IF;
        END IF;
    END IF;
END$$

-- ============================================================
-- PROCEDURE: sp_delete_schedule
-- Delete a schedule record
-- ============================================================
DROP PROCEDURE IF EXISTS sp_delete_schedule$$
CREATE PROCEDURE sp_delete_schedule(
    IN p_schedule_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_count INT;

    SELECT COUNT(*) INTO v_count
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE s.schedule_id = p_schedule_id AND tg.teacher_id = p_teacher_id;

    IF v_count = 0 THEN
        SET p_success = FALSE;
        SET p_message = 'Not authorized or schedule not found';
    ELSE
        DELETE FROM schedule WHERE schedule_id = p_schedule_id;
        SET p_success = TRUE;
        SET p_message = 'Schedule deleted successfully';
    END IF;
END$$

-- ============================================================
-- PROCEDURE: sp_get_teacher_weekly_schedule  [NEW]
-- Get teacher's schedule for a specific week number
-- Filters: week_start <= p_week AND week_end >= p_week
-- Returns extra fields needed for timetable rendering
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_teacher_weekly_schedule$$
CREATE PROCEDURE sp_get_teacher_weekly_schedule(
    IN p_teacher_id INT UNSIGNED,
    IN p_week       INT UNSIGNED
)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        TIME_FORMAT(s.start_time, '%H:%i')      AS start_time,
        TIME_FORMAT(s.end_time,   '%H:%i')      AS end_time,
        TIME_TO_SEC(s.start_time) / 60          AS start_minutes,
        TIME_TO_SEC(s.end_time)   / 60          AS end_minutes,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name                                  AS course_name,
        c.credit,
        c.hours,
        c.description,
        sec.semester,
        sec.year,
        COUNT(DISTINCT tk.student_id)           AS enrolled_count,
        sec.capacity,
        CASE s.day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END                                     AS day_name
    FROM teaching tg
    JOIN schedule  s   ON tg.section_id  = s.section_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    JOIN section   sec ON s.section_id   = sec.section_id
    JOIN course    c   ON sec.course_id  = c.course_id
    LEFT JOIN takes tk ON s.section_id   = tk.section_id AND tk.status = 'enrolled'
    WHERE tg.teacher_id = p_teacher_id
      AND s.week_start  <= p_week
      AND s.week_end    >= p_week
    GROUP BY
        s.schedule_id, s.section_id, s.day_of_week, s.start_time, s.end_time,
        s.classroom_id, cl.building, cl.room_number, s.week_start, s.week_end,
        c.course_id, c.name, c.credit, c.hours, c.description,
        sec.semester, sec.year, sec.capacity
    ORDER BY s.day_of_week, s.start_time;
END$$

-- ============================================================
-- PROCEDURE: sp_get_teacher_week_range  [NEW]
-- Return the min/max week numbers across all the teacher's schedules
-- Used to populate the week selector in the UI
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_teacher_week_range$$
CREATE PROCEDURE sp_get_teacher_week_range(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        COALESCE(MIN(s.week_start), 1)  AS min_week,
        COALESCE(MAX(s.week_end),   16) AS max_week
    FROM teaching tg
    JOIN schedule s ON tg.section_id = s.section_id
    WHERE tg.teacher_id = p_teacher_id;
END$$

DELIMITER ;