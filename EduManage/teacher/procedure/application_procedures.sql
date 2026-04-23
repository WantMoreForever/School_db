/*
  Teacher application helper SQL
  Keep only the objects still used by the current teacher module.
*/

USE school_db;
DELIMITER $$

-- Remove legacy objects from the older enrollment-approval design.
DROP FUNCTION IF EXISTS fn_section_fill_rate$$
DROP FUNCTION IF EXISTS fn_teacher_teaches_section$$
DROP VIEW IF EXISTS v_teaching_overview$$
DROP PROCEDURE IF EXISTS sp_get_teaching_overview$$
DROP PROCEDURE IF EXISTS sp_get_pending_enrollments$$
DROP PROCEDURE IF EXISTS sp_approve_enrollment$$
DROP PROCEDURE IF EXISTS sp_reject_enrollment$$
DROP PROCEDURE IF EXISTS sp_get_enrollment_stats$$

DROP TRIGGER IF EXISTS trg_no_duplicate_teaching$$
CREATE TRIGGER trg_no_duplicate_teaching
BEFORE INSERT ON teaching
FOR EACH ROW
BEGIN
    DECLARE v_cnt INT DEFAULT 0;

    SELECT COUNT(*) INTO v_cnt
    FROM teaching
    WHERE teacher_id = NEW.teacher_id
      AND section_id = NEW.section_id;

    IF v_cnt > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher is already assigned to this section';
    END IF;
END$$

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
            SELECT 1
            FROM teaching tg
            JOIN section s ON s.section_id = tg.section_id
            WHERE tg.teacher_id = p_teacher_id
              AND s.course_id = c.course_id
              AND s.year = v_year
        ) AS already_teaching_now,
        (
            SELECT s2.section_id
            FROM section s2
            WHERE s2.course_id = c.course_id
              AND s2.year = v_year
            ORDER BY FIELD(s2.semester, 'Fall', 'Spring')
            LIMIT 1
        ) AS existing_section_id,
        (
            SELECT s3.semester
            FROM section s3
            WHERE s3.course_id = c.course_id
              AND s3.year = v_year
            ORDER BY FIELD(s3.semester, 'Fall', 'Spring')
            LIMIT 1
        ) AS existing_semester,
        (
            SELECT COUNT(*)
            FROM section s4
            WHERE s4.course_id = c.course_id
        ) AS total_sections
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
    DECLARE v_section_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher_ok INT DEFAULT 0;
    DECLARE v_course_ok INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLSTATE '45000'
    BEGIN
        ROLLBACK;
        SET p_success = 0;
        SET p_section_id = NULL;
        SET p_message = 'You are already assigned to teach this section.';
    END;

    SELECT COUNT(*) INTO v_teacher_ok
    FROM teacher
    WHERE user_id = p_teacher_id;

    SELECT COUNT(*) INTO v_course_ok
    FROM course
    WHERE course_id = p_course_id;

    IF v_teacher_ok = 0 THEN
        SET p_success = 0;
        SET p_section_id = NULL;
        SET p_message = 'Teacher not found.';
    ELSEIF v_course_ok = 0 THEN
        SET p_success = 0;
        SET p_section_id = NULL;
        SET p_message = 'Course not found.';
    ELSEIF p_semester NOT IN ('Spring', 'Fall') THEN
        SET p_success = 0;
        SET p_section_id = NULL;
        SET p_message = 'Semester must be Spring or Fall.';
    ELSEIF p_year < 2020 OR p_year > 2035 THEN
        SET p_success = 0;
        SET p_section_id = NULL;
        SET p_message = 'Year must be between 2020 and 2035.';
    ELSE
        START TRANSACTION;

        SELECT section_id INTO v_section_id
        FROM section
        WHERE course_id = p_course_id
          AND semester = p_semester
          AND year = p_year
        LIMIT 1;

        IF v_section_id IS NULL THEN
            INSERT INTO section
                (course_id, semester, year, capacity, enrollment_start, enrollment_end)
            VALUES
                (p_course_id, p_semester, p_year, IFNULL(p_capacity, 30), p_enroll_start, p_enroll_end);
            SET v_section_id = LAST_INSERT_ID();
        ELSEIF p_capacity IS NOT NULL AND p_capacity > 0 THEN
            UPDATE section
            SET capacity = p_capacity
            WHERE section_id = v_section_id;
        END IF;

        INSERT INTO teaching (teacher_id, section_id)
        VALUES (p_teacher_id, v_section_id);

        COMMIT;
        SET p_section_id = v_section_id;
        SET p_success = 1;
        SET p_message = 'Successfully assigned to teach the section.';
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
    DECLARE v_teaches INT DEFAULT 0;
    DECLARE v_has_exams INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches
    FROM teaching
    WHERE teacher_id = p_teacher_id
      AND section_id = p_section_id;

    SELECT COUNT(*) INTO v_has_exams
    FROM exam
    WHERE teacher_id = p_teacher_id
      AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Assignment not found or not authorized.';
    ELSEIF v_has_exams > 0 THEN
        SET p_success = 0;
        SET p_message = CONCAT(
            'Cannot remove: you have ',
            v_has_exams,
            ' exam record(s) tied to this section. Delete those first.'
        );
    ELSE
        DELETE FROM teaching
        WHERE teacher_id = p_teacher_id
          AND section_id = p_section_id;

        SET p_success = 1;
        SET p_message = 'Teaching assignment removed successfully.';
    END IF;
END$$

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

    SELECT COUNT(*) INTO v_teaches
    FROM teaching
    WHERE teacher_id = p_teacher_id
      AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Not authorized: you do not teach this section.';
    ELSEIF p_capacity IS NOT NULL AND p_capacity < 1 THEN
        SET p_success = 0;
        SET p_message = 'Capacity must be at least 1.';
    ELSE
        UPDATE section
        SET
            capacity = IFNULL(p_capacity, capacity),
            enrollment_start = IFNULL(p_enroll_start, enrollment_start),
            enrollment_end = IFNULL(p_enroll_end, enrollment_end)
        WHERE section_id = p_section_id;

        SET p_success = 1;
        SET p_message = 'Section info updated successfully.';
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_get_advisor_students$$
CREATE PROCEDURE sp_get_advisor_students(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        st.user_id,
        u.name AS student_name,
        u.email,
        u.phone,
        st.student_no,
        st.grade AS student_grade,
        st.enrollment_year,
        d.dept_name,
        fn_get_student_course_gpa(st.user_id) AS gpa,
        COUNT(DISTINCT tk.section_id) AS enrolled_sections,
        COUNT(DISTINCT e.exam_id) AS total_exams,
        ROUND(AVG(e.score), 2) AS overall_avg,
        RANK() OVER (
            ORDER BY fn_get_student_course_gpa(st.user_id) DESC
        ) AS gpa_rank
    FROM advisor a
    JOIN student st ON st.user_id = a.student_id
    JOIN user u ON u.user_id = st.user_id
    JOIN department d ON d.dept_id = st.dept_id
    LEFT JOIN takes tk ON tk.student_id = st.user_id
                      AND tk.status = 'enrolled'
    LEFT JOIN exam e ON e.student_id = st.user_id
    WHERE a.teacher_id = p_teacher_id
    GROUP BY
        st.user_id,
        u.name,
        u.email,
        u.phone,
        st.student_no,
        st.grade,
        st.enrollment_year,
        d.dept_name
    ORDER BY gpa DESC, student_name;
END$$

DELIMITER ;
