-- procedures.sql
DELIMITER $$
CREATE PROCEDURE sp_get_student_base_info(IN p_user_id INT)
BEGIN
  SELECT u.user_id,
         u.name,
         u.email,
         u.phone,
         u.gender,
         u.status,
         u.created_at,
         u.image,
         s.student_no,
         s.grade,
         s.enrollment_year,
         d.dept_name
  FROM user u
  JOIN student s ON s.user_id = u.user_id
  JOIN department d ON d.dept_id = s.dept_id
  WHERE u.user_id = p_user_id;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_get_exam_semesters(IN p_student_id INT)
BEGIN
  SELECT DISTINCT CONCAT(sec.year,'-',sec.semester) AS sem_key, sec.year, sec.semester
  FROM exam e
  JOIN section sec ON sec.section_id = e.section_id
  WHERE e.student_id = p_student_id
  ORDER BY sec.year DESC, sec.semester ASC;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_get_student_exams(
  IN p_student_id INT,
  IN p_type VARCHAR(20),
  IN p_year INT,
  IN p_semester VARCHAR(20)
)
BEGIN
  SELECT
    e.exam_id, e.exam_date, e.exam_type, e.score,
    c.name AS course_name, c.credit,
    sec.semester, sec.year,
    u.name AS teacher_name, tc.title AS teacher_title
  FROM exam e
  JOIN section sec ON sec.section_id = e.section_id
  JOIN course c   ON c.course_id = sec.course_id
  JOIN teacher tc ON tc.user_id = e.teacher_id
  JOIN user u     ON u.user_id = e.teacher_id
  WHERE e.student_id = p_student_id
    AND e.score IS NOT NULL
    AND (p_type IS NULL OR p_type = '' OR e.exam_type = p_type)
    AND (p_year IS NULL OR p_year = 0 OR sec.year = p_year)
    AND (p_semester IS NULL OR p_semester = '' OR sec.semester = p_semester)
  ORDER BY e.exam_date DESC;
END $$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_calc_student_stats(IN p_student_id INT)
BEGIN
  SELECT
    COALESCE((
      SELECT ROUND(
        SUM(c.credit * (
          CASE
            WHEN e.score < 60 THEN 0.0
            WHEN e.score < 64 THEN 1.0
            WHEN e.score < 67 THEN 1.3
            WHEN e.score < 70 THEN 1.7
            WHEN e.score < 74 THEN 2.0
            WHEN e.score < 77 THEN 2.3
            WHEN e.score < 80 THEN 2.7
            WHEN e.score < 84 THEN 3.0
            WHEN e.score < 87 THEN 3.3
            WHEN e.score < 90 THEN 3.7
            ELSE 4.0
          END
        )) / NULLIF(SUM(c.credit),0), 2)
      FROM exam e
      JOIN section sec ON sec.section_id = e.section_id
      JOIN course c ON c.course_id = sec.course_id
      WHERE e.student_id = p_student_id
        AND e.score IS NOT NULL
        AND e.exam_type = 'final'   -- 【改动】只统计期末类型
    ), 0) AS gpa,

    COALESCE((
      SELECT COALESCE(SUM(sub.credit),0)
      FROM (
        SELECT e2.section_id, MAX(c2.credit) AS credit
        FROM exam e2
        JOIN section sec2 ON sec2.section_id = e2.section_id
        JOIN course c2   ON c2.course_id = sec2.course_id
        WHERE e2.student_id = p_student_id
          AND e2.exam_type = 'final'
          AND e2.score >= 60
        GROUP BY e2.section_id
      ) sub
    ), 0) AS credits,

    COALESCE((
      SELECT COUNT(DISTINCT e3.section_id)
      FROM exam e3
      WHERE e3.student_id = p_student_id
        AND e3.exam_type = 'final'
        AND e3.score IS NOT NULL
    ), 0) AS published,

    COALESCE((
      SELECT COUNT(*)
      FROM exam e4
      WHERE e4.student_id = p_student_id
        AND e4.score IS NOT NULL
    ), 0) AS exam_count;
END $$
DELIMITER ;

-- End of procedures.sql
