/*
 Grade Management — Stored Procedures & Functions
 Score recording and grade query (成绩上传、成绩查询)
 These procedures do NOT modify school_db existing tables
 Target Schema: school_db
 Date: 2026-03-30
*/

USE school_db;
DELIMITER $$

-- ============================================================
-- PROCEDURE: sp_get_final_scores
-- Get final exam scores for all students in a section
-- Includes: student info, final score, weighted avg, suggested grade
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

-- ============================================================
-- PROCEDURE: sp_get_student_final_score
-- Get final exam score for a specific student in a section
-- ============================================================
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

-- ============================================================
-- PROCEDURE: sp_get_course_avg_score
-- Get the average final score for a section
-- Including student count and score statistics
-- ============================================================
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

-- ============================================================
-- PROCEDURE: sp_get_course_avg_by_student
-- Get average final scores for all courses a student is enrolled in
-- ============================================================
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
-- PROCEDURE: sp_upload_exam_scores
-- Batch upload final exam scores (INSERT/UPDATE)
-- Expects: CSV-like data with student_id, score pairs
-- ============================================================
DROP PROCEDURE IF EXISTS sp_upload_exam_scores$$
CREATE PROCEDURE sp_upload_exam_scores(
    IN p_teacher_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED,
    IN p_exam_type VARCHAR(20),
    IN p_exam_date DATE,
    OUT p_count INT,
    OUT p_message VARCHAR(255)
)
BEGIN
    DECLARE v_ok INT;

    -- Verify teacher teaches this section
    SELECT COUNT(*) INTO v_ok
    FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_ok = 0 THEN
        SET p_count = 0;
        SET p_message = 'Not authorized';
    ELSEIF p_exam_type NOT IN ('final', 'midterm', 'quiz') THEN
        -- Validate exam type
        SET p_count = 0;
        SET p_message = 'Invalid exam_type. Must be: final, midterm, or quiz';
    ELSE
        -- Count affected rows
        SET p_count = 0;
        SET p_message = 'Scores ready for upload. Call with individual student scores.';
    END IF;
END$$

-- ============================================================
-- FUNCTION: fn_get_student_course_gpa
-- Calculate GPA for a student (weighted by credit hours)
-- Uses letter grades from takes table
-- ============================================================
DROP FUNCTION IF EXISTS fn_get_student_course_gpa$$
CREATE FUNCTION fn_get_student_course_gpa(p_student_id INT UNSIGNED)
RETURNS DECIMAL(3,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_gpa DECIMAL(3,2) DEFAULT NULL;
    DECLARE v_total_credits INT DEFAULT 0;
    DECLARE v_weighted_points DECIMAL(8,2) DEFAULT 0;

    -- Calculate weighted GPA from letter grades
    SELECT
        SUM(c.credit),
        SUM(
            CASE tk.grade
                WHEN 'A'  THEN c.credit * 4.0
                WHEN 'A-' THEN c.credit * 3.7
                WHEN 'B+' THEN c.credit * 3.3
                WHEN 'B'  THEN c.credit * 3.0
                WHEN 'B-' THEN c.credit * 2.7
                WHEN 'C+' THEN c.credit * 2.3
                WHEN 'C'  THEN c.credit * 2.0
                WHEN 'C-' THEN c.credit * 1.7
                WHEN 'D'  THEN c.credit * 1.0
                WHEN 'F'  THEN c.credit * 0.0
                ELSE 0
            END
        )
    INTO v_total_credits, v_weighted_points
    FROM takes tk
    JOIN section sec ON tk.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    WHERE tk.student_id = p_student_id
      AND tk.status = 'enrolled'
      AND tk.grade IS NOT NULL;

    IF v_total_credits > 0 AND v_weighted_points > 0 THEN
        SET v_gpa = ROUND(v_weighted_points / v_total_credits, 2);
    END IF;

    RETURN v_gpa;
END$$

-- ============================================================
-- PROCEDURE: sp_get_grade_distribution
-- Get grade distribution for a section (by letter grade)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_grade_distribution$$
CREATE PROCEDURE sp_get_grade_distribution(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        tk.grade AS letter_grade,
        COUNT(*) AS count,
        ROUND(COUNT(*) * 100 / (SELECT COUNT(*) FROM takes WHERE section_id = p_section_id AND status = 'enrolled'), 1) AS percentage
    FROM takes tk
    WHERE tk.section_id = p_section_id AND tk.status = 'enrolled' AND tk.grade IS NOT NULL
    GROUP BY tk.grade
    ORDER BY
        CASE tk.grade
            WHEN 'A'  THEN 1  WHEN 'A-' THEN 2  WHEN 'B+' THEN 3  WHEN 'B'  THEN 4
            WHEN 'B-' THEN 5  WHEN 'C+' THEN 6  WHEN 'C'  THEN 7  WHEN 'C-' THEN 8
            WHEN 'D'  THEN 9  WHEN 'F'  THEN 10 ELSE 11 END;
END$$

-- ============================================================
-- PROCEDURE: sp_get_exam_comparison
-- Compare students' scores across different exam types (final vs midterm vs quiz)
-- ============================================================
DROP PROCEDURE IF EXISTS sp_get_exam_comparison$$
CREATE PROCEDURE sp_get_exam_comparison(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.name,
        st.student_no,
        (SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final') AS final_avg,
        (SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm') AS midterm_avg,
        (SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz') AS quiz_avg,
        ROUND(
            COALESCE((SELECT AVG(score) * 0.50 FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final'), 0) +
            COALESCE((SELECT AVG(score) * 0.30 FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm'), 0) +
            COALESCE((SELECT AVG(score) * 0.20 FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz'), 0),
            2
        ) AS weighted_avg,
        fn_score_to_grade(
            COALESCE((SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final'), 0) * 0.50 +
            COALESCE((SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm'), 0) * 0.30 +
            COALESCE((SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz'), 0) * 0.20
        ) AS suggested_grade
    FROM takes tk
    JOIN student st ON tk.student_id = st.user_id
    JOIN user u ON st.user_id = u.user_id
    WHERE tk.section_id = p_section_id AND tk.status = 'enrolled'
    ORDER BY u.name;
END$$

DELIMITER ;
