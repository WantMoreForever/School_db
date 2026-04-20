/*
  announcement_procedures.sql
  ─────────────────────────────────────────────────────────────────
  课程公告模块 — Section Announcements
  ─────────────────────────────────────────────────────────────────
  新建 section_announcement 表，教师可对名下课程发布/编辑/置顶公告。

  SQL 要素：
    CREATE TABLE  – section_announcement
    INSERT        – sp_post_announcement
    UPDATE        – sp_update_announcement, sp_pin_announcement
    DELETE        – sp_delete_announcement
    SELECT        – sp_get_section_announcements (带置顶排序)
                    sp_get_teacher_announcements (跨课程汇总)
  ─────────────────────────────────────────────────────────────────
*/

USE school_db;

-- ═══════════════════════════════════════════════════════════════
-- TABLE
-- ═══════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `section_announcement` (
  `announcement_id` int(10) UNSIGNED     NOT NULL AUTO_INCREMENT,
  `section_id`      int(10) UNSIGNED     NOT NULL,
  `teacher_id`      int(10) UNSIGNED     NOT NULL,
  `title`           varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `content`         text         CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_pinned`       tinyint(1)   NOT NULL DEFAULT 0  COMMENT '1=置顶',
  `created_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`announcement_id`) USING BTREE,
  INDEX `idx_ann_section` (`section_id` ASC),
  INDEX `idx_ann_teacher` (`teacher_id` ASC),
  INDEX `idx_ann_pinned`  (`is_pinned` DESC, `created_at` DESC),
  CONSTRAINT `fk_ann_section`
    FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ann_teacher`
    FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB CHARACTER SET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='课程公告' ROW_FORMAT=DYNAMIC;


-- ═══════════════════════════════════════════════════════════════
-- PROCEDURES
-- ═══════════════════════════════════════════════════════════════
DELIMITER $$

-- ────────────────────────────────────────────────────────────────
-- sp_post_announcement
-- 教师发布新公告（验证教师确实教授该 section）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_post_announcement$$
CREATE PROCEDURE sp_post_announcement(
    IN  p_teacher_id       INT UNSIGNED,
    IN  p_section_id       INT UNSIGNED,
    IN  p_title            VARCHAR(200),
    IN  p_content          TEXT,
    IN  p_is_pinned        TINYINT,
    OUT p_announcement_id  INT UNSIGNED,
    OUT p_success          TINYINT,
    OUT p_message          VARCHAR(500)
)
BEGIN
    DECLARE v_teaches INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0; SET p_announcement_id = NULL;
        SET p_message = 'Not authorized: you do not teach this section.';
    ELSEIF p_title IS NULL OR TRIM(p_title) = '' THEN
        SET p_success = 0; SET p_announcement_id = NULL;
        SET p_message = 'Title cannot be empty.';
    ELSEIF p_content IS NULL OR TRIM(p_content) = '' THEN
        SET p_success = 0; SET p_announcement_id = NULL;
        SET p_message = 'Content cannot be empty.';
    ELSE
        INSERT INTO section_announcement
            (section_id, teacher_id, title, content, is_pinned)
        VALUES
            (p_section_id, p_teacher_id, p_title, p_content, IFNULL(p_is_pinned, 0));

        SET p_announcement_id = LAST_INSERT_ID();
        SET p_success         = 1;
        SET p_message         = 'Announcement posted.';
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_update_announcement
-- 修改公告标题/内容（仅原作者可修改）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_update_announcement$$
CREATE PROCEDURE sp_update_announcement(
    IN  p_announcement_id  INT UNSIGNED,
    IN  p_teacher_id       INT UNSIGNED,
    IN  p_title            VARCHAR(200),
    IN  p_content          TEXT,
    OUT p_success          TINYINT,
    OUT p_message          VARCHAR(500)
)
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count FROM section_announcement
    WHERE announcement_id = p_announcement_id AND teacher_id = p_teacher_id;

    IF v_count = 0 THEN
        SET p_success = 0;
        SET p_message = 'Announcement not found or not authorized.';
    ELSE
        UPDATE section_announcement
        SET title   = IFNULL(p_title,   title),
            content = IFNULL(p_content, content)
        WHERE announcement_id = p_announcement_id;

        SET p_success = 1;
        SET p_message = 'Announcement updated.';
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_pin_announcement
-- 切换公告置顶状态（1 → 0 或 0 → 1）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_pin_announcement$$
CREATE PROCEDURE sp_pin_announcement(
    IN  p_announcement_id  INT UNSIGNED,
    IN  p_teacher_id       INT UNSIGNED,
    IN  p_pin              TINYINT,      -- 1=置顶 0=取消置顶
    OUT p_success          TINYINT,
    OUT p_message          VARCHAR(500)
)
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count FROM section_announcement
    WHERE announcement_id = p_announcement_id AND teacher_id = p_teacher_id;

    IF v_count = 0 THEN
        SET p_success = 0;
        SET p_message = 'Announcement not found or not authorized.';
    ELSE
        UPDATE section_announcement
        SET is_pinned = p_pin
        WHERE announcement_id = p_announcement_id;

        SET p_success = 1;
        SET p_message = IF(p_pin = 1, 'Announcement pinned.', 'Announcement unpinned.');
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_delete_announcement
-- 删除公告（仅原作者可删除）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_delete_announcement$$
CREATE PROCEDURE sp_delete_announcement(
    IN  p_announcement_id  INT UNSIGNED,
    IN  p_teacher_id       INT UNSIGNED,
    OUT p_success          TINYINT,
    OUT p_message          VARCHAR(500)
)
BEGIN
    DECLARE v_count INT DEFAULT 0;

    SELECT COUNT(*) INTO v_count FROM section_announcement
    WHERE announcement_id = p_announcement_id AND teacher_id = p_teacher_id;

    IF v_count = 0 THEN
        SET p_success = 0;
        SET p_message = 'Announcement not found or not authorized.';
    ELSE
        DELETE FROM section_announcement WHERE announcement_id = p_announcement_id;
        SET p_success = 1;
        SET p_message = 'Announcement deleted.';
    END IF;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_section_announcements
-- 获取某 section 的全部公告，置顶优先，其次按时间倒序
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_section_announcements$$
CREATE PROCEDURE sp_get_section_announcements(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        a.announcement_id,
        a.title,
        a.content,
        a.is_pinned,
        a.created_at,
        a.updated_at,
        u.name  AS teacher_name,
        u.image AS teacher_image,
        t.title AS teacher_title
    FROM section_announcement a
    JOIN user    u ON u.user_id = a.teacher_id
    JOIN teacher t ON t.user_id = a.teacher_id
    WHERE a.section_id = p_section_id
    ORDER BY a.is_pinned DESC, a.created_at DESC;
END$$

-- ────────────────────────────────────────────────────────────────
-- sp_get_teacher_announcements
-- 获取某教师在所有 section 发布的公告汇总（教师管理视图）
-- ────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS sp_get_teacher_announcements$$
CREATE PROCEDURE sp_get_teacher_announcements(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        a.announcement_id,
        a.section_id,
        c.name    AS course_name,
        sec.semester,
        sec.year,
        a.title,
        a.content,
        a.is_pinned,
        a.created_at,
        a.updated_at,
        -- 该 section 的已读人数（需前端记录，此处占位 0）
        COUNT(DISTINCT tk.student_id) AS audience_size
    FROM section_announcement a
    JOIN section sec ON sec.section_id = a.section_id
    JOIN course  c   ON c.course_id    = sec.course_id
    LEFT JOIN takes tk ON tk.section_id = a.section_id AND tk.status = 'enrolled'
    WHERE a.teacher_id = p_teacher_id
    GROUP BY a.announcement_id, a.section_id, c.name,
             sec.semester, sec.year, a.title, a.content, a.is_pinned,
             a.created_at, a.updated_at
    ORDER BY a.created_at DESC;
END$$

DELIMITER ;
