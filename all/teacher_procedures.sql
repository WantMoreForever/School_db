/*
 Teacher Portal — Stored Procedures & Functions
 New SQL file — does NOT modify school_db existing tables or data
 Target Schema : school_db
 Date          : 2026-03-27
*/

USE school_db;
DELIMITER $$

-- ============================================================
-- FUNCTION: fn_score_to_grade
-- Convert numeric score (0-100) to letter grade
-- ============================================================
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

-- ============================================================
-- FUNCTION: fn_student_section_avg
-- Weighted average score for a student in a section
-- Weights: final=50%, midterm=30%, quiz=20%
-- ============================================================
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

    -- Weighted average only using available types
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

-- ============================================================
-- FUNCTION: fn_teacher_section_count
-- Total active sections a teacher is assigned to
-- ============================================================
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
-- PROCEDURE: sp_get_teacher_info
-- Full teacher profile with department
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

-- ============================================================
-- PROCEDURE: sp_get_teacher_sections
-- All sections a teacher teaches with stats
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_teacher_sections$$
CREATE PROCEDURE sp_get_teacher_sections(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        s.section_id,
        s.semester,
        s.year,
        c.course_id,
        c.name        AS course_name,
        c.credit,
        c.hours,
        s.capacity,
        c.description,
        COUNT(DISTINCT tk.student_id)                   AS enrolled_count,
        COUNT(DISTINCT e.exam_id)                       AS exam_count,
        ROUND(AVG(e.score), 2)                          AS section_avg_score,
        SUM(CASE WHEN tk.grade IS NULL AND tk.student_id IS NOT NULL THEN 1 ELSE 0 END) AS ungraded_count
    FROM teaching tg
    JOIN section s  ON tg.section_id = s.section_id
    JOIN course  c  ON s.course_id   = c.course_id
    LEFT JOIN takes tk ON s.section_id = tk.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON s.section_id = e.section_id AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id
    GROUP BY s.section_id, s.semester, s.year,
             c.course_id, c.name, c.credit, c.hours, s.capacity, c.description
    ORDER BY s.year DESC, FIELD(s.semester,'Fall','Spring');
END$$

-- ============================================================
-- PROCEDURE: sp_get_section_students
-- Students in a section with scores and grade status
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_section_students$$
CREATE PROCEDURE sp_get_section_students(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.image,
        st.student_no,
        st.grade      AS student_grade_year,
        st.enrollment_year,
        d.dept_name,
        tk.grade      AS letter_grade,
        tk.status,
        tk.enrolled_at,
        fn_student_section_avg(u.user_id, p_section_id)           AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(u.user_id, p_section_id)) AS suggested_grade,
        (SELECT score FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final'   ORDER BY exam_date DESC LIMIT 1) AS final_score,
        (SELECT score FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm' ORDER BY exam_date DESC LIMIT 1) AS midterm_score,
        (SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz') AS quiz_avg
    FROM takes tk
    JOIN student    st ON tk.student_id = st.user_id
    JOIN user       u  ON st.user_id    = u.user_id
    JOIN department d  ON st.dept_id    = d.dept_id
    WHERE tk.section_id = p_section_id
    ORDER BY u.name;
END$$

-- ============================================================
-- PROCEDURE: sp_get_section_exams
-- All exam records for a section
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_section_exams$$
CREATE PROCEDURE sp_get_section_exams(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        e.exam_id,
        e.exam_type,
        e.exam_date,
        e.score,
        fn_score_to_grade(e.score)  AS computed_grade,
        u.user_id                   AS student_id,
        u.name                      AS student_name,
        u.image                     AS student_image,
        st.student_no,
        tu.name                     AS teacher_name
    FROM exam e
    JOIN user    u  ON e.student_id  = u.user_id
    JOIN student st ON e.student_id  = st.user_id
    JOIN user    tu ON e.teacher_id  = tu.user_id
    WHERE e.section_id = p_section_id
    ORDER BY e.exam_date DESC, e.exam_type, u.name;
END$$

-- ============================================================
-- PROCEDURE: sp_save_exam
-- INSERT new exam or UPDATE score on duplicate key
-- ============================================================
DROP PROCEDURE IF EXISTS sp_save_exam$$
CREATE PROCEDURE sp_save_exam(
    IN  p_teacher_id  INT UNSIGNED,
    IN  p_student_id  INT UNSIGNED,
    IN  p_section_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_score       DECIMAL(5,2),
    IN  p_exam_date   DATE,
    OUT p_exam_id     INT UNSIGNED
)
BEGIN
    INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score)
    VALUES (p_teacher_id, p_student_id, p_section_id, p_exam_date, p_exam_type, p_score)
    ON DUPLICATE KEY UPDATE
        score     = VALUES(score),
        exam_type = VALUES(exam_type);

    SET p_exam_id = LAST_INSERT_ID();
END$$

-- ============================================================
-- PROCEDURE: sp_update_exam_score
-- Update an existing exam record's score (teacher-owned only)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_update_exam_score$$
CREATE PROCEDURE sp_update_exam_score(
    IN p_exam_id    INT UNSIGNED,
    IN p_score      DECIMAL(5,2),
    IN p_teacher_id INT UNSIGNED
)
BEGIN
    UPDATE exam
    SET score = p_score
    WHERE exam_id = p_exam_id AND teacher_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END$$

-- ============================================================
-- PROCEDURE: sp_delete_exam
-- Delete an exam record (teacher-owned only)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_delete_exam$$
CREATE PROCEDURE sp_delete_exam(
    IN p_exam_id    INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED
)
BEGIN
    DELETE FROM exam
    WHERE exam_id = p_exam_id AND teacher_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END$$

-- ============================================================
-- PROCEDURE: sp_update_letter_grade
-- Update or clear the letter grade in takes table
-- ============================================================
DROP PROCEDURE IF EXISTS sp_update_letter_grade$$
CREATE PROCEDURE sp_update_letter_grade(
    IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED,
    IN p_grade      VARCHAR(5)
)
BEGIN
    UPDATE takes
    SET grade = p_grade
    WHERE student_id = p_student_id AND section_id = p_section_id;
    SELECT ROW_COUNT() AS affected_rows;
END$$

-- ============================================================
-- PROCEDURE: sp_auto_assign_grades
-- Auto-assign letter grades for all students in a section
-- based on weighted average using fn_score_to_grade
-- ============================================================
DROP PROCEDURE IF EXISTS sp_auto_assign_grades$$
CREATE PROCEDURE sp_auto_assign_grades(
    IN p_section_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED
)
BEGIN
    -- Verify teacher teaches this section
    DECLARE v_ok INT DEFAULT 0;
    SELECT COUNT(*) INTO v_ok FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_ok > 0 THEN
        UPDATE takes tk
        SET tk.grade = fn_score_to_grade(fn_student_section_avg(tk.student_id, p_section_id))
        WHERE tk.section_id = p_section_id
          AND fn_student_section_avg(tk.student_id, p_section_id) IS NOT NULL;
        SELECT ROW_COUNT() AS updated_count;
    ELSE
        SELECT 0 AS updated_count;
    END IF;
END$$

-- ============================================================
-- PROCEDURE: sp_update_teacher_avatar
-- ============================================================
DROP PROCEDURE IF EXISTS sp_update_teacher_avatar$$
CREATE PROCEDURE sp_update_teacher_avatar(
    IN p_teacher_id INT UNSIGNED,
    IN p_image      VARCHAR(255)
)
BEGIN
    UPDATE user SET image = p_image WHERE user_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END$$

-- ============================================================
-- PROCEDURE: sp_update_teacher_profile
-- ============================================================
DROP PROCEDURE IF EXISTS sp_update_teacher_profile$$
CREATE PROCEDURE sp_update_teacher_profile(
    IN p_teacher_id INT UNSIGNED,
    IN p_name       VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_gender     VARCHAR(10)
)
BEGIN
    UPDATE user
    SET name = p_name, phone = p_phone, gender = p_gender
    WHERE user_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END$$

-- ============================================================
-- PROCEDURE: sp_get_dashboard_stats
-- Aggregated stats for teacher dashboard
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_dashboard_stats$$
CREATE PROCEDURE sp_get_dashboard_stats(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        COUNT(DISTINCT tg.section_id)                                          AS total_sections,
        COUNT(DISTINCT tk.student_id)                                          AS total_students,
        COUNT(DISTINCT e.exam_id)                                              AS total_exams,
        ROUND(AVG(e.score), 1)                                                 AS overall_avg,
        SUM(CASE WHEN tk.grade IS NULL AND tk.student_id IS NOT NULL THEN 1
                 ELSE 0 END)                                                   AS ungraded_count
    FROM teaching tg
    LEFT JOIN takes tk ON tg.section_id = tk.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON tg.section_id = e.section_id  AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id;
END$$

-- ============================================================
-- PROCEDURE: sp_change_password
-- Changes a user's password (plain-text, consistent with login_check.php)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_change_password$$
CREATE PROCEDURE sp_change_password(
    IN  p_user_id      INT UNSIGNED,
    IN  p_old_password VARCHAR(255),
    IN  p_new_password VARCHAR(255),
    OUT p_success      TINYINT,
    OUT p_message      VARCHAR(200)
)
BEGIN
    DECLARE v_match INT DEFAULT 0;

    SELECT COUNT(*) INTO v_match
    FROM user
    WHERE user_id = p_user_id AND password = p_old_password;

    IF v_match = 0 THEN
        SET p_success = 0;
        SET p_message = '当前密码不正确';
    ELSEIF p_new_password = p_old_password THEN
        SET p_success = 0;
        SET p_message = '新密码不能与当前密码相同';
    ELSEIF CHAR_LENGTH(p_new_password) < 6 THEN
        SET p_success = 0;
        SET p_message = '新密码至少需要 6 位';
    ELSE
        UPDATE user SET password = p_new_password WHERE user_id = p_user_id;
        SET p_success = 1;
        SET p_message = '密码已更新';
    END IF;
END$$

DELIMITER ;