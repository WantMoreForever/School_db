/*
  attendance_procedures.sql
  ─────────────────────────────────────────────────────────────────
  考勤管理模块 — Attendance Tracking
  ─────────────────────────────────────────────────────────────────
  新建 attendance 表，以 (schedule_id, student_id, week) 为唯一键，
  支持单条录入、JSON 批量录入和多维统计查询。

  SQL 要素：
    CREATE TABLE  – attendance
    INSERT/UPSERT – sp_record_attendance (INSERT … ON DUPLICATE KEY UPDATE)
    JSON 批量     – sp_batch_record_attendance (JSON_TABLE, MySQL 8.0+)
    SELECT        – sp_get_schedule_attendance, sp_get_student_attendance_summary,
                    sp_get_section_attendance_report
    FUNCTION      – fn_student_attendance_rate
    CASE/WHEN     – 多状态条件聚合
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;

-- ═══════════════════════════════════════════════════════════════
-- TABLE
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `attendance` (
  `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id`   int(10) UNSIGNED NOT NULL  COMMENT '关联 schedule.schedule_id（具体排课时间）',
  `student_id`    int(10) UNSIGNED NOT NULL,
  `week`          tinyint(2) UNSIGNED NOT NULL  COMMENT '第几周 (1-16)',
  `status`        enum('present','absent','late','excused') NOT NULL DEFAULT 'present',
  `note`          varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recorded_by`   int(10) UNSIGNED NOT NULL  COMMENT '记录人 teacher_id',
  `recorded_at`   datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`) USING BTREE,
  UNIQUE KEY `uq_attendance` (`schedule_id`, `student_id`, `week`) USING BTREE,
  INDEX `idx_att_student`  (`student_id`  ASC),
  INDEX `idx_att_schedule` (`schedule_id` ASC),
  INDEX `idx_att_week`     (`week`        ASC),
  INDEX `idx_att_status`   (`status`      ASC),
  CONSTRAINT `fk_att_schedule`
    FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_student`
    FOREIGN KEY (`student_id`)  REFERENCES `student` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_recorder`
    FOREIGN KEY (`recorded_by`) REFERENCES `teacher` (`user_id`)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='考勤记录' ROW_FORMAT=DYNAMIC;


-- ═══════════════════════════════════════════════════════════════
-- FUNCTION
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- fn_student_attendance_rate
-- 计算学生在某 section（所有关联 schedule）的出勤率（%）
-- present + late 均计为出勤
-- ────────────────────────────────────────────────────────────────
DROP FUNCTION IF EXISTS fn_student_attendance_rate$$
CREATE FUNCTION fn_student_attendance_rate(
    p_student_id INT UNSIGNED,
    p_section_id INT UNSIGNED
)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_total   INT DEFAULT 0;
    DECLARE v_present INT DEFAULT 0;

    SELECT
        COUNT(*),
        SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END)
    INTO v_total, v_present
    FROM attendance a
    JOIN schedule s ON s.schedule_id = a.schedule_id
    WHERE s.section_id  = p_section_id
      AND a.student_id  = p_student_id;

    IF v_total = 0 THEN RETURN NULL; END IF;
    RETURN ROUND(v_present * 100.0 / v_total, 2);
END$$

DELIMITER ;


-- ═══════════════════════════════════════════════════════════════
-- PROCEDURES
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_record_attendance
-- 单条考勤录入（Upsert：已存在则覆盖）
-- 验证：教师教授该 schedule 所属 section；学生已选该课
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_record_attendance$$
CREATE PROCEDURE sp_record_attendance(
    IN  p_teacher_id   INT UNSIGNED,
    IN  p_schedule_id  INT UNSIGNED,
    IN  p_student_id   INT UNSIGNED,
    IN  p_week         TINYINT UNSIGNED,
    IN  p_status       VARCHAR(20),
    IN  p_note         VARCHAR(200),
    OUT p_success      TINYINT,
    OUT p_message      VARCHAR(500)
)
BEGIN
    DECLARE v_section_id  INT UNSIGNED;
    DECLARE v_teaches     INT DEFAULT 0;
    DECLARE v_enrolled    INT DEFAULT 0;

    -- 取 schedule 所属 section
    SELECT section_id INTO v_section_id FROM schedule WHERE schedule_id = p_schedule_id;

    IF v_section_id IS NULL THEN
        SET p_success = 0; SET p_message = 'Schedule not found.';
    ELSE
        SELECT COUNT(*) INTO v_teaches FROM teaching
        WHERE teacher_id = p_teacher_id AND section_id = v_section_id;

        SELECT COUNT(*) INTO v_enrolled FROM takes
        WHERE student_id = p_student_id AND section_id = v_section_id
          AND status = 'enrolled';

        IF v_teaches = 0 THEN
            SET p_success = 0;
            SET p_message = 'Not authorized: you do not teach this section.';
        ELSEIF v_enrolled = 0 THEN
            SET p_success = 0;
            SET p_message = 'Student is not enrolled in this section.';
        ELSEIF p_status NOT IN ('present','absent','late','excused') THEN
            SET p_success = 0;
            SET p_message = 'Invalid status. Use: present, absent, late, excused.';
        ELSEIF p_week < 1 OR p_week > 16 THEN
            SET p_success = 0;
            SET p_message = 'Week must be between 1 and 16.';
        ELSE
            INSERT INTO attendance (schedule_id, student_id, week, status, note, recorded_by)
            VALUES (p_schedule_id, p_student_id, p_week, p_status, p_note, p_teacher_id)
            ON DUPLICATE KEY UPDATE
                status      = VALUES(status),
                note        = VALUES(note),
                recorded_by = VALUES(recorded_by),
                recorded_at = NOW();

            SET p_success = 1;
            SET p_message = 'Attendance recorded.';
        END IF;
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_batch_record_attendance
-- 批量录入一节课（schedule_id + week）的全班考勤
-- p_records: JSON 数组，格式：
--   [{"student_id":1,"status":"present"},
--    {"student_id":2,"status":"absent","note":"sick"}, ...]
-- 依赖 MySQL 8.0+ JSON_TABLE
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_batch_record_attendance$$
CREATE PROCEDURE sp_batch_record_attendance(
    IN  p_teacher_id  INT UNSIGNED,
    IN  p_schedule_id INT UNSIGNED,
    IN  p_week        TINYINT UNSIGNED,
    IN  p_records     JSON,             -- JSON array
    OUT p_inserted    INT,
    OUT p_success     TINYINT,
    OUT p_message     VARCHAR(500)
)
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_teaches    INT DEFAULT 0;

    SELECT section_id INTO v_section_id FROM schedule WHERE schedule_id = p_schedule_id;

    IF v_section_id IS NULL THEN
        SET p_success = 0; SET p_inserted = 0;
        SET p_message = 'Schedule not found.';
    ELSE
        SELECT COUNT(*) INTO v_teaches FROM teaching
        WHERE teacher_id = p_teacher_id AND section_id = v_section_id;

        IF v_teaches = 0 THEN
            SET p_success = 0; SET p_inserted = 0;
            SET p_message = 'Not authorized.';
        ELSEIF p_week < 1 OR p_week > 16 THEN
            SET p_success = 0; SET p_inserted = 0;
            SET p_message = 'Week must be between 1 and 16.';
        ELSE
            INSERT INTO attendance (schedule_id, student_id, week, status, note, recorded_by)
            SELECT
                p_schedule_id,
                jt.student_id,
                p_week,
                jt.status,
                jt.note,
                p_teacher_id
            FROM JSON_TABLE(
                p_records,
                '$[*]' COLUMNS (
                    student_id INT UNSIGNED PATH '$.student_id',
                    status     VARCHAR(20)  PATH '$.status'  DEFAULT 'present' ON EMPTY,
                    note       VARCHAR(200) PATH '$.note'
                )
            ) AS jt
            -- 只插入实际已选课的学生
            WHERE EXISTS (
                SELECT 1 FROM takes
                WHERE student_id = jt.student_id
                  AND section_id = v_section_id
                  AND status     = 'enrolled'
            )
            ON DUPLICATE KEY UPDATE
                status      = VALUES(status),
                note        = VALUES(note),
                recorded_by = VALUES(recorded_by),
                recorded_at = NOW();

            SET p_inserted = ROW_COUNT();
            SET p_success  = 1;
            SET p_message  = CONCAT(p_inserted, ' attendance record(s) saved.');
        END IF;
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_schedule_attendance
-- 获取某次排课（schedule_id + week）的全班考勤明细
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_schedule_attendance$$
CREATE PROCEDURE sp_get_schedule_attendance(
    IN p_schedule_id INT UNSIGNED,
    IN p_week        TINYINT UNSIGNED
)
BEGIN
    SELECT
        u.user_id       AS student_id,
        u.name          AS student_name,
        u.image,
        st.student_no,
        d.dept_name,
        COALESCE(a.status, 'not_recorded') AS status,
        a.note,
        a.recorded_at
    FROM takes tk
    JOIN student    st ON st.user_id = tk.student_id
    JOIN user       u  ON u.user_id  = tk.student_id
    JOIN department d  ON d.dept_id  = st.dept_id
    JOIN schedule   sc ON sc.schedule_id = p_schedule_id
    LEFT JOIN attendance a
           ON a.schedule_id = p_schedule_id
          AND a.student_id  = tk.student_id
          AND a.week        = p_week
    WHERE tk.section_id = sc.section_id
      AND tk.status     = 'enrolled'
    ORDER BY u.name;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_student_attendance_summary
-- 获取学生在某 section 的考勤汇总（按周/状态分类）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_student_attendance_summary$$
CREATE PROCEDURE sp_get_student_attendance_summary(
    IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED
)
BEGIN
    SELECT
        u.name          AS student_name,
        st.student_no,
        c.name          AS course_name,
        sec.semester,
        sec.year,
        COUNT(*)        AS total_recorded,
        SUM(CASE WHEN a.status = 'present'  THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN a.status = 'absent'   THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN a.status = 'late'     THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN a.status = 'excused'  THEN 1 ELSE 0 END) AS excused_count,
        fn_student_attendance_rate(p_student_id, p_section_id)  AS attendance_rate_pct
    FROM attendance a
    JOIN schedule   s   ON s.schedule_id  = a.schedule_id
    JOIN section    sec ON sec.section_id = s.section_id
    JOIN course     c   ON c.course_id    = sec.course_id
    JOIN student    st  ON st.user_id     = a.student_id
    JOIN user       u   ON u.user_id      = a.student_id
    WHERE a.student_id  = p_student_id
      AND s.section_id  = p_section_id
    GROUP BY u.name, st.student_no, c.name, sec.semester, sec.year;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_section_attendance_report
-- 全课程考勤报表：每位学生出勤率 + 出勤不足预警（< 阈值则标记）
-- p_warn_threshold: 出勤率警戒线（默认 75）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_section_attendance_report$$
CREATE PROCEDURE sp_get_section_attendance_report(
    IN p_section_id       INT UNSIGNED,
    IN p_warn_threshold   DECIMAL(5,2)    -- 例如 75.00 代表 75%
)
BEGIN
    SET p_warn_threshold = IFNULL(p_warn_threshold, 75.00);

    SELECT
        u.user_id   AS student_id,
        u.name      AS student_name,
        st.student_no,
        d.dept_name,
        COUNT(*)    AS total_recorded,
        SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended,
        SUM(CASE WHEN a.status = 'absent'  THEN 1 ELSE 0 END)          AS absent_count,
        SUM(CASE WHEN a.status = 'late'    THEN 1 ELSE 0 END)          AS late_count,
        SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END)          AS excused_count,
        fn_student_attendance_rate(u.user_id, p_section_id)             AS attendance_rate_pct,
        CASE
            WHEN fn_student_attendance_rate(u.user_id, p_section_id) IS NULL THEN 'no_data'
            WHEN fn_student_attendance_rate(u.user_id, p_section_id) < p_warn_threshold THEN 'warning'
            ELSE 'ok'
        END AS attendance_flag
    FROM takes tk
    JOIN student    st ON st.user_id = tk.student_id
    JOIN user       u  ON u.user_id  = tk.student_id
    JOIN department d  ON d.dept_id  = st.dept_id
    LEFT JOIN attendance a
           ON a.student_id = tk.student_id
    LEFT JOIN schedule s
           ON s.schedule_id = a.schedule_id AND s.section_id = p_section_id
    WHERE tk.section_id = p_section_id
      AND tk.status     = 'enrolled'
    GROUP BY u.user_id, u.name, st.student_no, d.dept_name
    ORDER BY attendance_rate_pct ASC, u.name;
END$$

DELIMITER ;
