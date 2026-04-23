/*
 Teacher Portal — Unified Stored Procedures & Functions
 Consolidated from teacher/procedure/*.sql
 Target Schema : school_db
 Date          : 2026-04-21
*/

USE school_db;
DELIMITER $$

-- ============================================================
-- COMMON HELPERS (from common_procedures.sql)
-- ============================================================

-- fn_score_to_grade
DROP FUNCTION IF EXISTS fn_score_to_grade$$
CREATE FUNCTION fn_score_to_grade(p_score DECIMAL(5,2))
RETURNS VARCHAR(5)
DETERMINISTIC
READS SQL DATA
BEGIN
    IF p_score IS NULL THEN RETURN NULL; END IF;
    IF p_score >= 93 THEN RETURN 'A';
    ELSEIF p_score >= 90 THEN RETURN 'A-';
    ELSEIF p_score >= 87 THEN RETURN 'B+';
    ELSEIF p_score >= 83 THEN RETURN 'B';
    ELSEIF p_score >= 80 THEN RETURN 'B-';
    ELSEIF p_score >= 77 THEN RETURN 'C+';
    ELSEIF p_score >= 73 THEN RETURN 'C';
    ELSEIF p_score >= 70 THEN RETURN 'C-';
    ELSEIF p_score >= 60 THEN RETURN 'D';
    ELSE RETURN 'F';
    END IF;
END$$

-- fn_student_section_avg
DROP FUNCTION IF EXISTS fn_student_section_avg$$
CREATE FUNCTION fn_student_section_avg(
    p_student_id INT UNSIGNED,
    p_section_id INT UNSIGNED
)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_final    DECIMAL(5,2) DEFAULT NULL;
    DECLARE v_midterm  DECIMAL(5,2) DEFAULT NULL;
    DECLARE v_quiz     DECIMAL(5,2) DEFAULT NULL;
    DECLARE v_avg      DECIMAL(5,2) DEFAULT NULL;

    SELECT AVG(score) INTO v_final
    FROM exam
    WHERE student_id = p_student_id AND section_id = p_section_id
      AND exam_type = 'final' AND score IS NOT NULL;

    SELECT AVG(score) INTO v_midterm
    FROM exam
    WHERE student_id = p_student_id AND section_id = p_section_id
      AND exam_type = 'midterm' AND score IS NOT NULL;

    SELECT AVG(score) INTO v_quiz
    FROM exam
    WHERE student_id = p_student_id AND section_id = p_section_id
      AND exam_type = 'quiz' AND score IS NOT NULL;

    IF v_final IS NOT NULL OR v_midterm IS NOT NULL OR v_quiz IS NOT NULL THEN
        SET v_avg = (
            COALESCE(v_final * 0.5, 0) +
            COALESCE(v_midterm * 0.30, 0) +
            COALESCE(v_quiz * 0.20, 0)
        ) / (
            CASE WHEN v_final   IS NOT NULL THEN 0.50 ELSE 0 END +
            CASE WHEN v_midterm IS NOT NULL THEN 0.30 ELSE 0 END +
            CASE WHEN v_quiz    IS NOT NULL THEN 0.20 ELSE 0 END
        );
    END IF;

    RETURN ROUND(v_avg, 2);
END$$

-- fn_teacher_section_count
DROP FUNCTION IF EXISTS fn_teacher_section_count$$
CREATE FUNCTION fn_teacher_section_count(p_teacher_id INT UNSIGNED)
RETURNS INT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count FROM teaching WHERE teacher_id = p_teacher_id;
    RETURN v_count;
END$$

-- ============================================================
-- TEACHER PROFILE & DASHBOARD (from common + application)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_teacher_info$$
CREATE PROCEDURE sp_get_teacher_info(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.gender,
        u.image,
        u.status,
        u.created_at,
        t.title,
        d.dept_id,
        d.dept_name,
        d.dept_code,
        fn_teacher_section_count(p_teacher_id) AS section_count
    FROM user u
    JOIN teacher t ON u.user_id = t.user_id
    JOIN department d ON t.dept_id = d.dept_id
    WHERE u.user_id = p_teacher_id;
END$$

DROP PROCEDURE IF EXISTS sp_get_dashboard_stats$$
CREATE PROCEDURE sp_get_dashboard_stats(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        COUNT(DISTINCT tg.section_id) AS total_sections,
        COUNT(DISTINCT tk.student_id) AS total_students,
        COUNT(DISTINCT e.exam_id) AS total_exams,
        ROUND(AVG(e.score), 1) AS overall_avg,
        SUM(CASE WHEN tk.grade IS NULL AND tk.student_id IS NOT NULL THEN 1 ELSE 0 END) AS ungraded_count
    FROM teaching tg
    LEFT JOIN takes tk ON tg.section_id = tk.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON tg.section_id = e.section_id  AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id;
END$$

-- ============================================================
-- COURSE / ENROLLMENT / TEACHING (from application_procedures.sql)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_courses_to_apply$$
CREATE PROCEDURE sp_get_courses_to_apply(IN p_teacher_id INT UNSIGNED)
BEGIN
    DECLARE v_year YEAR DEFAULT YEAR(CURDATE());

    SELECT
        c.course_id,
        c.name,
        c.credit,
        c.hours,
        c.description,
        EXISTS (
            SELECT 1 FROM teaching tg
            JOIN section s ON s.section_id = tg.section_id
            WHERE tg.teacher_id = p_teacher_id
              AND s.course_id   = c.course_id
              AND s.year        = v_year
        ) AS already_teaching_now,
        (
            SELECT s2.section_id FROM section s2
            WHERE s2.course_id = c.course_id AND s2.year = v_year
            ORDER BY FIELD(s2.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_section_id,
        (
            SELECT s3.semester FROM section s3
            WHERE s3.course_id = c.course_id AND s3.year = v_year
            ORDER BY FIELD(s3.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_semester,
        (SELECT COUNT(*) FROM section s4 WHERE s4.course_id = c.course_id) AS total_sections
    FROM course c
    ORDER BY c.name;
END$$

DROP PROCEDURE IF EXISTS sp_apply_to_teach$$
CREATE PROCEDURE sp_apply_to_teach(
    IN  p_teacher_id    INT UNSIGNED,
    IN  p_course_id     INT UNSIGNED,
    IN  p_semester      VARCHAR(20),
    IN  p_year          YEAR,
    IN  p_capacity      SMALLINT UNSIGNED,
    IN  p_enroll_start  DATETIME,
    IN  p_enroll_end    DATETIME,
    OUT p_section_id    INT UNSIGNED,
    OUT p_success       TINYINT,
    OUT p_message       VARCHAR(500)
)
BEGIN
    DECLARE v_section_id  INT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher_ok  INT DEFAULT 0;
    DECLARE v_course_ok   INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLSTATE '45000'
    BEGIN
        ROLLBACK;
        SET p_success    = 0;
        SET p_section_id = NULL;
        SET p_message    = 'You are already assigned to teach this section.';
    END;

    SELECT COUNT(*) INTO v_teacher_ok FROM teacher WHERE user_id = p_teacher_id;
    SELECT COUNT(*) INTO v_course_ok  FROM course  WHERE course_id = p_course_id;

    IF v_teacher_ok = 0 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Teacher not found.';
    ELSEIF v_course_ok = 0 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Course not found.';
    ELSEIF p_semester NOT IN ('Spring','Fall') THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Semester must be Spring or Fall.';
    ELSEIF p_year < 2020 OR p_year > 2035 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Year must be between 2020 and 2035.';
    ELSE
        START TRANSACTION;
        SELECT section_id INTO v_section_id
        FROM section
        WHERE course_id = p_course_id
          AND semester  = p_semester
          AND year      = p_year
        LIMIT 1;

        IF v_section_id IS NULL THEN
            INSERT INTO section
                (course_id, semester, year, capacity, enrollment_start, enrollment_end)
            VALUES
                (p_course_id, p_semester, p_year,
                 IFNULL(p_capacity, 30),
                 p_enroll_start,
                 p_enroll_end);
            SET v_section_id = LAST_INSERT_ID();
        ELSEIF p_capacity IS NOT NULL AND p_capacity > 0 THEN
            UPDATE section SET capacity = p_capacity WHERE section_id = v_section_id;
        END IF;

        INSERT INTO teaching (teacher_id, section_id) VALUES (p_teacher_id, v_section_id);
        COMMIT;
        SET p_section_id = v_section_id;
        SET p_success    = 1;
        SET p_message    = 'Successfully assigned to teach the section.';
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_remove_teaching$$
CREATE PROCEDURE sp_remove_teaching(
    IN  p_teacher_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    OUT p_success    TINYINT,
    OUT p_message    VARCHAR(500)
)
BEGIN
    DECLARE v_teaches   INT DEFAULT 0;
    DECLARE v_has_exams INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches
    FROM teaching WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    SELECT COUNT(*) INTO v_has_exams
    FROM exam WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Assignment not found or not authorized.';
    ELSEIF v_has_exams > 0 THEN
        SET p_success = 0;
        SET p_message = CONCAT('Cannot remove: you have ', v_has_exams,
                               ' exam record(s) tied to this section. Delete those first.');
    ELSE
        DELETE FROM teaching WHERE teacher_id = p_teacher_id AND section_id = p_section_id;
        SET p_success = 1;
        SET p_message = 'Teaching assignment removed successfully.';
    END IF;
END$$

-- ============================================================
-- GRADE MANAGEMENT (from grades_procedures.sql)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_final_scores$$
CREATE PROCEDURE sp_get_final_scores(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.image,
        st.student_no,
        st.grade AS student_grade_year,
        d.dept_name,
        tk.grade AS letter_grade,
        tk.status,
        tk.enrolled_at,
        (SELECT score FROM exam
         WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final'
         ORDER BY exam_date DESC LIMIT 1) AS final_score,
        fn_student_section_avg(u.user_id, p_section_id) AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(u.user_id, p_section_id)) AS suggested_grade
    FROM takes tk
    JOIN student st ON tk.student_id = st.user_id
    JOIN user u ON st.user_id = u.user_id
    JOIN department d ON st.dept_id = d.dept_id
    WHERE tk.section_id = p_section_id AND tk.status = 'enrolled'
    ORDER BY u.name;
END$$

DROP PROCEDURE IF EXISTS sp_get_student_final_score$$
CREATE PROCEDURE sp_get_student_final_score(
    IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED
)
BEGIN
    SELECT
        e.exam_id,
        e.exam_date,
        e.score AS final_score,
        fn_score_to_grade(e.score) AS letter_grade,
        fn_student_section_avg(p_student_id, p_section_id) AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(p_student_id, p_section_id)) AS suggested_grade,
        (SELECT AVG(score) FROM exam WHERE student_id = p_student_id AND section_id = p_section_id AND exam_type = 'final') AS student_final_avg
    FROM exam e
    WHERE e.student_id = p_student_id
      AND e.section_id = p_section_id
      AND e.exam_type = 'final'
    ORDER BY e.exam_date DESC
    LIMIT 1;
END$$

DROP PROCEDURE IF EXISTS sp_get_course_avg_score$$
CREATE PROCEDURE sp_get_course_avg_score(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        p_section_id AS section_id,
        c.course_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        COUNT(DISTINCT tk.student_id) AS enrolled_count,
        COUNT(DISTINCT CASE WHEN e.exam_id IS NOT NULL THEN tk.student_id END) AS graded_count,
        ROUND(AVG(e.score), 2) AS avg_final_score,
        MIN(e.score) AS min_score,
        MAX(e.score) AS max_score,
        ROUND(STDDEV(e.score), 2) AS stddev_score,
        sec.capacity
    FROM section sec
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN takes tk ON sec.section_id = tk.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam e ON tk.student_id = e.student_id
                     AND sec.section_id = e.section_id
                     AND e.exam_type = 'final'
    WHERE sec.section_id = p_section_id
    GROUP BY sec.section_id, c.course_id, c.name, sec.semester, sec.year, sec.capacity;
END$$

DROP PROCEDURE IF EXISTS sp_get_course_avg_by_student$$
CREATE PROCEDURE sp_get_course_avg_by_student(IN p_student_id INT UNSIGNED)
BEGIN
    SELECT
        sec.section_id,
        c.course_id,
        c.name AS course_name,
        c.credit,
        sec.semester,
        sec.year,
        (SELECT score FROM exam
         WHERE student_id = p_student_id AND section_id = sec.section_id AND exam_type = 'final'
         ORDER BY exam_date DESC LIMIT 1) AS final_score,
        fn_student_section_avg(p_student_id, sec.section_id) AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(p_student_id, sec.section_id)) AS letter_grade,
        tk.grade AS recorded_grade,
        tk.status,
        tk.enrolled_at
    FROM takes tk
    JOIN section sec ON tk.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    WHERE tk.student_id = p_student_id AND tk.status = 'enrolled'
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), c.name;
END$$

-- ============================================================
-- EXAM EVENT PROCEDURES (from exam_event_procedures.sql)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_publish_exam$$
CREATE PROCEDURE sp_publish_exam(
    IN  p_teacher_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    IN  p_exam_type  VARCHAR(20),
    IN  p_exam_date  DATE,
    OUT p_inserted   INT,
    OUT p_ok         TINYINT,
    OUT p_msg        VARCHAR(200) CHARACTER SET utf8mb4
)
BEGIN
    DECLARE v_teaches INT DEFAULT 0;
    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_ok = 0; SET p_inserted = 0;
        SET p_msg = '无权限：您未教授该班级';
    ELSEIF p_exam_type NOT IN ('final', 'midterm', 'quiz') THEN
        SET p_ok = 0; SET p_inserted = 0;
        SET p_msg = '考试类型无效';
    ELSEIF p_exam_date IS NULL THEN
        SET p_ok = 0; SET p_inserted = 0;
        SET p_msg = '请填写考试日期';
    ELSE
        INSERT IGNORE INTO exam (teacher_id, student_id, section_id, exam_type, exam_date, score)
        SELECT p_teacher_id, tk.student_id, p_section_id, p_exam_type, p_exam_date, NULL
        FROM takes tk
        WHERE tk.section_id = p_section_id AND tk.status = 'enrolled';

        SET p_inserted = ROW_COUNT();
        SET p_ok  = 1;
        SET p_msg = CONCAT('考试已发布，共 ', p_inserted, ' 名学生');
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_get_exam_events$$
CREATE PROCEDURE sp_get_exam_events(
    IN p_teacher_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED
)
BEGIN
    SELECT
        e.exam_type,
        e.exam_date,
        (SELECT COUNT(*) FROM takes WHERE section_id = p_section_id AND status = 'enrolled') AS enrolled_count,
        COUNT(IF(e.score IS NOT NULL, 1, NULL))  AS scored_count
    FROM exam e
    WHERE e.teacher_id = p_teacher_id
      AND e.section_id = p_section_id
    GROUP BY e.exam_type, e.exam_date
    ORDER BY e.exam_date DESC, FIELD(e.exam_type, 'final', 'midterm', 'quiz');
END$$

DROP PROCEDURE IF EXISTS sp_get_pending_exams$$
CREATE PROCEDURE sp_get_pending_exams(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        e.section_id,
        c.name       AS course_name,
        sec.semester,
        sec.year,
        e.exam_type,
        e.exam_date,
        (SELECT COUNT(*) FROM takes t
         WHERE t.section_id = e.section_id AND t.status = 'enrolled') AS enrolled_count,
        COUNT(IF(e.score IS NOT NULL, 1, NULL))                        AS scored_count
    FROM exam e
    JOIN section sec ON sec.section_id = e.section_id
    JOIN course  c   ON c.course_id    = sec.course_id
    WHERE e.teacher_id = p_teacher_id
    GROUP BY e.section_id, c.name, sec.semester, sec.year, e.exam_type, e.exam_date
    HAVING SUM(IF(e.score IS NULL, 1, 0)) > 0
    ORDER BY e.exam_date DESC;
END$$

DROP PROCEDURE IF EXISTS sp_get_exam_entry_students$$
CREATE PROCEDURE sp_get_exam_entry_students(
    IN p_section_id  INT UNSIGNED,
    IN p_teacher_id  INT UNSIGNED,
    IN p_exam_type   VARCHAR(20),
    IN p_exam_date   DATE
)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.image,
        st.student_no,
        e.exam_id,
        e.score
    FROM takes   tk
    JOIN student st ON st.user_id   = tk.student_id
    JOIN user    u  ON u.user_id    = st.user_id
    LEFT JOIN exam e ON e.student_id = st.user_id
                    AND e.section_id = p_section_id
                    AND e.teacher_id = p_teacher_id
                    AND e.exam_type  = p_exam_type
                    AND e.exam_date  = p_exam_date
    WHERE tk.section_id = p_section_id AND tk.status = 'enrolled'
    ORDER BY u.name;
END$$

DROP PROCEDURE IF EXISTS sp_cancel_exam_event$$
CREATE PROCEDURE sp_cancel_exam_event(
    IN  p_section_id  INT UNSIGNED,
    IN  p_teacher_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_exam_date   DATE,
    OUT p_ok          TINYINT,
    OUT p_msg         VARCHAR(200) CHARACTER SET utf8mb4
)
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count FROM exam
    WHERE section_id = p_section_id AND teacher_id = p_teacher_id
      AND exam_type  = p_exam_type  AND exam_date   = p_exam_date;

    IF v_count = 0 THEN
        SET p_ok = 0; SET p_msg = '考试不存在或无权限';
    ELSE
        DELETE FROM exam
        WHERE section_id = p_section_id AND teacher_id = p_teacher_id
          AND exam_type  = p_exam_type  AND exam_date   = p_exam_date
          AND score IS NULL;
        SET p_ok = 1; SET p_msg = '考试已取消';
    END IF;
END$$

-- ============================================================
-- SCHEDULE & TIMETABLE (from schedule_procedures.sql)
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
-- WORKLOAD & MISC (from workload_procedures.sql)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_teacher_semesters$$
CREATE PROCEDURE sp_get_teacher_semesters(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT DISTINCT sec.year, sec.semester
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    WHERE tg.teacher_id = p_teacher_id
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring');
END$$

DROP PROCEDURE IF EXISTS sp_get_workload_summary$$
CREATE PROCEDURE sp_get_workload_summary(
    IN p_teacher_id INT UNSIGNED,
    IN p_semester   VARCHAR(20),
    IN p_year       INT
)
BEGIN
    SELECT
        COUNT(DISTINCT tg.section_id)   AS total_sections,
        COUNT(DISTINCT tk.student_id)   AS total_students,
        COUNT(DISTINCT e.exam_id)       AS total_exams,
        ROUND(AVG(e.score), 1)          AS overall_avg_score,
        SUM(CASE WHEN tk.grade IS NOT NULL AND tk.student_id IS NOT NULL THEN 1 ELSE 0 END) AS graded_students,
        SUM(CASE WHEN tk.grade IS NULL  AND tk.student_id IS NOT NULL THEN 1 ELSE 0 END) AS ungraded_students,
        (SELECT COALESCE(SUM(c2.credit), 0)
         FROM teaching tg2
         JOIN section s2 ON s2.section_id = tg2.section_id
         JOIN course  c2 ON c2.course_id  = s2.course_id
         WHERE tg2.teacher_id = p_teacher_id
           AND (p_semester = '' OR s2.semester = p_semester)
           AND (p_year = 0     OR s2.year     = p_year)
        ) AS total_credit_load,
        (SELECT COALESCE(SUM((TIME_TO_SEC(sch.end_time) - TIME_TO_SEC(sch.start_time)) / 60), 0)
         FROM teaching tg3
         JOIN section  s3  ON s3.section_id  = tg3.section_id
         JOIN schedule sch ON sch.section_id = tg3.section_id
         WHERE tg3.teacher_id = p_teacher_id
           AND (p_semester = '' OR s3.semester = p_semester)
           AND (p_year = 0     OR s3.year     = p_year)
        ) AS total_weekly_minutes
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    LEFT JOIN takes tk ON tk.section_id = sec.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON e.section_id  = sec.section_id AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id
      AND (p_semester = '' OR sec.semester = p_semester)
      AND (p_year = 0     OR sec.year     = p_year);
END$$

DROP PROCEDURE IF EXISTS sp_get_workload_by_section$$
CREATE PROCEDURE sp_get_workload_by_section(
    IN p_teacher_id INT UNSIGNED,
    IN p_semester   VARCHAR(20),
    IN p_year       INT
)
BEGIN
    SELECT
        sec.section_id,
        c.name          AS course_name,
        c.credit,
        c.hours         AS course_hours,
        sec.semester,
        sec.year,
        COUNT(DISTINCT tk.student_id)   AS student_count,
        COUNT(DISTINCT e.exam_id)       AS exam_count,
        ROUND(AVG(e.score), 1)          AS avg_score,
        SUM(CASE WHEN tk.grade IS NOT NULL THEN 1 ELSE 0 END) AS graded_count,
        ROUND(SUM(CASE WHEN tk.grade IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / NULLIF(COUNT(DISTINCT tk.student_id), 0), 1) AS grade_completion_pct,
        (SELECT COUNT(*) FROM schedule sch WHERE sch.section_id = sec.section_id) AS schedule_slots,
        (SELECT COALESCE(SUM((TIME_TO_SEC(sch2.end_time) - TIME_TO_SEC(sch2.start_time)) / 60), 0) FROM schedule sch2 WHERE sch2.section_id = sec.section_id) AS weekly_minutes
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    JOIN course  c   ON c.course_id    = sec.course_id
    LEFT JOIN takes tk ON tk.section_id = sec.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON e.section_id  = sec.section_id AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id
      AND (p_semester = '' OR sec.semester = p_semester)
      AND (p_year = 0     OR sec.year     = p_year)
    GROUP BY sec.section_id, c.name, c.credit, c.hours, sec.semester, sec.year
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), c.name;
END$$

-- ============================================================
-- BATCH GRADES IMPORT (from batch_grades_procedures.sql)
-- ============================================================

DROP PROCEDURE IF EXISTS sp_batch_import_exam$$
CREATE PROCEDURE sp_batch_import_exam(
    IN  p_teacher_id  INT UNSIGNED,
    IN  p_section_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_exam_date   DATE,
    IN  p_records     JSON,
    OUT p_saved       INT,
    OUT p_skipped     INT,
    OUT p_success     TINYINT,
    OUT p_message     VARCHAR(500)
)
BEGIN
    DECLARE v_teaches INT DEFAULT 0;
    DECLARE v_total   INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0; SET p_saved = 0; SET p_skipped = 0;
        SET p_message = 'Not authorized for this section.';
    ELSEIF p_exam_type NOT IN ('final', 'midterm', 'quiz') THEN
        SET p_success = 0; SET p_saved = 0; SET p_skipped = 0;
        SET p_message = 'Invalid exam_type. Use: final, midterm, quiz.';
    ELSEIF p_records IS NULL OR JSON_LENGTH(p_records) = 0 THEN
        SET p_success = 0; SET p_saved = 0; SET p_skipped = 0;
        SET p_message = 'Empty records array.';
    ELSE
        SET v_total = JSON_LENGTH(p_records);
        INSERT INTO exam (teacher_id, student_id, section_id, exam_type, exam_date, score)
        SELECT
            p_teacher_id,
            st.user_id,
            p_section_id,
            p_exam_type,
            p_exam_date,
            jt.score
        FROM JSON_TABLE(
            p_records,
            '$[*]' COLUMNS (
                student_id  INT          PATH '$.student_id',
                student_no  VARCHAR(50)  PATH '$.student_no',
                score       DECIMAL(5,2) PATH '$.score'
            )
        ) AS jt
        JOIN student st ON (
            (jt.student_no IS NOT NULL AND jt.student_no <> '' AND st.student_no = jt.student_no)
            OR ((jt.student_no IS NULL OR jt.student_no = '')
                AND jt.student_id IS NOT NULL AND jt.student_id > 0
                AND st.user_id = jt.student_id)
        )
        WHERE jt.score BETWEEN 0 AND 100
          AND EXISTS (
              SELECT 1 FROM takes tk
              WHERE tk.student_id = st.user_id
                AND tk.section_id = p_section_id
                AND tk.status     = 'enrolled'
          )
        ON DUPLICATE KEY UPDATE
            score     = VALUES(score),
            exam_date = VALUES(exam_date);

        SELECT COUNT(*) INTO p_saved
        FROM JSON_TABLE(
            p_records,
            '$[*]' COLUMNS (
                student_id  INT          PATH '$.student_id',
                student_no  VARCHAR(50)  PATH '$.student_no',
                score       DECIMAL(5,2) PATH '$.score'
            )
        ) AS jt2
        JOIN student st2 ON (
            (jt2.student_no IS NOT NULL AND jt2.student_no <> '' AND st2.student_no = jt2.student_no)
            OR ((jt2.student_no IS NULL OR jt2.student_no = '')
                AND jt2.student_id IS NOT NULL AND jt2.student_id > 0
                AND st2.user_id = jt2.student_id)
        )
        WHERE jt2.score BETWEEN 0 AND 100
          AND EXISTS (
              SELECT 1 FROM takes tk2
              WHERE tk2.student_id = st2.user_id
                AND tk2.section_id = p_section_id
                AND tk2.status     = 'enrolled'
          );
        SET p_skipped = GREATEST(v_total - p_saved, 0);
        SET p_success = 1;
        SET p_message = CONCAT(p_saved, ' record(s) saved, ', p_skipped, ' skipped (not enrolled or invalid score).');
    END IF;
END$$

-- Finalize delimiter
DELIMITER ;

-- EOF: teacher/procedure/all.sql (merged)
