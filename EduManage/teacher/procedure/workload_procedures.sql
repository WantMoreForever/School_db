/*
  workload_procedures.sql
  ─────────────────────────────────────────────────────────────────
  教师工作量汇报模块 — Teacher Workload Report
  ─────────────────────────────────────────────────────────────────
  聚合教师在各学期的教学负荷数据：
    · 班级数、学生总数、学分总量、周课时
    · 考试录入量、成绩批改完成率
    · 支持按学期/年份过滤

  SQL 要素：
    多表 JOIN 聚合（teaching / section / course / takes / exam / schedule）
    相关子查询                – 避免 schedule 与 takes JOIN 引起的行数膨胀
    FIELD()                  – Fall 在前排序
    TIME_TO_SEC()            – 计算排课时长（分钟）
    NULLIF / ROUND           – 安全除法 + 精度控制
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_get_teacher_semesters
-- 返回该教师历史上出现过的所有学期，供前端下拉筛选
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_teacher_semesters$$
CREATE PROCEDURE sp_get_teacher_semesters(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT DISTINCT sec.year, sec.semester
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    WHERE tg.teacher_id = p_teacher_id
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring');
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_workload_summary
-- 汇总统计（单行）：班级数 / 学生数 / 学分总量 / 周课时 /
--                   已批改 / 待批改 / 综合平均分
-- p_semester = '' 表示不限学期
-- p_year     = 0  表示不限年份
-- ────────────────────────────────────────────────────────────────
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
        SUM(CASE WHEN tk.grade IS NOT NULL AND tk.student_id IS NOT NULL
                 THEN 1 ELSE 0 END)     AS graded_students,
        SUM(CASE WHEN tk.grade IS NULL  AND tk.student_id IS NOT NULL
                 THEN 1 ELSE 0 END)     AS ungraded_students,
        -- 学分总量：correlated subquery 避免重复累加
        (SELECT COALESCE(SUM(c2.credit), 0)
         FROM teaching tg2
         JOIN section s2 ON s2.section_id = tg2.section_id
         JOIN course  c2 ON c2.course_id  = s2.course_id
         WHERE tg2.teacher_id = p_teacher_id
           AND (p_semester = '' OR s2.semester = p_semester)
           AND (p_year = 0     OR s2.year     = p_year)
        )                               AS total_credit_load,
        -- 周课时（分钟）：correlated subquery 避免 JOIN 行膨胀
        (SELECT COALESCE(SUM(
             (TIME_TO_SEC(sch.end_time) - TIME_TO_SEC(sch.start_time)) / 60
         ), 0)
         FROM teaching tg3
         JOIN section  s3  ON s3.section_id  = tg3.section_id
         JOIN schedule sch ON sch.section_id = tg3.section_id
         WHERE tg3.teacher_id = p_teacher_id
           AND (p_semester = '' OR s3.semester = p_semester)
           AND (p_year = 0     OR s3.year     = p_year)
        )                               AS total_weekly_minutes
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    LEFT JOIN takes tk ON tk.section_id = sec.section_id AND tk.status = 'enrolled'
    LEFT JOIN exam  e  ON e.section_id  = sec.section_id AND e.teacher_id = p_teacher_id
    WHERE tg.teacher_id = p_teacher_id
      AND (p_semester = '' OR sec.semester = p_semester)
      AND (p_year = 0     OR sec.year     = p_year);
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_workload_by_section
-- 逐班级明细：含学生数、考试数、平均分、批改完成率、周课时
-- ────────────────────────────────────────────────────────────────
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
        ROUND(
            SUM(CASE WHEN tk.grade IS NOT NULL THEN 1 ELSE 0 END) * 100.0
            / NULLIF(COUNT(DISTINCT tk.student_id), 0)
        , 1)                            AS grade_completion_pct,
        -- 排课槽数（correlated 避免行膨胀）
        (SELECT COUNT(*)
         FROM schedule sch
         WHERE sch.section_id = sec.section_id
        )                               AS schedule_slots,
        -- 周课时（分钟）
        (SELECT COALESCE(SUM(
             (TIME_TO_SEC(sch2.end_time) - TIME_TO_SEC(sch2.start_time)) / 60
         ), 0)
         FROM schedule sch2
         WHERE sch2.section_id = sec.section_id
        )                               AS weekly_minutes
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

DELIMITER ;
