/*
  exam_event_procedures.sql
  ─────────────────────────────────────────────────────────────────
  考试发布模块 — Exam Event Management（基于现有 exam 表，无新建表）
  ─────────────────────────────────────────────────────────────────
  「发布考试」= 为班级所有已选课学生在 exam 表中插入 score=NULL 占位行。
  「待录入」  = exam 表中存在 score IS NULL 的行的 (section, type, date) 组合。
  「取消考试」= 删除该组合中 score IS NULL 的占位行，已录入成绩行保留。

  SQL 要素：
    INSERT IGNORE + SELECT  – sp_publish_exam（批量插入占位行，幂等）
    EXISTS / NOT EXISTS     – 权限验证
    Correlated subquery     – 计算 enrolled_count（取自 takes 表）
    HAVING                  – 过滤仍有未录入行的考试组合
    LEFT JOIN               – sp_get_exam_entry_students（含未录入学生）
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;

DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_publish_exam
-- 为班级所有已选课学生插入 score=NULL 的 exam 占位行（INSERT IGNORE 幂等）
-- ────────────────────────────────────────────────────────────────
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
        -- INSERT IGNORE skips rows that violate the unique key (teacher, student, section, date)
        INSERT IGNORE INTO exam (teacher_id, student_id, section_id, exam_type, exam_date, score)
        SELECT p_teacher_id, tk.student_id, p_section_id, p_exam_type, p_exam_date, NULL
        FROM takes tk
        WHERE tk.section_id = p_section_id AND tk.status = 'enrolled';

        SET p_inserted = ROW_COUNT();
        SET p_ok  = 1;
        SET p_msg = CONCAT('考试已发布，共 ', p_inserted, ' 名学生');
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_exam_events
-- 返回某班级该教师的所有已发布考试（exam 表中存在的 type+date 组合）及录入进度
-- ────────────────────────────────────────────────────────────────
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

-- ────────────────────────────────────────────────────────────────
-- sp_get_pending_exams
-- 返回该教师所有班级中「仍有 score IS NULL 行」的考试组合
-- ────────────────────────────────────────────────────────────────
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

-- ────────────────────────────────────────────────────────────────
-- sp_get_exam_entry_students
-- 返回某考试组合的所有已选课学生及其当前成绩（NULL=未录入）
-- ────────────────────────────────────────────────────────────────
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

-- ────────────────────────────────────────────────────────────────
-- sp_cancel_exam_event
-- 删除该考试组合中 score IS NULL 的占位行；已录入成绩行保留
-- ────────────────────────────────────────────────────────────────
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

DELIMITER ;
