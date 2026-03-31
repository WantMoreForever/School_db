/*
  application_procedures.sql
  ─────────────────────────────────────────────────────────────────
  Teacher Portal — Course Application, Enrollment Management,
                   Advisor View & Teaching Overview
  ─────────────────────────────────────────────────────────────────
  ★ No new tables are created.
    All operations use ONLY the existing tables:
      course, section, teaching, takes, student, user,
      department, exam, advisor
  ─────────────────────────────────────────────────────────────────
  SQL features demonstrated:
    SELECT     – multi-table JOINs, EXISTS, subqueries, CTE, window
    INSERT     – section creation + teaching assignment (transaction)
    UPDATE     – section info update, enrollment status change
    DELETE     – remove teaching assignment
    Trigger    – BEFORE INSERT on teaching (duplicate guard)
    Function   – fn_teaching_conflicts, fn_section_fill_rate
    View       – v_teaching_overview (JOIN across 4 tables)
    Window fn  – RANK, ROW_NUMBER, COUNT/SUM OVER
    CTE        – WITH clause in overview & enrollment stats
    CASE/WHEN  – conditional aggregates
    FIELD()    – custom ENUM ordering
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;

-- ═══════════════════════════════════════════════════
-- FUNCTION: fn_section_fill_rate
-- Returns enrollment percentage for a section (0–100)
-- ═══════════════════════════════════════════════════
DELIMITER $$

DROP FUNCTION IF EXISTS fn_section_fill_rate$$
CREATE FUNCTION fn_section_fill_rate(p_section_id INT UNSIGNED)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_enrolled  INT DEFAULT 0;
    DECLARE v_capacity  INT DEFAULT 0;

    SELECT COUNT(*) INTO v_enrolled
    FROM takes
    WHERE section_id = p_section_id AND status = 'enrolled';

    SELECT capacity INTO v_capacity
    FROM section WHERE section_id = p_section_id;

    IF v_capacity = 0 THEN RETURN 0; END IF;
    RETURN ROUND(v_enrolled * 100.0 / v_capacity, 2);
END$$

-- ═══════════════════════════════════════════════════
-- FUNCTION: fn_teacher_teaches_section
-- Returns 1 if teacher teaches the section, else 0
-- ═══════════════════════════════════════════════════
DROP FUNCTION IF EXISTS fn_teacher_teaches_section$$
CREATE FUNCTION fn_teacher_teaches_section(
    p_teacher_id INT UNSIGNED,
    p_section_id INT UNSIGNED
)
RETURNS TINYINT
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_cnt INT DEFAULT 0;
    SELECT COUNT(*) INTO v_cnt FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;
    RETURN IF(v_cnt > 0, 1, 0);
END$$

DELIMITER ;

-- ═══════════════════════════════════════════════════
-- TRIGGER: trg_no_duplicate_teaching
-- Prevents inserting a duplicate (teacher_id, section_id)
-- into the teaching table (belt-and-suspenders guard).
-- ═══════════════════════════════════════════════════
DELIMITER $$

DROP TRIGGER IF EXISTS trg_no_duplicate_teaching$$
CREATE TRIGGER trg_no_duplicate_teaching
BEFORE INSERT ON teaching
FOR EACH ROW
BEGIN
    DECLARE v_cnt INT DEFAULT 0;
    SELECT COUNT(*) INTO v_cnt FROM teaching
    WHERE teacher_id = NEW.teacher_id AND section_id = NEW.section_id;
    IF v_cnt > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher is already assigned to this section';
    END IF;
END$$

DELIMITER ;

-- ═══════════════════════════════════════════════════
-- VIEW: v_teaching_overview
-- Denormalised view of teacher→section→course with stats.
-- Useful for admin panels; teachers query it filtered by
-- teacher_id in the stored procedures below.
-- ═══════════════════════════════════════════════════
CREATE OR REPLACE VIEW v_teaching_overview AS
SELECT
    tg.teacher_id,
    u.name                                        AS teacher_name,
    t.title                                       AS teacher_title,
    d.dept_name,
    sec.section_id,
    sec.semester,
    sec.year,
    sec.capacity,
    sec.enrollment_start,
    sec.enrollment_end,
    c.course_id,
    c.name                                        AS course_name,
    c.credit,
    c.hours,
    COUNT(DISTINCT tk.student_id)                 AS enrolled_count,
    COUNT(DISTINCT CASE WHEN tk.status = 'pending'
                        THEN tk.student_id END)   AS pending_count,
    ROUND(AVG(e.score), 2)                        AS avg_score,
    fn_section_fill_rate(sec.section_id)          AS fill_rate
FROM teaching tg
JOIN user       u   ON u.user_id    = tg.teacher_id
JOIN teacher    t   ON t.user_id    = tg.teacher_id
JOIN department d   ON d.dept_id    = t.dept_id
JOIN section    sec ON sec.section_id = tg.section_id
JOIN course     c   ON c.course_id  = sec.course_id
LEFT JOIN takes tk  ON tk.section_id = sec.section_id
LEFT JOIN exam  e   ON e.section_id  = sec.section_id
                   AND e.teacher_id  = tg.teacher_id
GROUP BY
    tg.teacher_id, u.name, t.title, d.dept_name,
    sec.section_id, sec.semester, sec.year, sec.capacity,
    sec.enrollment_start, sec.enrollment_end,
    c.course_id, c.name, c.credit, c.hours;

-- ═══════════════════════════════════════════════════════════════
-- STORED PROCEDURES
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ───────────────────────────────────────────────────────────────
-- sp_get_courses_to_apply
-- SELECT: all courses with flags for the current teacher.
--   already_teaching_now  – 1 if assigned to any section this year
--   existing_section_id   – section_id this year (or NULL)
--   total_sections        – total sections ever created for the course
-- Uses: EXISTS subquery + scalar correlated subquery
-- ───────────────────────────────────────────────────────────────
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
        -- Is teacher already assigned to this course this calendar year?
        EXISTS (
            SELECT 1 FROM teaching tg
            JOIN section s ON s.section_id = tg.section_id
            WHERE tg.teacher_id = p_teacher_id
              AND s.course_id   = c.course_id
              AND s.year        = v_year
        ) AS already_teaching_now,
        -- The section_id for this year (if it exists), else NULL
        (
            SELECT s2.section_id FROM section s2
            WHERE s2.course_id = c.course_id AND s2.year = v_year
            ORDER BY FIELD(s2.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_section_id,
        -- Semester of that section
        (
            SELECT s3.semester FROM section s3
            WHERE s3.course_id = c.course_id AND s3.year = v_year
            ORDER BY FIELD(s3.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_semester,
        -- Total sections ever for this course
        (SELECT COUNT(*) FROM section s4 WHERE s4.course_id = c.course_id)
            AS total_sections
    FROM course c
    ORDER BY c.name;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_apply_to_teach
-- INSERT path: creates a section (if none exists for the given
-- course+semester+year) then inserts into teaching.
-- Wrapped in a transaction; the duplicate-teaching trigger acts
-- as a second guard.
--
-- SQL: INSERT into section (if needed), INSERT into teaching,
--      START TRANSACTION / COMMIT / ROLLBACK on error,
--      DECLARE EXIT HANDLER for SQLSTATE '45000' (trigger signal)
-- ───────────────────────────────────────────────────────────────
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

    -- EXIT HANDLER for the duplicate-teaching trigger signal
    DECLARE EXIT HANDLER FOR SQLSTATE '45000'
    BEGIN
        ROLLBACK;
        SET p_success    = 0;
        SET p_section_id = NULL;
        SET p_message    = 'You are already assigned to teach this section.';
    END;

    -- Validate teacher
    SELECT COUNT(*) INTO v_teacher_ok FROM teacher WHERE user_id = p_teacher_id;
    -- Validate course
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

        -- Find or create the section
        SELECT section_id INTO v_section_id
        FROM section
        WHERE course_id = p_course_id
          AND semester  = p_semester
          AND year      = p_year
        LIMIT 1;

        IF v_section_id IS NULL THEN
            -- CREATE new section (INSERT)
            INSERT INTO section
                (course_id, semester, year, capacity, enrollment_start, enrollment_end)
            VALUES
                (p_course_id, p_semester, p_year,
                 IFNULL(p_capacity, 30),
                 p_enroll_start,
                 p_enroll_end);
            SET v_section_id = LAST_INSERT_ID();
        ELSEIF p_capacity IS NOT NULL AND p_capacity > 0 THEN
            -- UPDATE existing section capacity if caller supplied one
            UPDATE section SET capacity = p_capacity WHERE section_id = v_section_id;
        END IF;

        -- Assign teacher to section (INSERT; trigger fires here)
        INSERT INTO teaching (teacher_id, section_id) VALUES (p_teacher_id, v_section_id);

        COMMIT;
        SET p_section_id = v_section_id;
        SET p_success    = 1;
        SET p_message    = 'Successfully assigned to teach the section.';
    END IF;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_remove_teaching
-- DELETE: removes teacher from a section.
-- Safety check: only allowed if teacher has no exam records for
-- that section (prevents data orphaning).
-- ───────────────────────────────────────────────────────────────
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

-- ───────────────────────────────────────────────────────────────
-- sp_update_section_info
-- UPDATE: teacher can adjust capacity and enrollment window of
-- sections they teach.
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_update_section_info$$
CREATE PROCEDURE sp_update_section_info(
    IN  p_teacher_id    INT UNSIGNED,
    IN  p_section_id    INT UNSIGNED,
    IN  p_capacity      SMALLINT UNSIGNED,
    IN  p_enroll_start  DATETIME,
    IN  p_enroll_end    DATETIME,
    OUT p_success       TINYINT,
    OUT p_message       VARCHAR(500)
)
BEGIN
    DECLARE v_teaches INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Not authorized: you do not teach this section.';
    ELSEIF p_capacity IS NOT NULL AND p_capacity < 1 THEN
        SET p_success = 0;
        SET p_message = 'Capacity must be at least 1.';
    ELSE
        UPDATE section
        SET
            capacity         = IFNULL(p_capacity, capacity),
            enrollment_start = IFNULL(p_enroll_start, enrollment_start),
            enrollment_end   = IFNULL(p_enroll_end,   enrollment_end)
        WHERE section_id = p_section_id;

        SET p_success = 1;
        SET p_message = 'Section info updated successfully.';
    END IF;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_get_teaching_overview
-- SELECT with CTE + window functions.
-- Returns teaching assignments with:
--   enrollment_rank  – RANK() by enrolled_count DESC
--   total_students   – SUM(enrolled) OVER () all sections
--   overall_avg      – AVG(avg_score) OVER () all sections
--   fill_rate        – percentage of capacity filled
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_teaching_overview$$
CREATE PROCEDURE sp_get_teaching_overview(IN p_teacher_id INT UNSIGNED)
BEGIN
    WITH base AS (
        SELECT
            sec.section_id,
            sec.semester,
            sec.year,
            sec.capacity,
            sec.enrollment_start,
            sec.enrollment_end,
            c.course_id,
            c.name                                      AS course_name,
            c.credit,
            c.hours,
            COUNT(DISTINCT CASE WHEN tk.status = 'enrolled'
                                THEN tk.student_id END) AS enrolled_count,
            COUNT(DISTINCT CASE WHEN tk.status = 'pending'
                                THEN tk.student_id END) AS pending_count,
            COUNT(DISTINCT CASE WHEN tk.status = 'dropped'
                                THEN tk.student_id END) AS dropped_count,
            ROUND(AVG(e.score), 2)                      AS avg_score,
            COUNT(DISTINCT e.exam_id)                   AS exam_count
        FROM teaching tg
        JOIN section sec ON sec.section_id = tg.section_id
        JOIN course  c   ON c.course_id    = sec.course_id
        LEFT JOIN takes tk ON tk.section_id = sec.section_id
        LEFT JOIN exam  e  ON e.section_id  = sec.section_id
                           AND e.teacher_id = p_teacher_id
        WHERE tg.teacher_id = p_teacher_id
        GROUP BY sec.section_id, sec.semester, sec.year, sec.capacity,
                 sec.enrollment_start, sec.enrollment_end,
                 c.course_id, c.name, c.credit, c.hours
    )
    SELECT
        section_id, semester, year, capacity,
        enrollment_start, enrollment_end,
        course_id, course_name, credit, hours,
        enrolled_count, pending_count, dropped_count,
        avg_score, exam_count,
        -- Window functions
        RANK()       OVER (ORDER BY enrolled_count DESC)         AS enrollment_rank,
        ROW_NUMBER() OVER (ORDER BY year DESC,
                           FIELD(semester,'Fall','Spring'))       AS recency_seq,
        SUM(enrolled_count) OVER ()                              AS total_students_all,
        ROUND(AVG(avg_score) OVER (), 2)                         AS overall_avg_score,
        ROUND(enrolled_count * 100.0 / NULLIF(capacity, 0), 1)  AS fill_pct
    FROM base
    ORDER BY year DESC, FIELD(semester,'Fall','Spring'), course_name;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_get_pending_enrollments
-- SELECT: students with takes.status = 'pending' across all
-- sections the teacher teaches.
-- Uses: JOIN chain + CASE WHEN + ORDER BY
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_pending_enrollments$$
CREATE PROCEDURE sp_get_pending_enrollments(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        tk.student_id,
        u.name                  AS student_name,
        st.student_no,
        d.dept_name,
        st.grade                AS student_grade,
        tk.section_id,
        c.name                  AS course_name,
        sec.semester,
        sec.year,
        tk.enrolled_at,
        -- How long (days) the request has been pending
        DATEDIFF(NOW(), tk.enrolled_at) AS days_waiting,
        -- Rank within section by wait time (longest first)
        ROW_NUMBER() OVER (
            PARTITION BY tk.section_id
            ORDER BY tk.enrolled_at ASC
        ) AS queue_position
    FROM takes tk
    JOIN teaching tg  ON tg.section_id  = tk.section_id
                     AND tg.teacher_id  = p_teacher_id
    JOIN student  st  ON st.user_id     = tk.student_id
    JOIN user     u   ON u.user_id      = tk.student_id
    JOIN department d ON d.dept_id      = st.dept_id
    JOIN section  sec ON sec.section_id = tk.section_id
    JOIN course   c   ON c.course_id    = sec.course_id
    WHERE tk.status = 'pending'
    ORDER BY days_waiting DESC, tk.enrolled_at ASC;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_approve_enrollment
-- UPDATE takes.status 'pending' → 'enrolled'
-- Checks: teacher teaches section, student is pending,
--         section not over capacity.
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_approve_enrollment$$
CREATE PROCEDURE sp_approve_enrollment(
    IN  p_teacher_id INT UNSIGNED,
    IN  p_student_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    OUT p_success    TINYINT,
    OUT p_message    VARCHAR(500)
)
BEGIN
    DECLARE v_teaches    INT DEFAULT 0;
    DECLARE v_pending    INT DEFAULT 0;
    DECLARE v_enrolled   INT DEFAULT 0;
    DECLARE v_capacity   INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    SELECT COUNT(*) INTO v_pending FROM takes
    WHERE student_id = p_student_id AND section_id = p_section_id AND status = 'pending';

    SELECT COUNT(*) INTO v_enrolled FROM takes
    WHERE section_id = p_section_id AND status = 'enrolled';

    SELECT capacity INTO v_capacity FROM section WHERE section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0; SET p_message = 'Not authorized.';
    ELSEIF v_pending = 0 THEN
        SET p_success = 0; SET p_message = 'Student has no pending enrollment for this section.';
    ELSEIF v_capacity > 0 AND v_enrolled >= v_capacity THEN
        SET p_success = 0;
        SET p_message = CONCAT('Section is full (', v_enrolled, '/', v_capacity, '). Cannot approve.');
    ELSE
        UPDATE takes SET status = 'enrolled'
        WHERE student_id = p_student_id AND section_id = p_section_id AND status = 'pending';
        SET p_success = 1;
        SET p_message = 'Enrollment approved.';
    END IF;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_reject_enrollment
-- UPDATE takes.status 'pending' → 'dropped'
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_reject_enrollment$$
CREATE PROCEDURE sp_reject_enrollment(
    IN  p_teacher_id INT UNSIGNED,
    IN  p_student_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    OUT p_success    TINYINT,
    OUT p_message    VARCHAR(500)
)
BEGIN
    DECLARE v_teaches INT DEFAULT 0;
    DECLARE v_pending INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    SELECT COUNT(*) INTO v_pending FROM takes
    WHERE student_id = p_student_id AND section_id = p_section_id AND status = 'pending';

    IF v_teaches = 0 THEN
        SET p_success = 0; SET p_message = 'Not authorized.';
    ELSEIF v_pending = 0 THEN
        SET p_success = 0; SET p_message = 'No pending enrollment found.';
    ELSE
        UPDATE takes SET status = 'dropped'
        WHERE student_id = p_student_id AND section_id = p_section_id AND status = 'pending';
        SET p_success = 1;
        SET p_message = 'Enrollment rejected.';
    END IF;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_get_enrollment_stats
-- SELECT with conditional aggregates (CASE WHEN) per section.
-- Returns enrolled / pending / dropped counts + fill_pct.
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_enrollment_stats$$
CREATE PROCEDURE sp_get_enrollment_stats(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        sec.section_id,
        c.name                                          AS course_name,
        sec.semester,
        sec.year,
        sec.capacity,
        SUM(CASE WHEN tk.status = 'enrolled' THEN 1 ELSE 0 END) AS enrolled_count,
        SUM(CASE WHEN tk.status = 'pending'  THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN tk.status = 'dropped'  THEN 1 ELSE 0 END) AS dropped_count,
        ROUND(
            SUM(CASE WHEN tk.status = 'enrolled' THEN 1 ELSE 0 END)
            * 100.0 / NULLIF(sec.capacity, 0), 1
        )                                               AS fill_pct,
        sec.enrollment_start,
        sec.enrollment_end
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    JOIN course  c   ON c.course_id    = sec.course_id
    LEFT JOIN takes tk ON tk.section_id = sec.section_id
    WHERE tg.teacher_id = p_teacher_id
    GROUP BY sec.section_id, c.name, sec.semester, sec.year,
             sec.capacity, sec.enrollment_start, sec.enrollment_end
    ORDER BY sec.year DESC, FIELD(sec.semester,'Fall','Spring'), c.name;
END$$

-- ───────────────────────────────────────────────────────────────
-- sp_get_advisor_students
-- SELECT: all students advised by this teacher (advisor table).
-- Uses: fn_get_student_course_gpa for GPA column,
--       LEFT JOIN to count sections enrolled,
--       window RANK() by GPA DESC.
-- ───────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_advisor_students$$
CREATE PROCEDURE sp_get_advisor_students(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        st.user_id,
        u.name                                              AS student_name,
        u.email,
        u.phone,
        st.student_no,
        st.grade                                            AS student_grade,
        st.enrollment_year,
        d.dept_name,
        fn_get_student_course_gpa(st.user_id)               AS gpa,
        COUNT(DISTINCT tk.section_id)                       AS enrolled_sections,
        COUNT(DISTINCT e.exam_id)                           AS total_exams,
        ROUND(AVG(e.score), 2)                              AS overall_avg,
        -- GPA rank among this teacher's advisees
        RANK() OVER (
            ORDER BY fn_get_student_course_gpa(st.user_id) DESC
        ) AS gpa_rank
    FROM advisor a
    JOIN student    st ON st.user_id   = a.student_id
    JOIN user       u  ON u.user_id    = st.user_id
    JOIN department d  ON d.dept_id    = st.dept_id
    LEFT JOIN takes tk ON tk.student_id = st.user_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON e.student_id  = st.user_id
    WHERE a.teacher_id = p_teacher_id
    GROUP BY st.user_id, u.name, u.email, u.phone,
             st.student_no, st.grade, st.enrollment_year, d.dept_name
    ORDER BY gpa DESC , student_name;
END$$

DELIMITER ;
