/*
  batch_grades_procedures.sql
  ─────────────────────────────────────────────────────────────────
  批量成绩导入模块 — Batch Exam Score Import
  ─────────────────────────────────────────────────────────────────
  通过 JSON 数组批量向 exam 表写入（或覆盖）考试成绩。
  支持按学号（student_no）或 student_id 匹配学生，
  只插入已选课的学生，超出 0-100 范围的成绩自动跳过。

  SQL 要素：
    INSERT … ON DUPLICATE KEY UPDATE – 成绩幂等写入
    JSON_TABLE                        – 解析 JSON 数组（MySQL 8.0+）
    EXISTS 子查询                     – 验证学生是否已选课
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_batch_import_exam
-- 批量导入一批学生的同类型考试成绩（期末/期中/测验）
--
-- p_records JSON 数组格式（两种均支持）：
--   按学号: [{"student_no":"20210001","score":85},  ...]
--   按 ID : [{"student_id":101,       "score":92.5},...]
--
-- OUT 参数：
--   p_saved   – 实际写入（新增或覆盖）的条数
--   p_skipped – 跳过的条数（未找到学生 / 成绩越界）
--   p_success – 0=失败 1=成功
--   p_message – 结果说明
-- ────────────────────────────────────────────────────────────────
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

    -- 权限验证：教师必须教授该 section
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

        -- 批量 UPSERT：student_no 优先，其次 student_id
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
                student_no  VARCHAR(8)   PATH '$.student_no',
                score       DECIMAL(5,2) PATH '$.score'
            )
        ) AS jt
        -- 支持按学号或 user_id 匹配
        JOIN student st ON (
            (jt.student_no IS NOT NULL AND jt.student_no <> '' AND st.student_no = jt.student_no)
            OR ((jt.student_no IS NULL OR jt.student_no = '')
                AND jt.student_id IS NOT NULL AND jt.student_id > 0
                AND st.user_id = jt.student_id)
        )
        -- 只允许已选课学生
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

        -- Pre-count valid/enrolled rows to avoid ON DUPLICATE KEY UPDATE inflating ROW_COUNT()
        -- (MySQL returns 1 for INSERT, 2 for changed UPDATE, 0 for no-change UPDATE)
        SELECT COUNT(*) INTO p_saved
        FROM JSON_TABLE(
            p_records,
            '$[*]' COLUMNS (
                student_id  INT          PATH '$.student_id',
                student_no  VARCHAR(8)   PATH '$.student_no',
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

DELIMITER ;
