/*
  backup_audit.sql
  ─────────────────────────────────────────────────────────────────
  审计与备份模块 — Audit Trail & Backup
  ─────────────────────────────────────────────────────────────────
  新建两张审计表，自动记录对 exam（成绩）和 takes（字母等第/状态）
  的全部变更，并提供查询、回滚和快照导出过程。

  调用方式（PHP 端设置操作人）：
    SET @current_user_id = <teacher_id>;
    CALL sp_save_exam(...);     -- 触发器自动记录

  SQL 要素：
    CREATE TABLE  – exam_audit, grade_audit
    TRIGGER       – AFTER INSERT/UPDATE/BEFORE DELETE on exam
                    AFTER UPDATE on takes
    PROCEDURE     – 查询历史、恢复分数、截面快照、清理过期记录
    FUNCTION      – fn_get_score_at_time (时间点回溯)
    TRANSACTION   – sp_restore_exam_score 内原子更新
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;

-- ═══════════════════════════════════════════════════════════════
-- TABLES
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `exam_audit` (
  `audit_id`   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`     enum('INSERT','UPDATE','DELETE') NOT NULL  COMMENT '操作类型',
  `exam_id`    int(10) UNSIGNED NOT NULL                 COMMENT '对应 exam.exam_id',
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `exam_type`  varchar(20)      NOT NULL,
  `exam_date`  date             DEFAULT NULL,
  `old_score`  decimal(5,2)     DEFAULT NULL             COMMENT '变更前分数',
  `new_score`  decimal(5,2)     DEFAULT NULL             COMMENT '变更后分数',
  `changed_by` int(10) UNSIGNED DEFAULT NULL             COMMENT '操作人 user_id（由 @current_user_id 注入）',
  `changed_at` datetime         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`) USING BTREE,
  INDEX `idx_eaud_exam`    (`exam_id`    ASC),
  INDEX `idx_eaud_student` (`student_id` ASC),
  INDEX `idx_eaud_section` (`section_id` ASC),
  INDEX `idx_eaud_time`    (`changed_at` ASC)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='考试分数变更审计' ROW_FORMAT=DYNAMIC;


CREATE TABLE IF NOT EXISTS `grade_audit` (
  `audit_id`   int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `action`     enum('UPDATE','DELETE') NOT NULL           COMMENT '操作类型',
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `old_grade`  varchar(5)  DEFAULT NULL                   COMMENT '变更前字母等第',
  `new_grade`  varchar(5)  DEFAULT NULL                   COMMENT '变更后字母等第',
  `old_status` varchar(20) DEFAULT NULL                   COMMENT '变更前选课状态',
  `new_status` varchar(20) DEFAULT NULL                   COMMENT '变更后选课状态',
  `changed_by` int(10) UNSIGNED DEFAULT NULL              COMMENT '操作人 user_id',
  `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`audit_id`) USING BTREE,
  INDEX `idx_gaud_student` (`student_id` ASC),
  INDEX `idx_gaud_section` (`section_id` ASC),
  INDEX `idx_gaud_time`    (`changed_at` ASC)
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='字母等第与选课状态变更审计' ROW_FORMAT=DYNAMIC;


-- ═══════════════════════════════════════════════════════════════
-- TRIGGERS
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ── 新增考试记录 ──────────────────────────────────────────────
DROP TRIGGER IF EXISTS trg_exam_after_insert$$
CREATE TRIGGER trg_exam_after_insert
AFTER INSERT ON exam
FOR EACH ROW
BEGIN
    INSERT INTO exam_audit
        (action, exam_id, teacher_id, student_id, section_id,
         exam_type, exam_date, old_score, new_score, changed_by)
    VALUES
        ('INSERT', NEW.exam_id, NEW.teacher_id, NEW.student_id, NEW.section_id,
         NEW.exam_type, NEW.exam_date, NULL, NEW.score,
         COALESCE(@current_user_id, NEW.teacher_id));
END$$

-- ── 修改考试分数（仅分数有实际变化时才记录）──────────────────
DROP TRIGGER IF EXISTS trg_exam_after_update$$
CREATE TRIGGER trg_exam_after_update
AFTER UPDATE ON exam
FOR EACH ROW
BEGIN
    IF NOT (OLD.score <=> NEW.score) THEN   -- <=> 处理 NULL 安全比较
        INSERT INTO exam_audit
            (action, exam_id, teacher_id, student_id, section_id,
             exam_type, exam_date, old_score, new_score, changed_by)
        VALUES
            ('UPDATE', NEW.exam_id, NEW.teacher_id, NEW.student_id, NEW.section_id,
             NEW.exam_type, NEW.exam_date, OLD.score, NEW.score,
             COALESCE(@current_user_id, NEW.teacher_id));
    END IF;
END$$

-- ── 删除考试记录（先保存旧值）────────────────────────────────
DROP TRIGGER IF EXISTS trg_exam_before_delete$$
CREATE TRIGGER trg_exam_before_delete
BEFORE DELETE ON exam
FOR EACH ROW
BEGIN
    INSERT INTO exam_audit
        (action, exam_id, teacher_id, student_id, section_id,
         exam_type, exam_date, old_score, new_score, changed_by)
    VALUES
        ('DELETE', OLD.exam_id, OLD.teacher_id, OLD.student_id, OLD.section_id,
         OLD.exam_type, OLD.exam_date, OLD.score, NULL,
         COALESCE(@current_user_id, OLD.teacher_id));
END$$

-- ── 字母等第或选课状态变更 ────────────────────────────────────
DROP TRIGGER IF EXISTS trg_takes_after_update$$
CREATE TRIGGER trg_takes_after_update
AFTER UPDATE ON takes
FOR EACH ROW
BEGIN
    -- grade 或 status 任一变化即记录
    IF NOT (OLD.grade  <=> NEW.grade)
    OR NOT (OLD.status <=> NEW.status) THEN
        INSERT INTO grade_audit
            (action, student_id, section_id,
             old_grade, new_grade, old_status, new_status, changed_by)
        VALUES
            ('UPDATE', NEW.student_id, NEW.section_id,
             OLD.grade, NEW.grade, OLD.status, NEW.status,
             @current_user_id);
    END IF;
END$$

DELIMITER ;


-- ═══════════════════════════════════════════════════════════════
-- FUNCTION
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- fn_get_score_at_time
-- 返回某学生在某 section 某 exam_type 在指定时间点之前的最新分数
-- 用于时间点回溯分析（e.g. 期末提交前的分数是多少）
-- ────────────────────────────────────────────────────────────────
DROP FUNCTION IF EXISTS fn_get_score_at_time$$
CREATE FUNCTION fn_get_score_at_time(
    p_student_id INT UNSIGNED,
    p_section_id INT UNSIGNED,
    p_exam_type  VARCHAR(20),
    p_as_of      DATETIME
)
RETURNS DECIMAL(5,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_score DECIMAL(5,2) DEFAULT NULL;

    -- 在 p_as_of 时刻，找最近一次该记录的 new_score
    SELECT new_score INTO v_score
    FROM exam_audit
    WHERE student_id = p_student_id
      AND section_id = p_section_id
      AND exam_type  = p_exam_type
      AND action    != 'DELETE'
      AND changed_at <= p_as_of
    ORDER BY changed_at DESC
    LIMIT 1;

    RETURN v_score;
END$$

DELIMITER ;


-- ═══════════════════════════════════════════════════════════════
-- PROCEDURES
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_get_exam_audit
-- 查询某 section（可选过滤特定学生）的考试分数变更历史
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_exam_audit$$
CREATE PROCEDURE sp_get_exam_audit(
    IN p_section_id INT UNSIGNED,
    IN p_student_id INT UNSIGNED    -- NULL = 不限学生
)
BEGIN
    SELECT
        ea.audit_id,
        ea.action,
        ea.exam_id,
        ea.exam_type,
        ea.exam_date,
        ea.old_score,
        ea.new_score,
        ROUND(ea.new_score - COALESCE(ea.old_score, 0), 2) AS score_delta,
        su.name  AS student_name,
        st.student_no,
        tu.name  AS changed_by_name,
        ea.changed_at
    FROM exam_audit ea
    JOIN user    su ON su.user_id = ea.student_id
    JOIN student st ON st.user_id = ea.student_id
    JOIN user    tu ON tu.user_id = COALESCE(ea.changed_by, ea.teacher_id)
    WHERE ea.section_id = p_section_id
      AND (p_student_id IS NULL OR ea.student_id = p_student_id)
    ORDER BY ea.changed_at DESC;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_grade_audit
-- 查询某 section（可选过滤特定学生）的字母等第变更历史
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_grade_audit$$
CREATE PROCEDURE sp_get_grade_audit(
    IN p_section_id INT UNSIGNED,
    IN p_student_id INT UNSIGNED    -- NULL = 不限学生
)
BEGIN
    SELECT
        ga.audit_id,
        ga.action,
        ga.old_grade,
        ga.new_grade,
        ga.old_status,
        ga.new_status,
        su.name     AS student_name,
        st.student_no,
        cu.name     AS changed_by_name,
        ga.changed_at
    FROM grade_audit ga
    JOIN user    su ON su.user_id = ga.student_id
    JOIN student st ON st.user_id = ga.student_id
    LEFT JOIN user cu ON cu.user_id = ga.changed_by
    WHERE ga.section_id = p_section_id
      AND (p_student_id IS NULL OR ga.student_id = p_student_id)
    ORDER BY ga.changed_at DESC;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_restore_exam_score
-- 将某条 exam 的分数回滚到指定审计记录的 old_score
-- 原子操作：先验证权限，再在事务中更新
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_restore_exam_score$$
CREATE PROCEDURE sp_restore_exam_score(
    IN  p_audit_id    INT UNSIGNED,    -- 要回滚到的那条审计记录
    IN  p_teacher_id  INT UNSIGNED,
    OUT p_success     TINYINT,
    OUT p_message     VARCHAR(500)
)
BEGIN
    DECLARE v_exam_id    INT UNSIGNED;
    DECLARE v_old_score  DECIMAL(5,2);
    DECLARE v_teaches    INT DEFAULT 0;
    DECLARE v_section_id INT UNSIGNED;

    -- 取审计记录
    SELECT exam_id, old_score, section_id
    INTO   v_exam_id, v_old_score, v_section_id
    FROM   exam_audit
    WHERE  audit_id = p_audit_id;

    IF v_exam_id IS NULL THEN
        SET p_success = 0;
        SET p_message = 'Audit record not found.';
    ELSEIF v_old_score IS NULL THEN
        SET p_success = 0;
        SET p_message = 'No previous score to restore (this was the original INSERT).';
    ELSE
        -- 验证教师授权
        SELECT COUNT(*) INTO v_teaches FROM teaching
        WHERE teacher_id = p_teacher_id AND section_id = v_section_id;

        IF v_teaches = 0 THEN
            SET p_success = 0;
            SET p_message = 'Not authorized: you do not teach this section.';
        ELSE
            SET @current_user_id = p_teacher_id;

            START TRANSACTION;
                UPDATE exam SET score = v_old_score WHERE exam_id = v_exam_id;
            COMMIT;

            SET p_success = 1;
            SET p_message = CONCAT('Score restored to ', v_old_score);
        END IF;
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_section_grade_snapshot
-- 输出某 section 当前所有学生的成绩快照（可用于人工归档）
-- 包含：数值分数、加权均分、字母等第、选课状态
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_section_grade_snapshot$$
CREATE PROCEDURE sp_get_section_grade_snapshot(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        NOW()                                                         AS snapshot_time,
        p_section_id                                                  AS section_id,
        c.name                                                        AS course_name,
        sec.semester,
        sec.year,
        u.user_id                                                     AS student_id,
        u.name                                                        AS student_name,
        st.student_no,
        d.dept_name,
        tk.status,
        tk.grade                                                      AS letter_grade,
        (SELECT score FROM exam
         WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final'
         ORDER BY exam_date DESC LIMIT 1)                             AS final_score,
        (SELECT score FROM exam
         WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm'
         ORDER BY exam_date DESC LIMIT 1)                             AS midterm_score,
        (SELECT ROUND(AVG(score),2) FROM exam
         WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz')
                                                                      AS quiz_avg,
        fn_student_section_avg(u.user_id, p_section_id)              AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(u.user_id, p_section_id))
                                                                      AS suggested_grade,
        tk.enrolled_at
    FROM takes tk
    JOIN student    st  ON tk.student_id  = st.user_id
    JOIN user       u   ON st.user_id     = u.user_id
    JOIN department d   ON st.dept_id     = d.dept_id
    JOIN section    sec ON tk.section_id  = sec.section_id
    JOIN course     c   ON sec.course_id  = c.course_id
    WHERE tk.section_id = p_section_id
    ORDER BY tk.status, u.name;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_cleanup_old_audit
-- 删除超过指定天数的审计记录（保留最近 N 天）
-- 建议定期由管理员执行，防止审计表无限增长
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_cleanup_old_audit$$
CREATE PROCEDURE sp_cleanup_old_audit(
    IN  p_days_to_keep  INT,          -- 保留最近 N 天，其余删除
    OUT p_deleted_exams INT,
    OUT p_deleted_grades INT
)
BEGIN
    DECLARE v_cutoff DATETIME DEFAULT DATE_SUB(NOW(), INTERVAL p_days_to_keep DAY);

    DELETE FROM exam_audit  WHERE changed_at < v_cutoff;
    SET p_deleted_exams = ROW_COUNT();

    DELETE FROM grade_audit WHERE changed_at < v_cutoff;
    SET p_deleted_grades = ROW_COUNT();
END$$

DELIMITER ;
