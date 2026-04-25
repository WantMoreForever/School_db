/*
 Navicat Premium Dump SQL

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : school_db

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 23/04/2026 20:27:58
*/

SET NAMES utf8mb4;
CREATE DATABASE IF NOT EXISTS `school_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `school_db`;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin
-- ----------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin`  (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin' COMMENT '管理员角色',
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for advisor
-- ----------------------------
DROP TABLE IF EXISTS `advisor`;
CREATE TABLE `advisor`  (
  `teacher_id` int(10) UNSIGNED NOT NULL COMMENT '导师',
  `student_id` int(10) UNSIGNED NOT NULL COMMENT '学生',
  PRIMARY KEY (`teacher_id`, `student_id`) USING BTREE,
  INDEX `idx_advisor_student`(`student_id` ASC) USING BTREE,
  CONSTRAINT `fk_advisor_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_advisor_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for announcement
-- ----------------------------
DROP TABLE IF EXISTS `announcement`;
CREATE TABLE `announcement`  (
  `announcement_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `author_user_id` int(10) UNSIGNED NOT NULL COMMENT '发布人，对应 user.user_id',
  `title` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公告标题',
  `content` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '公告正文（纯文本）',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'published' COMMENT '状态：draft/published/archived',
  `is_pinned` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶：1=置顶',
  `published_at` datetime NULL DEFAULT CURRENT_TIMESTAMP COMMENT '发布时间',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`announcement_id`) USING BTREE,
  INDEX `idx_announcement_author`(`author_user_id` ASC) USING BTREE,
  INDEX `idx_announcement_status`(`status` ASC) USING BTREE,
  INDEX `idx_announcement_pub`(`published_at` ASC) USING BTREE,
  CONSTRAINT `fk_announcement_author` FOREIGN KEY (`author_user_id`) REFERENCES `user` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 28 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '公告主表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for announcement_target
-- ----------------------------
DROP TABLE IF EXISTS `announcement_target`;
CREATE TABLE `announcement_target`  (
  `target_row_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `announcement_id` int(10) UNSIGNED NOT NULL COMMENT '关联公告',
  `target_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'section' COMMENT '目标类型：当前仅使用 section',
  `target_id` int(10) UNSIGNED NOT NULL COMMENT '目标ID，当前对应 section.section_id',
  PRIMARY KEY (`target_row_id`) USING BTREE,
  UNIQUE INDEX `uq_announcement_target`(`announcement_id` ASC, `target_type` ASC, `target_id` ASC) USING BTREE,
  INDEX `idx_target_announcement`(`announcement_id` ASC) USING BTREE,
  INDEX `idx_target_type_id_announcement`(`target_type` ASC, `target_id` ASC, `announcement_id` ASC) USING BTREE,
  CONSTRAINT `fk_target_announcement` FOREIGN KEY (`announcement_id`) REFERENCES `announcement` (`announcement_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 34 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '公告投放目标表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for attendance
-- ----------------------------
DROP TABLE IF EXISTS `attendance`;
CREATE TABLE `attendance`  (
  `attendance_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `schedule_id` int(10) UNSIGNED NOT NULL COMMENT '关联 schedule.schedule_id（具体排课时间）',
  `student_id` int(10) UNSIGNED NOT NULL,
  `week` tinyint(2) UNSIGNED NOT NULL COMMENT '第几周 (1-16)',
  `status` enum('present','absent','late','excused') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'present',
  `note` varchar(200) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL,
  `recorded_by` int(10) UNSIGNED NOT NULL COMMENT '记录人 teacher_id',
  `recorded_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`attendance_id`) USING BTREE,
  UNIQUE INDEX `uq_attendance`(`schedule_id` ASC, `student_id` ASC, `week` ASC) USING BTREE,
  INDEX `idx_att_student`(`student_id` ASC) USING BTREE,
  INDEX `idx_att_schedule`(`schedule_id` ASC) USING BTREE,
  INDEX `idx_att_week`(`week` ASC) USING BTREE,
  INDEX `idx_att_status`(`status` ASC) USING BTREE,
  INDEX `fk_att_recorder`(`recorded_by` ASC) USING BTREE,
  CONSTRAINT `fk_att_recorder` FOREIGN KEY (`recorded_by`) REFERENCES `teacher` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_att_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `schedule` (`schedule_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_att_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '考勤记录' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for classroom
-- ----------------------------
DROP TABLE IF EXISTS `classroom`;
CREATE TABLE `classroom`  (
  `classroom_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `building` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '教学楼',
  `room_number` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '房间号',
  `capacity` int(10) UNSIGNED NOT NULL DEFAULT 50 COMMENT '容量',
  `type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'normal' COMMENT '教室类型：normal=普通, multimedia=多媒体, lab=机房',
  PRIMARY KEY (`classroom_id`) USING BTREE,
  UNIQUE INDEX `uq_classroom_room`(`building` ASC, `room_number` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for config
-- ----------------------------
DROP TABLE IF EXISTS `config`;
CREATE TABLE `config`  (
  `config_key` varchar(120) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '配置键（建议使用点号层级，如 schedule.total_weeks）',
  `config_value` json NOT NULL COMMENT '配置值（JSON）',
  `description` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '配置说明',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`config_key`) USING BTREE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '系统配置表（键值）' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of config
-- ----------------------------
INSERT INTO `config` VALUES ('office_phone', '\"12345678\"', '学生页展示的办公电话', '2026-04-18 20:09:36');
INSERT INTO `config` VALUES ('schedule.grid_end_h', '22', '课表结束小时', '2026-04-18 20:09:36');
INSERT INTO `config` VALUES ('schedule.grid_start_h', '8', '课表开始小时', '2026-04-18 20:09:36');
INSERT INTO `config` VALUES ('schedule.total_weeks', '18', '学期总周数', '2026-04-20 18:07:59');
INSERT INTO `config` VALUES ('term.fall_start_date', '\"2026-09-07\"', NULL, '2026-04-18 21:12:00');
INSERT INTO `config` VALUES ('term.spring_start_date', '\"2026-03-02\"', NULL, '2026-04-18 21:12:00');
-- ----------------------------
-- Table structure for course
-- ----------------------------
DROP TABLE IF EXISTS `course`;
CREATE TABLE `course`  (
  `course_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '课程名称',
  `credit` decimal(3, 1) NOT NULL COMMENT '学分',
  `hours` tinyint(3) UNSIGNED NOT NULL COMMENT '学时',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '课程描述',
  PRIMARY KEY (`course_id`) USING BTREE,
  UNIQUE INDEX `uq_course_name`(`name` ASC) USING BTREE,
  INDEX `idx_course_credit`(`credit` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for department
-- ----------------------------
DROP TABLE IF EXISTS `department`;
CREATE TABLE `department`  (
  `dept_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '院系名称',
  `dept_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '院系代码（1-10位大写字母）',
  PRIMARY KEY (`dept_id`) USING BTREE,
  UNIQUE INDEX `uq_dept_name`(`dept_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_dept_code`(`dept_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for exam
-- ----------------------------
DROP TABLE IF EXISTS `exam`;
CREATE TABLE `exam`  (
  `exam_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `teacher_id` int(10) UNSIGNED NOT NULL COMMENT '监考/评分教师',
  `student_id` int(10) UNSIGNED NOT NULL COMMENT '参考学生',
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '所属开课节',
  `exam_date` date NULL DEFAULT NULL COMMENT '考试日期',
  `exam_type` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'final' COMMENT '考试类型：final=期末，midterm=期中，quiz=平时测验',
  `score` decimal(5, 2) NULL DEFAULT NULL COMMENT '分数（百分制）',
  PRIMARY KEY (`exam_id`) USING BTREE,
  UNIQUE INDEX `uq_exam`(`teacher_id` ASC, `student_id` ASC, `section_id` ASC, `exam_date` ASC) USING BTREE,
  INDEX `idx_exam_student`(`student_id` ASC) USING BTREE,
  INDEX `idx_exam_section`(`section_id` ASC) USING BTREE,
  INDEX `idx_exam_date`(`exam_date` ASC) USING BTREE,
  INDEX `idx_exam_type`(`exam_type` ASC) USING BTREE,
  INDEX `idx_exam_teacher_section_event`(`teacher_id` ASC, `section_id` ASC, `exam_date` ASC, `exam_type` ASC) USING BTREE,
  INDEX `idx_exam_student_section_type_date`(`student_id` ASC, `section_id` ASC, `exam_type` ASC, `exam_date` ASC) USING BTREE,
  CONSTRAINT `fk_exam_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 34 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for major
-- ----------------------------
DROP TABLE IF EXISTS `major`;
CREATE TABLE `major`  (
  `major_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `major_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专业名称',
  `major_code` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专业代码（1-10位大写字母）',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '所属院系',
  PRIMARY KEY (`major_id`) USING BTREE,
  UNIQUE INDEX `uq_major_name`(`major_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_major_code`(`major_code` ASC) USING BTREE,
  INDEX `idx_major_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_major_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for restriction
-- ----------------------------
DROP TABLE IF EXISTS `restriction`;
CREATE TABLE `restriction`  (
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '开课节',
  `major_id` int(10) UNSIGNED NOT NULL COMMENT '允许选修的专业',
  PRIMARY KEY (`section_id`, `major_id`) USING BTREE,
  INDEX `idx_restriction_major`(`major_id` ASC) USING BTREE,
  CONSTRAINT `fk_restriction_major` FOREIGN KEY (`major_id`) REFERENCES `major` (`major_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_restriction_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '开课节选课专业限制表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for schedule
-- ----------------------------
DROP TABLE IF EXISTS `schedule`;
CREATE TABLE `schedule`  (
  `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '关联的开课节',
  `day_of_week` tinyint(3) UNSIGNED NOT NULL COMMENT '星期几 (1=周一, 7=周日)',
  `start_time` time NOT NULL COMMENT '上课开始时间',
  `end_time` time NOT NULL COMMENT '上课结束时间',
  `classroom_id` int(10) UNSIGNED NOT NULL COMMENT '关联教室',
  `week_start` tinyint(2) UNSIGNED NULL DEFAULT 1 COMMENT 'First week of class (1-16)',
  `week_end` tinyint(2) UNSIGNED NULL DEFAULT 13 COMMENT 'Last week of class (1-16)',
  PRIMARY KEY (`schedule_id`) USING BTREE,
  INDEX `idx_schedule_section_day_time`(`section_id` ASC, `day_of_week` ASC, `start_time` ASC, `end_time` ASC) USING BTREE,
  INDEX `idx_schedule_classroom_day_time`(`classroom_id` ASC, `day_of_week` ASC, `start_time` ASC, `end_time` ASC) USING BTREE,
  CONSTRAINT `fk_schedule_classroom` FOREIGN KEY (`classroom_id`) REFERENCES `classroom` (`classroom_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 18 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '排课时间表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for section
-- ----------------------------
DROP TABLE IF EXISTS `section`;
CREATE TABLE `section`  (
  `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `semester` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '学期（Spring/Fall）',
  `year` year NOT NULL COMMENT '学年',
  `course_id` int(10) UNSIGNED NOT NULL COMMENT '所属课程',
  `enrollment_start` datetime NULL DEFAULT NULL COMMENT '选课开始时间',
  `enrollment_end` datetime NULL DEFAULT NULL COMMENT '选课结束时间',
  `capacity` smallint(5) UNSIGNED NOT NULL DEFAULT 0 COMMENT '容量',
  PRIMARY KEY (`section_id`) USING BTREE,
  UNIQUE INDEX `uq_section`(`course_id` ASC, `semester` ASC, `year` ASC) USING BTREE,
  INDEX `idx_section_term_course`(`year` ASC, `semester` ASC, `course_id` ASC, `section_id` ASC) USING BTREE,
  CONSTRAINT `fk_section_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for student
-- ----------------------------
DROP TABLE IF EXISTS `student`;
CREATE TABLE `student`  (
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '关联 user.user_id',
  `student_no` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '学号（8位数字，唯一）',
  `grade` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '年级称谓（Sophomore 等）',
  `enrollment_year` year NULL DEFAULT NULL COMMENT '入学年份',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '所属院系',
  `major_id` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '所属专业',
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_student_no`(`student_no` ASC) USING BTREE,
  INDEX `idx_student_dept`(`dept_id` ASC) USING BTREE,
  INDEX `idx_student_major`(`major_id` ASC) USING BTREE,
  CONSTRAINT `fk_student_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_major` FOREIGN KEY (`major_id`) REFERENCES `major` (`major_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for system_log
-- ----------------------------
DROP TABLE IF EXISTS `system_log`;
CREATE TABLE `system_log`  (
  `log_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '操作人ID',
  `action` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '操作动作',
  `target_table` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '受影响表',
  `target_id` int(10) UNSIGNED NULL DEFAULT NULL COMMENT '受影响记录ID',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
  PRIMARY KEY (`log_id`) USING BTREE,
  INDEX `idx_log_user`(`user_id` ASC) USING BTREE,
  INDEX `idx_log_created_at`(`created_at` ASC) USING BTREE,
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 462 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for takes
-- ----------------------------
DROP TABLE IF EXISTS `takes`;
CREATE TABLE `takes`  (
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `grade` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '成绩（A/B+/C 等字母制）',
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '选课时间',
  PRIMARY KEY (`student_id`, `section_id`) USING BTREE,
  INDEX `idx_takes_section`(`section_id` ASC) USING BTREE,
  INDEX `idx_takes_enrolled`(`enrolled_at` ASC) USING BTREE,
  INDEX `idx_takes_student_enrolled`(`student_id` ASC, `enrolled_at` ASC) USING BTREE,
  CONSTRAINT `fk_takes_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_takes_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for teacher
-- ----------------------------
DROP TABLE IF EXISTS `teacher`;
CREATE TABLE `teacher`  (
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '关联 user.user_id',
  `title` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '职称',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '所属院系',
  PRIMARY KEY (`user_id`) USING BTREE,
  INDEX `idx_teacher_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_teacher_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for teaching
-- ----------------------------
DROP TABLE IF EXISTS `teaching`;
CREATE TABLE `teaching`  (
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`teacher_id`, `section_id`) USING BTREE,
  INDEX `idx_teaching_section`(`section_id` ASC) USING BTREE,
  CONSTRAINT `fk_teaching_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_teaching_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for time_slot
-- ----------------------------
DROP TABLE IF EXISTS `time_slot`;
CREATE TABLE `time_slot`  (
  `slot_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `slot_name` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '节次名称',
  `start_time` time NOT NULL COMMENT '开始时间',
  `end_time` time NOT NULL COMMENT '结束时间',
  PRIMARY KEY (`slot_id`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of time_slot
-- ----------------------------
INSERT INTO `time_slot` VALUES (1, '1-2节', '08:00:00', '09:40:00');
INSERT INTO `time_slot` VALUES (2, '3-4节', '10:00:00', '11:40:00');
INSERT INTO `time_slot` VALUES (3, '5-6节', '13:30:00', '15:10:00');
INSERT INTO `time_slot` VALUES (4, '7-8节', '15:30:00', '17:10:00');
INSERT INTO `time_slot` VALUES (5, '9-10节', '18:20:00', '19:50:00');
INSERT INTO `time_slot` VALUES (6, '11-12节', '20:00:00', '21:30:00');


-- ----------------------------
-- Table structure for user
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user`  (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '姓名',
  `email` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '邮箱',
  `password` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `status` enum('active','inactive','banned') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT '账号状态',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '注册时间',
  `gender` enum('male','female','other') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '性别',
  `phone` varchar(11) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号（11位数字）',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像文件名',
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_user_email`(`email` ASC) USING BTREE,
  UNIQUE INDEX `uq_user_phone`(`phone` ASC) USING BTREE,
  INDEX `idx_user_status`(`status` ASC) USING BTREE,
  INDEX `idx_user_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 40 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Drop views in dependency order
-- ----------------------------
DROP VIEW IF EXISTS `v_project_student_schedule_overview`;
DROP VIEW IF EXISTS `v_project_schedule_overview`;
DROP VIEW IF EXISTS `v_project_section_overview`;
DROP VIEW IF EXISTS `v_student_exam_grades`;
DROP VIEW IF EXISTS `v_project_section_teachers`;

-- ----------------------------
-- View structure for v_project_section_teachers
-- ----------------------------
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_project_section_teachers` AS select `tg`.`section_id` AS `section_id`,min(`tg`.`teacher_id`) AS `primary_teacher_id`,group_concat(distinct `u`.`name` order by `u`.`name` ASC separator '、') AS `teacher_names` from (`teaching` `tg` join `user` `u` on((`u`.`user_id` = `tg`.`teacher_id`))) group by `tg`.`section_id`;

-- ----------------------------
-- View structure for v_project_section_overview
-- ----------------------------
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_project_section_overview` AS select `sec`.`section_id` AS `section_id`,`sec`.`semester` AS `semester`,`sec`.`year` AS `year`,`sec`.`course_id` AS `course_id`,`sec`.`enrollment_start` AS `enrollment_start`,`sec`.`enrollment_end` AS `enrollment_end`,`sec`.`capacity` AS `capacity`,`c`.`name` AS `course_name`,`c`.`credit` AS `credit`,`c`.`hours` AS `hours`,`c`.`description` AS `description`,`tea`.`primary_teacher_id` AS `primary_teacher_id`,`u`.`name` AS `primary_teacher_name`,`tea`.`teacher_names` AS `teacher_names`,(select count(0) from `takes` `tk` where (`tk`.`section_id` = `sec`.`section_id`)) AS `enrolled_count` from (((`section` `sec` join `course` `c` on((`c`.`course_id` = `sec`.`course_id`))) left join `v_project_section_teachers` `tea` on((`tea`.`section_id` = `sec`.`section_id`))) left join `user` `u` on((`u`.`user_id` = `tea`.`primary_teacher_id`)));

-- ----------------------------
-- View structure for v_project_schedule_overview
-- ----------------------------
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_project_schedule_overview` AS select `sch`.`schedule_id` AS `schedule_id`,`sch`.`section_id` AS `section_id`,`sch`.`day_of_week` AS `day_of_week`,`sch`.`start_time` AS `start_time`,`sch`.`end_time` AS `end_time`,`sch`.`classroom_id` AS `classroom_id`,`sch`.`week_start` AS `week_start`,`sch`.`week_end` AS `week_end`,`sec`.`semester` AS `semester`,`sec`.`year` AS `year`,`sec`.`course_id` AS `course_id`,`sec`.`capacity` AS `capacity`,`c`.`name` AS `course_name`,`c`.`credit` AS `credit`,`c`.`hours` AS `hours`,`cl`.`building` AS `building`,`cl`.`room_number` AS `room_number`,concat(`cl`.`building`,'-',`cl`.`room_number`) AS `location`,`cl`.`capacity` AS `classroom_capacity`,`cl`.`type` AS `classroom_type`,`tea`.`primary_teacher_id` AS `primary_teacher_id`,`u`.`name` AS `primary_teacher_name`,`tea`.`teacher_names` AS `teacher_names`,`tc`.`title` AS `teacher_title` from ((((((`schedule` `sch` join `section` `sec` on((`sec`.`section_id` = `sch`.`section_id`))) join `course` `c` on((`c`.`course_id` = `sec`.`course_id`))) left join `classroom` `cl` on((`cl`.`classroom_id` = `sch`.`classroom_id`))) left join `v_project_section_teachers` `tea` on((`tea`.`section_id` = `sec`.`section_id`))) left join `teacher` `tc` on((`tc`.`user_id` = `tea`.`primary_teacher_id`))) left join `user` `u` on((`u`.`user_id` = `tea`.`primary_teacher_id`)));

-- ----------------------------
-- View structure for v_project_student_schedule_overview
-- ----------------------------
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_project_student_schedule_overview` AS select `tk`.`student_id` AS `student_id`,`tk`.`enrolled_at` AS `enrolled_at`,`v`.`schedule_id` AS `schedule_id`,`v`.`section_id` AS `section_id`,`v`.`day_of_week` AS `day_of_week`,`v`.`start_time` AS `start_time`,`v`.`end_time` AS `end_time`,`v`.`classroom_id` AS `classroom_id`,`v`.`week_start` AS `week_start`,`v`.`week_end` AS `week_end`,`v`.`semester` AS `semester`,`v`.`year` AS `year`,`v`.`course_id` AS `course_id`,`v`.`capacity` AS `capacity`,`v`.`course_name` AS `course_name`,`v`.`credit` AS `credit`,`v`.`hours` AS `hours`,`v`.`building` AS `building`,`v`.`room_number` AS `room_number`,`v`.`location` AS `location`,`v`.`classroom_capacity` AS `classroom_capacity`,`v`.`classroom_type` AS `classroom_type`,`v`.`primary_teacher_id` AS `primary_teacher_id`,`v`.`primary_teacher_name` AS `primary_teacher_name`,`v`.`teacher_names` AS `teacher_names`,`v`.`teacher_title` AS `teacher_title` from (`takes` `tk` join `v_project_schedule_overview` `v` on((`v`.`section_id` = `tk`.`section_id`)));

-- ----------------------------
-- View structure for v_student_exam_grades
-- ----------------------------
CREATE ALGORITHM = UNDEFINED SQL SECURITY DEFINER VIEW `v_student_exam_grades` AS select `e`.`exam_id` AS `exam_id`,`e`.`student_id` AS `student_id`,`e`.`exam_date` AS `exam_date`,`e`.`exam_type` AS `exam_type`,`e`.`score` AS `score`,`e`.`section_id` AS `section_id`,`c`.`name` AS `course_name`,`c`.`credit` AS `credit`,`sec`.`semester` AS `semester`,`sec`.`year` AS `year`,`u`.`name` AS `teacher_name`,`tc`.`title` AS `teacher_title` from ((((`exam` `e` join `section` `sec` on((`sec`.`section_id` = `e`.`section_id`))) join `course` `c` on((`c`.`course_id` = `sec`.`course_id`))) join `teacher` `tc` on((`tc`.`user_id` = `e`.`teacher_id`))) join `user` `u` on((`u`.`user_id` = `e`.`teacher_id`)));

-- ----------------------------
-- Function structure for fn_check_room_conflict
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_check_room_conflict`;
delimiter ;;
CREATE FUNCTION `fn_check_room_conflict`(p_classroom_id INT UNSIGNED,
    p_day_of_week INT,
    p_start_time TIME,
    p_end_time TIME,
    p_exclude_schedule_id INT UNSIGNED)
 RETURNS int(11)
  READS SQL DATA 
  DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM schedule
    WHERE classroom_id = p_classroom_id
      AND day_of_week = p_day_of_week
      AND schedule_id != p_exclude_schedule_id
      AND (
          (p_start_time < end_time AND p_end_time > start_time)
      );
    RETURN v_count;
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_check_teacher_conflict
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_check_teacher_conflict`;
delimiter ;;
CREATE FUNCTION `fn_check_teacher_conflict`(p_teacher_id INT UNSIGNED,
    p_day_of_week INT,
    p_start_time TIME,
    p_end_time TIME,
    p_exclude_schedule_id INT UNSIGNED)
 RETURNS int(11)
  READS SQL DATA 
  DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE tg.teacher_id = p_teacher_id
      AND s.day_of_week = p_day_of_week
      AND s.schedule_id != p_exclude_schedule_id
      AND (
          (p_start_time < s.end_time AND p_end_time > s.start_time)
      );
    RETURN v_count;
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_get_student_course_gpa
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_get_student_course_gpa`;
delimiter ;;
CREATE FUNCTION `fn_get_student_course_gpa`(p_student_id INT UNSIGNED)
 RETURNS decimal(3,2)
  READS SQL DATA 
  DETERMINISTIC
BEGIN
    DECLARE v_gpa DECIMAL(3,2) DEFAULT 0.00;
    DECLARE v_total_credits DECIMAL(8,2) DEFAULT 0.00;
    DECLARE v_weighted_points DECIMAL(10,2) DEFAULT 0.00;

    SELECT
        COALESCE(SUM(c.credit), 0),
        COALESCE(SUM(
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
        ), 0)
    INTO v_total_credits, v_weighted_points
    FROM takes tk
    JOIN section sec ON tk.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    WHERE tk.student_id = p_student_id
      AND tk.grade IS NOT NULL;

    IF v_total_credits > 0 THEN
        SET v_gpa = ROUND(v_weighted_points / v_total_credits, 2);
    END IF;

    RETURN v_gpa;
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_score_to_grade
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_score_to_grade`;
delimiter ;;
CREATE FUNCTION `fn_score_to_grade`(p_score DECIMAL(5,2))
 RETURNS varchar(5) CHARSET utf8mb4 COLLATE utf8mb4_unicode_ci
  READS SQL DATA 
  DETERMINISTIC
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
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_student_attendance_rate
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_student_attendance_rate`;
delimiter ;;
CREATE FUNCTION `fn_student_attendance_rate`(p_student_id INT UNSIGNED,
    p_section_id INT UNSIGNED)
 RETURNS decimal(5,2)
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
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_student_section_avg
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_student_section_avg`;
delimiter ;;
CREATE FUNCTION `fn_student_section_avg`(p_student_id INT UNSIGNED,
    p_section_id INT UNSIGNED)
 RETURNS decimal(5,2)
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
END
;;
delimiter ;

-- ----------------------------
-- Function structure for fn_teacher_section_count
-- ----------------------------
DROP FUNCTION IF EXISTS `fn_teacher_section_count`;
delimiter ;;
CREATE FUNCTION `fn_teacher_section_count`(p_teacher_id INT UNSIGNED)
 RETURNS int(11)
  READS SQL DATA 
  DETERMINISTIC
BEGIN
    DECLARE v_count INT DEFAULT 0;
    SELECT COUNT(*) INTO v_count FROM teaching WHERE teacher_id = p_teacher_id;
    RETURN v_count;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_add_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_add_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_add_schedule`(IN p_section_id INT UNSIGNED,
    IN p_day_of_week INT,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_classroom_id INT UNSIGNED,
    IN p_week_start INT UNSIGNED,
    IN p_week_end INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_schedule_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255))
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_room_conflict INT;
    DECLARE v_teacher_conflict INT;

    SELECT section_id INTO v_section_id
    FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_section_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Teacher does not teach this section';
        SET p_schedule_id = NULL;
    ELSEIF p_end_time <= p_start_time THEN
        SET p_success = FALSE;
        SET p_message = 'End time must be after start time';
        SET p_schedule_id = NULL;
    ELSEIF p_day_of_week < 1 OR p_day_of_week > 7 THEN
        SET p_success = FALSE;
        SET p_message = 'Day of week must be between 1 and 7';
        SET p_schedule_id = NULL;
    ELSE
        SET v_room_conflict = fn_check_room_conflict(
            p_classroom_id, p_day_of_week, p_start_time, p_end_time, 0
        );

        IF v_room_conflict > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Room is already reserved at this time';
            SET p_schedule_id = NULL;
        ELSE
            SET v_teacher_conflict = fn_check_teacher_conflict(
                p_teacher_id, p_day_of_week, p_start_time, p_end_time, 0
            );

            IF v_teacher_conflict > 0 THEN
                SET p_success = FALSE;
                SET p_message = 'Teacher has conflicting schedule at this time';
                SET p_schedule_id = NULL;
            ELSE
                INSERT INTO schedule (section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
                VALUES (p_section_id, p_day_of_week, p_start_time, p_end_time, p_classroom_id, p_week_start, p_week_end);

                SET p_schedule_id = LAST_INSERT_ID();
                SET p_success = TRUE;
                SET p_message = 'Schedule added successfully';
            END IF;
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_apply_to_teach
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_apply_to_teach`;
delimiter ;;
CREATE PROCEDURE `sp_apply_to_teach`(IN  p_teacher_id    INT UNSIGNED,
    IN  p_course_id     INT UNSIGNED,
    IN  p_semester      VARCHAR(20),
    IN  p_year          YEAR,
    IN  p_capacity      SMALLINT UNSIGNED,
    IN  p_enroll_start  DATETIME,
    IN  p_enroll_end    DATETIME,
    OUT p_section_id    INT UNSIGNED,
    OUT p_success       TINYINT,
    OUT p_message       VARCHAR(500))
BEGIN
    DECLARE v_section_id  INT UNSIGNED DEFAULT NULL;
    DECLARE v_teacher_ok  INT DEFAULT 0;
    DECLARE v_course_ok   INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLSTATE '45000'
    BEGIN
        ROLLBACK;
        SET p_success    = 0;
        SET p_section_id = NULL;
        SET p_message    = 'You are already assigned to teach this section.';
    END;

    SELECT COUNT(*) INTO v_teacher_ok FROM teacher WHERE user_id = p_teacher_id;
    SELECT COUNT(*) INTO v_course_ok  FROM course  WHERE course_id = p_course_id;

    IF v_teacher_ok = 0 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Teacher not found.';
    ELSEIF v_course_ok = 0 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Course not found.';
    ELSEIF p_semester NOT IN ('Spring','Fall') THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Semester must be Spring or Fall.';
    ELSEIF p_year < 2020 OR p_year > 2035 THEN
        SET p_success = 0; SET p_section_id = NULL;
        SET p_message = 'Year must be between 2020 and 2035.';
    ELSE
        START TRANSACTION;
        SELECT section_id INTO v_section_id
        FROM section
        WHERE course_id = p_course_id
          AND semester  = p_semester
          AND year      = p_year
        LIMIT 1;

        IF v_section_id IS NULL THEN
            INSERT INTO section
                (course_id, semester, year, capacity, enrollment_start, enrollment_end)
            VALUES
                (p_course_id, p_semester, p_year,
                 IFNULL(p_capacity, 30),
                 p_enroll_start,
                 p_enroll_end);
            SET v_section_id = LAST_INSERT_ID();
        ELSEIF p_capacity IS NOT NULL AND p_capacity > 0 THEN
            UPDATE section SET capacity = p_capacity WHERE section_id = v_section_id;
        END IF;

        INSERT INTO teaching (teacher_id, section_id) VALUES (p_teacher_id, v_section_id);
        COMMIT;
        SET p_section_id = v_section_id;
        SET p_success    = 1;
        SET p_message    = 'Successfully assigned to teach the section.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_auto_assign_grades
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_auto_assign_grades`;
delimiter ;;
CREATE PROCEDURE `sp_auto_assign_grades`(IN p_section_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_batch_import_exam
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_batch_import_exam`;
delimiter ;;
CREATE PROCEDURE `sp_batch_import_exam`(IN  p_teacher_id  INT UNSIGNED,
    IN  p_section_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_exam_date   DATE,
    IN  p_records     JSON,
    OUT p_saved       INT,
    OUT p_skipped     INT,
    OUT p_success     TINYINT,
    OUT p_message     VARCHAR(500))
BEGIN
    DECLARE v_teaches INT DEFAULT 0;
    DECLARE v_total   INT DEFAULT 0;

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
        INSERT INTO exam (teacher_id, student_id, section_id, exam_type, exam_date, score)
        SELECT p_teacher_id, st.user_id, p_section_id, p_exam_type, p_exam_date, jt.score
        FROM JSON_TABLE(
            p_records,
            '$[*]' COLUMNS (
                student_id  INT          PATH '$.student_id',
                student_no  VARCHAR(50)  PATH '$.student_no',
                score       DECIMAL(5,2) PATH '$.score'
            )
        ) AS jt
        JOIN student st ON (
            (jt.student_no IS NOT NULL AND jt.student_no <> '' AND st.student_no = jt.student_no)
            OR ((jt.student_no IS NULL OR jt.student_no = '')
                AND jt.student_id IS NOT NULL AND jt.student_id > 0
                AND st.user_id = jt.student_id)
        )
        WHERE jt.score BETWEEN 0 AND 100
          AND EXISTS (
              SELECT 1 FROM takes tk
              WHERE tk.student_id = st.user_id
                AND tk.section_id = p_section_id
          )
        ON DUPLICATE KEY UPDATE
            score     = VALUES(score),
            exam_date = VALUES(exam_date);

        SELECT COUNT(*) INTO p_saved
        FROM JSON_TABLE(
            p_records,
            '$[*]' COLUMNS (
                student_id  INT          PATH '$.student_id',
                student_no  VARCHAR(50)  PATH '$.student_no',
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
          );

        SET p_skipped = GREATEST(v_total - p_saved, 0);
        SET p_success = 1;
        SET p_message = CONCAT(p_saved, ' record(s) saved, ', p_skipped, ' skipped (not enrolled or invalid score).');
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_batch_record_attendance
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_batch_record_attendance`;
delimiter ;;
CREATE PROCEDURE `sp_batch_record_attendance`(IN  p_teacher_id  INT UNSIGNED,
    IN  p_schedule_id INT UNSIGNED,
    IN  p_week        TINYINT UNSIGNED,
    IN  p_records     JSON,
    OUT p_inserted    INT,
    OUT p_success     TINYINT,
    OUT p_message     VARCHAR(500))
BEGIN
    DECLARE v_section_id  INT UNSIGNED;
    DECLARE v_teaches     INT DEFAULT 0;
    DECLARE v_enrolled    INT DEFAULT 0;
    DECLARE v_idx         INT DEFAULT 0;
    DECLARE v_count       INT DEFAULT 0;
    DECLARE v_student_id  INT UNSIGNED;
    DECLARE v_status      VARCHAR(20);
    DECLARE v_note        VARCHAR(200);

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
            SET v_count    = JSON_LENGTH(p_records);
            SET v_idx      = 0;
            SET p_inserted = 0;

            WHILE v_idx < v_count DO
                SET v_student_id = JSON_EXTRACT(p_records, CONCAT('$[', v_idx, '].student_id'));
                SET v_status     = JSON_UNQUOTE(JSON_EXTRACT(p_records, CONCAT('$[', v_idx, '].status')));
                SET v_note       = JSON_UNQUOTE(JSON_EXTRACT(p_records, CONCAT('$[', v_idx, '].note')));

                IF v_status IS NULL OR v_status NOT IN ('present','absent','late','excused') THEN
                    SET v_status = 'present';
                END IF;

                SELECT COUNT(*) INTO v_enrolled FROM takes
                WHERE student_id = v_student_id AND section_id = v_section_id;

                IF v_enrolled > 0 THEN
                    INSERT INTO attendance (schedule_id, student_id, week, status, note, recorded_by)
                    VALUES (p_schedule_id, v_student_id, p_week, v_status, v_note, p_teacher_id)
                    ON DUPLICATE KEY UPDATE
                        status      = v_status,
                        note        = v_note,
                        recorded_by = p_teacher_id,
                        recorded_at = NOW();
                    SET p_inserted = p_inserted + 1;
                END IF;

                SET v_idx = v_idx + 1;
            END WHILE;

            SET p_success = 1;
            SET p_message = CONCAT(p_inserted, ' attendance record(s) saved.');
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_calc_student_stats
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_calc_student_stats`;
delimiter ;;
CREATE PROCEDURE `sp_calc_student_stats`(IN p_student_id INT)
BEGIN
  SELECT
    COALESCE((
      SELECT ROUND(
        SUM(credit * (
          CASE
            WHEN score < 60 THEN 0.0
            WHEN score < 64 THEN 1.0
            WHEN score < 67 THEN 1.3
            WHEN score < 70 THEN 1.7
            WHEN score < 74 THEN 2.0
            WHEN score < 77 THEN 2.3
            WHEN score < 80 THEN 2.7
            WHEN score < 84 THEN 3.0
            WHEN score < 87 THEN 3.3
            WHEN score < 90 THEN 3.7
            ELSE 4.0
          END
        )) / NULLIF(SUM(credit),0), 2)
      FROM v_student_exam_grades
      WHERE student_id = p_student_id
        AND score IS NOT NULL
        AND exam_type = 'final'   -- 【改动】只统计期末类型
    ), 0) AS gpa,

    COALESCE((
      SELECT COALESCE(SUM(sub.credit),0)
      FROM (
        SELECT section_id, MAX(credit) AS credit
        FROM v_student_exam_grades
        WHERE student_id = p_student_id
          AND exam_type = 'final'
          AND score >= 60
        GROUP BY section_id
      ) sub
    ), 0) AS credits,

    COALESCE((
      SELECT COUNT(DISTINCT section_id)
      FROM v_student_exam_grades
      WHERE student_id = p_student_id
        AND exam_type = 'final'
        AND score IS NOT NULL
    ), 0) AS published,

    COALESCE((
      SELECT COUNT(*)
      FROM v_student_exam_grades
      WHERE student_id = p_student_id
        AND score IS NOT NULL
    ), 0) AS exam_count;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_cancel_exam_event
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_cancel_exam_event`;
delimiter ;;
CREATE PROCEDURE `sp_cancel_exam_event`(IN  p_section_id  INT UNSIGNED,
    IN  p_teacher_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_exam_date   DATE,
    OUT p_ok          TINYINT,
    OUT p_msg         VARCHAR(200) CHARACTER SET utf8mb4)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_delete_exam
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_delete_exam`;
delimiter ;;
CREATE PROCEDURE `sp_delete_exam`(IN p_exam_id    INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED)
BEGIN
    DELETE FROM exam
    WHERE exam_id = p_exam_id AND teacher_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_delete_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_delete_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_delete_schedule`(IN p_schedule_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255))
BEGIN
    DECLARE v_count INT;

    SELECT COUNT(*) INTO v_count
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE s.schedule_id = p_schedule_id AND tg.teacher_id = p_teacher_id;

    IF v_count = 0 THEN
        SET p_success = FALSE;
        SET p_message = 'Not authorized or schedule not found';
    ELSE
        DELETE FROM schedule WHERE schedule_id = p_schedule_id;
        SET p_success = TRUE;
        SET p_message = 'Schedule deleted successfully';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_advisor_students
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_advisor_students`;
delimiter ;;
CREATE PROCEDURE `sp_get_advisor_students`(IN p_teacher_id INT UNSIGNED)
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
        RANK() OVER (ORDER BY fn_get_student_course_gpa(st.user_id) DESC) AS gpa_rank
    FROM advisor a
    JOIN student st ON st.user_id = a.student_id
    JOIN user u ON u.user_id = st.user_id
    JOIN department d ON d.dept_id = st.dept_id
    LEFT JOIN takes tk ON tk.student_id = st.user_id
    LEFT JOIN exam e ON e.student_id = st.user_id
    WHERE a.teacher_id = p_teacher_id
    GROUP BY st.user_id, u.name, u.email, u.phone,
             st.student_no, st.grade, st.enrollment_year, d.dept_name
    ORDER BY gpa DESC, student_name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_courses_to_apply
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_courses_to_apply`;
delimiter ;;
CREATE PROCEDURE `sp_get_courses_to_apply`(IN p_teacher_id INT UNSIGNED)
BEGIN
    DECLARE v_year YEAR DEFAULT YEAR(CURDATE());

    SELECT
        c.course_id,
        c.name,
        c.credit,
        c.hours,
        c.description,
        EXISTS (
            SELECT 1 FROM teaching tg
            JOIN section s ON s.section_id = tg.section_id
            WHERE tg.teacher_id = p_teacher_id
              AND s.course_id   = c.course_id
              AND s.year        = v_year
        ) AS already_teaching_now,
        (
            SELECT s2.section_id FROM section s2
            WHERE s2.course_id = c.course_id AND s2.year = v_year
            ORDER BY FIELD(s2.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_section_id,
        (
            SELECT s3.semester FROM section s3
            WHERE s3.course_id = c.course_id AND s3.year = v_year
            ORDER BY FIELD(s3.semester,'Fall','Spring')
            LIMIT 1
        ) AS existing_semester,
        (SELECT COUNT(*) FROM section s4 WHERE s4.course_id = c.course_id) AS total_sections
    FROM course c
    ORDER BY c.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_course_avg_by_student
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_course_avg_by_student`;
delimiter ;;
CREATE PROCEDURE `sp_get_course_avg_by_student`(IN p_student_id INT UNSIGNED)
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
        'enrolled' AS status,
        tk.enrolled_at
    FROM takes tk
    JOIN section sec ON tk.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    WHERE tk.student_id = p_student_id
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), c.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_course_avg_score
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_course_avg_score`;
delimiter ;;
CREATE PROCEDURE `sp_get_course_avg_score`(IN p_section_id INT UNSIGNED)
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
    LEFT JOIN takes tk ON sec.section_id = tk.section_id
    LEFT JOIN exam e ON tk.student_id = e.student_id
                     AND sec.section_id = e.section_id
                     AND e.exam_type = 'final'
    WHERE sec.section_id = p_section_id
    GROUP BY sec.section_id, c.course_id, c.name, sec.semester, sec.year, sec.capacity;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_dashboard_stats
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_dashboard_stats`;
delimiter ;;
CREATE PROCEDURE `sp_get_dashboard_stats`(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        COALESCE((SELECT COUNT(DISTINCT tg.section_id)
                  FROM teaching tg
                  WHERE tg.teacher_id = p_teacher_id), 0) AS total_sections,
        COALESCE((SELECT COUNT(DISTINCT tk.student_id)
                  FROM teaching tg
                  JOIN takes tk ON tk.section_id = tg.section_id
                  WHERE tg.teacher_id = p_teacher_id), 0) AS total_students,
        COALESCE((SELECT COUNT(DISTINCT e.exam_id)
                  FROM teaching tg
                  JOIN exam e ON e.section_id = tg.section_id AND e.teacher_id = p_teacher_id
                  WHERE tg.teacher_id = p_teacher_id), 0) AS total_exams,
        COALESCE((SELECT ROUND(AVG(e.score), 1)
                  FROM teaching tg
                  JOIN exam e ON e.section_id = tg.section_id AND e.teacher_id = p_teacher_id
                  WHERE tg.teacher_id = p_teacher_id), 0.0) AS overall_avg,
        COALESCE((SELECT COUNT(*)
                  FROM teaching tg
                  JOIN takes tk ON tk.section_id = tg.section_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND tk.grade IS NULL), 0) AS ungraded_count;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_exam_comparison
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_exam_comparison`;
delimiter ;;
CREATE PROCEDURE `sp_get_exam_comparison`(IN p_section_id INT UNSIGNED)
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
    WHERE tk.section_id = p_section_id
    ORDER BY u.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_exam_entry_students
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_exam_entry_students`;
delimiter ;;
CREATE PROCEDURE `sp_get_exam_entry_students`(IN p_section_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    IN p_exam_type VARCHAR(20),
    IN p_exam_date DATE)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.image,
        st.student_no,
        e.exam_id,
        e.score
    FROM takes tk
    JOIN student st ON st.user_id = tk.student_id
    JOIN user u ON u.user_id = st.user_id
    LEFT JOIN exam e ON e.student_id = st.user_id
                    AND e.section_id = p_section_id
                    AND e.teacher_id = p_teacher_id
                    AND e.exam_type = p_exam_type
                    AND e.exam_date = p_exam_date
    WHERE tk.section_id = p_section_id
    ORDER BY u.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_exam_events
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_exam_events`;
delimiter ;;
CREATE PROCEDURE `sp_get_exam_events`(IN p_teacher_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        e.exam_type,
        e.exam_date,
        (SELECT COUNT(*) FROM takes WHERE section_id = p_section_id) AS enrolled_count,
        COUNT(IF(e.score IS NOT NULL, 1, NULL)) AS scored_count
    FROM exam e
    WHERE e.teacher_id = p_teacher_id
      AND e.section_id = p_section_id
    GROUP BY e.exam_type, e.exam_date
    ORDER BY e.exam_date DESC, FIELD(e.exam_type, 'final', 'midterm', 'quiz');
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_exam_semesters
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_exam_semesters`;
delimiter ;;
CREATE PROCEDURE `sp_get_exam_semesters`(IN p_student_id INT)
BEGIN
  SELECT DISTINCT CONCAT(year,'-',semester) AS sem_key, year, semester
  FROM v_student_exam_grades
  WHERE student_id = p_student_id
  ORDER BY year DESC, semester ASC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_final_scores
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_final_scores`;
delimiter ;;
CREATE PROCEDURE `sp_get_final_scores`(IN p_section_id INT UNSIGNED)
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
        'enrolled' AS status,
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
    WHERE tk.section_id = p_section_id
    ORDER BY u.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_grade_distribution
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_grade_distribution`;
delimiter ;;
CREATE PROCEDURE `sp_get_grade_distribution`(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        tk.grade AS letter_grade,
        COUNT(*) AS count,
        ROUND(COUNT(*) * 100 / NULLIF((SELECT COUNT(*) FROM takes WHERE section_id = p_section_id), 0), 1) AS percentage
    FROM takes tk
    WHERE tk.section_id = p_section_id AND tk.grade IS NOT NULL
    GROUP BY tk.grade
    ORDER BY
        CASE tk.grade
            WHEN 'A'  THEN 1  WHEN 'A-' THEN 2  WHEN 'B+' THEN 3  WHEN 'B'  THEN 4
            WHEN 'B-' THEN 5  WHEN 'C+' THEN 6  WHEN 'C'  THEN 7  WHEN 'C-' THEN 8
            WHEN 'D'  THEN 9  WHEN 'F'  THEN 10 ELSE 11 END;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_pending_exams
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_pending_exams`;
delimiter ;;
CREATE PROCEDURE `sp_get_pending_exams`(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        e.section_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        e.exam_type,
        e.exam_date,
        (SELECT COUNT(*) FROM takes t WHERE t.section_id = e.section_id) AS enrolled_count,
        COUNT(IF(e.score IS NOT NULL, 1, NULL)) AS scored_count
    FROM exam e
    JOIN section sec ON sec.section_id = e.section_id
    JOIN course c ON c.course_id = sec.course_id
    WHERE e.teacher_id = p_teacher_id
    GROUP BY e.section_id, c.name, sec.semester, sec.year, e.exam_type, e.exam_date
    HAVING SUM(IF(e.score IS NULL, 1, 0)) > 0
    ORDER BY e.exam_date DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_get_schedule`(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        TIMEDIFF(s.end_time, s.start_time) AS duration,
        CONCAT(
            CASE s.day_of_week
                WHEN 1 THEN 'Monday'
                WHEN 2 THEN 'Tuesday'
                WHEN 3 THEN 'Wednesday'
                WHEN 4 THEN 'Thursday'
                WHEN 5 THEN 'Friday'
                WHEN 6 THEN 'Saturday'
                WHEN 7 THEN 'Sunday'
            END,
            ' ',
            DATE_FORMAT(s.start_time, '%H:%i'),
            '-',
            DATE_FORMAT(s.end_time, '%H:%i')
        ) AS schedule_display
    FROM schedule s
    JOIN section sec ON s.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    WHERE s.section_id = p_section_id
    ORDER BY s.day_of_week, s.start_time;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_schedule_attendance
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_schedule_attendance`;
delimiter ;;
CREATE PROCEDURE `sp_get_schedule_attendance`(IN p_schedule_id INT UNSIGNED,
    IN p_week TINYINT UNSIGNED)
BEGIN
    SELECT
        u.user_id AS student_id,
        u.name AS student_name,
        u.image,
        st.student_no,
        d.dept_name,
        COALESCE(a.status, 'not_recorded') AS status,
        a.note,
        a.recorded_at
    FROM takes tk
    JOIN student st ON st.user_id = tk.student_id
    JOIN user u ON u.user_id = tk.student_id
    JOIN department d ON d.dept_id = st.dept_id
    JOIN schedule sc ON sc.schedule_id = p_schedule_id
    LEFT JOIN attendance a
           ON a.schedule_id = p_schedule_id
          AND a.student_id = tk.student_id
          AND a.week = p_week
    WHERE tk.section_id = sc.section_id
    ORDER BY u.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_section_attendance_report
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_section_attendance_report`;
delimiter ;;
CREATE PROCEDURE `sp_get_section_attendance_report`(IN p_section_id INT UNSIGNED,
    IN p_warn_threshold DECIMAL(5,2))
BEGIN
    SET p_warn_threshold = IFNULL(p_warn_threshold, 75.00);

    WITH enrolled AS (
        SELECT
            u.user_id AS student_id,
            u.name AS student_name,
            st.student_no,
            d.dept_name
        FROM takes tk
        JOIN student st ON st.user_id = tk.student_id
        JOIN user u ON u.user_id = tk.student_id
        JOIN department d ON d.dept_id = st.dept_id
        WHERE tk.section_id = p_section_id
    ),
    attendance_stats AS (
        SELECT
            a.student_id,
            COUNT(*) AS total_recorded,
            SUM(CASE WHEN a.status IN ('present','late') THEN 1 ELSE 0 END) AS attended,
            SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN a.status = 'excused' THEN 1 ELSE 0 END) AS excused_count
        FROM attendance a
        JOIN schedule s ON s.schedule_id = a.schedule_id
        WHERE s.section_id = p_section_id
        GROUP BY a.student_id
    )
    SELECT
        x.student_id,
        x.student_name,
        x.student_no,
        x.dept_name,
        x.total_recorded,
        x.attended,
        x.absent_count,
        x.late_count,
        x.excused_count,
        x.attendance_rate_pct,
        CASE
            WHEN x.attendance_rate_pct IS NULL THEN 'no_data'
            WHEN x.attendance_rate_pct < p_warn_threshold THEN 'warning'
            ELSE 'ok'
        END AS attendance_flag
    FROM (
        SELECT
            e.student_id,
            e.student_name,
            e.student_no,
            e.dept_name,
            COALESCE(a.total_recorded, 0) AS total_recorded,
            COALESCE(a.attended, 0) AS attended,
            COALESCE(a.absent_count, 0) AS absent_count,
            COALESCE(a.late_count, 0) AS late_count,
            COALESCE(a.excused_count, 0) AS excused_count,
            fn_student_attendance_rate(e.student_id, p_section_id) AS attendance_rate_pct
        FROM enrolled e
        LEFT JOIN attendance_stats a ON a.student_id = e.student_id
    ) x
    ORDER BY (x.attendance_rate_pct IS NULL), x.attendance_rate_pct ASC, x.student_name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_section_exams
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_section_exams`;
delimiter ;;
CREATE PROCEDURE `sp_get_section_exams`(IN p_section_id INT UNSIGNED)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_section_students
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_section_students`;
delimiter ;;
CREATE PROCEDURE `sp_get_section_students`(IN p_section_id INT UNSIGNED)
BEGIN
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.image,
        st.student_no,
        st.grade AS student_grade_year,
        st.enrollment_year,
        d.dept_name,
        tk.grade AS letter_grade,
        'enrolled' AS status,
        tk.enrolled_at,
        fn_student_section_avg(u.user_id, p_section_id) AS weighted_avg,
        fn_score_to_grade(fn_student_section_avg(u.user_id, p_section_id)) AS suggested_grade,
        (SELECT score FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'final' ORDER BY exam_date DESC LIMIT 1) AS final_score,
        (SELECT score FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'midterm' ORDER BY exam_date DESC LIMIT 1) AS midterm_score,
        (SELECT AVG(score) FROM exam WHERE student_id = u.user_id AND section_id = p_section_id AND exam_type = 'quiz') AS quiz_avg
    FROM takes tk
    JOIN student st ON tk.student_id = st.user_id
    JOIN user u ON st.user_id = u.user_id
    JOIN department d ON st.dept_id = d.dept_id
    WHERE tk.section_id = p_section_id
    ORDER BY u.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_student_attendance_summary
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_student_attendance_summary`;
delimiter ;;
CREATE PROCEDURE `sp_get_student_attendance_summary`(IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_student_exams
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_student_exams`;
delimiter ;;
CREATE PROCEDURE `sp_get_student_exams`(IN p_student_id INT,
  IN p_type VARCHAR(20),
  IN p_year INT,
  IN p_semester VARCHAR(20))
BEGIN
  SELECT
    exam_id, exam_date, exam_type, score,
    course_name, credit,
    semester, year,
    teacher_name, teacher_title
  FROM v_student_exam_grades
  WHERE student_id = p_student_id
    AND score IS NOT NULL
    AND (p_type IS NULL OR p_type = '' OR exam_type = p_type)
    AND (p_year IS NULL OR p_year = 0 OR year = p_year)
    AND (p_semester IS NULL OR p_semester = '' OR semester = p_semester)
  ORDER BY exam_date DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_student_final_score
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_student_final_score`;
delimiter ;;
CREATE PROCEDURE `sp_get_student_final_score`(IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_info
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_info`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_info`(IN p_teacher_id INT UNSIGNED)
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
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_schedule`(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        s.start_time,
        s.end_time,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name AS course_name,
        sec.semester,
        sec.year,
        u.name AS teacher_name,
        COUNT(DISTINCT tk.student_id) AS enrolled_count,
        CASE s.day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END AS day_name
    FROM teaching tg
    JOIN schedule s ON tg.section_id = s.section_id
    JOIN section sec ON s.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    JOIN user u ON tg.teacher_id = u.user_id
    LEFT JOIN takes tk ON s.section_id = tk.section_id
    WHERE tg.teacher_id = p_teacher_id
    GROUP BY s.schedule_id, s.section_id, s.day_of_week, s.start_time,
             s.end_time, s.classroom_id, cl.building, cl.room_number, s.week_start, s.week_end,
             c.course_id, c.name, sec.semester, sec.year, u.name
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), s.day_of_week, s.start_time;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_sections
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_sections`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_sections`(IN p_teacher_id INT UNSIGNED)
BEGIN
    WITH take_stats AS (
        SELECT
            section_id,
            COUNT(DISTINCT student_id) AS enrolled_count,
            SUM(CASE WHEN grade IS NULL THEN 1 ELSE 0 END) AS ungraded_count
        FROM takes
        GROUP BY section_id
    ),
    exam_stats AS (
        SELECT
            section_id,
            COUNT(DISTINCT exam_id) AS exam_count,
            ROUND(AVG(score), 2) AS section_avg_score
        FROM exam
        WHERE teacher_id = p_teacher_id
        GROUP BY section_id
    )
    SELECT
        s.section_id,
        s.semester,
        s.year,
        c.course_id,
        c.name AS course_name,
        c.credit,
        c.hours,
        s.capacity,
        c.description,
        COALESCE(ts.enrolled_count, 0) AS enrolled_count,
        COALESCE(es.exam_count, 0) AS exam_count,
        es.section_avg_score,
        COALESCE(ts.ungraded_count, 0) AS ungraded_count
    FROM teaching tg
    JOIN section s ON tg.section_id = s.section_id
    JOIN course c ON s.course_id = c.course_id
    LEFT JOIN take_stats ts ON ts.section_id = s.section_id
    LEFT JOIN exam_stats es ON es.section_id = s.section_id
    WHERE tg.teacher_id = p_teacher_id
    ORDER BY s.year DESC, FIELD(s.semester, 'Fall', 'Spring');
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_semesters
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_semesters`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_semesters`(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT DISTINCT sec.year, sec.semester
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    WHERE tg.teacher_id = p_teacher_id
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring');
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_weekly_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_weekly_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_weekly_schedule`(IN p_teacher_id INT UNSIGNED,
    IN p_week INT UNSIGNED)
BEGIN
    SELECT
        s.schedule_id,
        s.section_id,
        s.day_of_week,
        TIME_FORMAT(s.start_time, '%H:%i') AS start_time,
        TIME_FORMAT(s.end_time, '%H:%i') AS end_time,
        TIME_TO_SEC(s.start_time) / 60 AS start_minutes,
        TIME_TO_SEC(s.end_time) / 60 AS end_minutes,
        s.classroom_id,
        CONCAT_WS(' ', cl.building, cl.room_number) AS location,
        s.week_start,
        s.week_end,
        c.course_id,
        c.name AS course_name,
        c.credit,
        c.hours,
        c.description,
        sec.semester,
        sec.year,
        COUNT(DISTINCT tk.student_id) AS enrolled_count,
        sec.capacity,
        CASE s.day_of_week
            WHEN 1 THEN 'Monday'
            WHEN 2 THEN 'Tuesday'
            WHEN 3 THEN 'Wednesday'
            WHEN 4 THEN 'Thursday'
            WHEN 5 THEN 'Friday'
            WHEN 6 THEN 'Saturday'
            WHEN 7 THEN 'Sunday'
        END AS day_name
    FROM teaching tg
    JOIN schedule s ON tg.section_id = s.section_id
    LEFT JOIN classroom cl ON s.classroom_id = cl.classroom_id
    JOIN section sec ON s.section_id = sec.section_id
    JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN takes tk ON s.section_id = tk.section_id
    WHERE tg.teacher_id = p_teacher_id
      AND s.week_start <= p_week
      AND s.week_end >= p_week
    GROUP BY
        s.schedule_id, s.section_id, s.day_of_week, s.start_time, s.end_time,
        s.classroom_id, cl.building, cl.room_number, s.week_start, s.week_end,
        c.course_id, c.name, c.credit, c.hours, c.description,
        sec.semester, sec.year, sec.capacity
    ORDER BY s.day_of_week, s.start_time;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_teacher_week_range
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_teacher_week_range`;
delimiter ;;
CREATE PROCEDURE `sp_get_teacher_week_range`(IN p_teacher_id INT UNSIGNED)
BEGIN
    SELECT
        COALESCE(MIN(s.week_start), 1)  AS min_week,
        COALESCE(MAX(s.week_end),   16) AS max_week
    FROM teaching tg
    JOIN schedule s ON tg.section_id = s.section_id
    WHERE tg.teacher_id = p_teacher_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_workload_by_section
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_workload_by_section`;
delimiter ;;
CREATE PROCEDURE `sp_get_workload_by_section`(IN p_teacher_id INT UNSIGNED,
    IN p_semester VARCHAR(20),
    IN p_year INT)
BEGIN
    WITH take_stats AS (
        SELECT
            section_id,
            COUNT(DISTINCT student_id) AS student_count,
            SUM(CASE WHEN grade IS NOT NULL THEN 1 ELSE 0 END) AS graded_count
        FROM takes
        GROUP BY section_id
    ),
    exam_stats AS (
        SELECT
            section_id,
            COUNT(DISTINCT exam_id) AS exam_count,
            ROUND(AVG(score), 1) AS avg_score
        FROM exam
        WHERE teacher_id = p_teacher_id
        GROUP BY section_id
    )
    SELECT
        sec.section_id,
        c.name AS course_name,
        c.credit,
        c.hours AS course_hours,
        sec.semester,
        sec.year,
        COALESCE(ts.student_count, 0) AS student_count,
        COALESCE(es.exam_count, 0) AS exam_count,
        es.avg_score,
        COALESCE(ts.graded_count, 0) AS graded_count,
        ROUND(COALESCE(ts.graded_count, 0) * 100.0 / NULLIF(COALESCE(ts.student_count, 0), 0), 1) AS grade_completion_pct,
        (SELECT COUNT(*) FROM schedule sch WHERE sch.section_id = sec.section_id) AS schedule_slots,
        (SELECT COALESCE(SUM((TIME_TO_SEC(sch2.end_time) - TIME_TO_SEC(sch2.start_time)) / 60), 0)
         FROM schedule sch2
         WHERE sch2.section_id = sec.section_id) AS weekly_minutes
    FROM teaching tg
    JOIN section sec ON sec.section_id = tg.section_id
    JOIN course c ON c.course_id = sec.course_id
    LEFT JOIN take_stats ts ON ts.section_id = sec.section_id
    LEFT JOIN exam_stats es ON es.section_id = sec.section_id
    WHERE tg.teacher_id = p_teacher_id
      AND (p_semester = '' OR sec.semester = p_semester)
      AND (p_year = 0 OR sec.year = p_year)
    ORDER BY sec.year DESC, FIELD(sec.semester, 'Fall', 'Spring'), c.name;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_get_workload_summary
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_get_workload_summary`;
delimiter ;;
CREATE PROCEDURE `sp_get_workload_summary`(IN p_teacher_id INT UNSIGNED,
    IN p_semester VARCHAR(20),
    IN p_year INT)
BEGIN
    SELECT
        COALESCE((SELECT COUNT(DISTINCT sec.section_id)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0) AS total_sections,
        COALESCE((SELECT COUNT(DISTINCT tk.student_id)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  JOIN takes tk ON tk.section_id = sec.section_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0) AS total_students,
        COALESCE((SELECT COUNT(DISTINCT e.exam_id)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  JOIN exam e ON e.section_id = sec.section_id AND e.teacher_id = p_teacher_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0) AS total_exams,
        COALESCE((SELECT ROUND(AVG(e.score), 1)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  JOIN exam e ON e.section_id = sec.section_id AND e.teacher_id = p_teacher_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0.0) AS overall_avg_score,
        COALESCE((SELECT COUNT(*)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  JOIN takes tk ON tk.section_id = sec.section_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND tk.grade IS NOT NULL
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0) AS graded_students,
        COALESCE((SELECT COUNT(*)
                  FROM teaching tg
                  JOIN section sec ON sec.section_id = tg.section_id
                  JOIN takes tk ON tk.section_id = sec.section_id
                  WHERE tg.teacher_id = p_teacher_id
                    AND tk.grade IS NULL
                    AND (p_semester = '' OR sec.semester = p_semester)
                    AND (p_year = 0 OR sec.year = p_year)), 0) AS ungraded_students,
        COALESCE((SELECT SUM(c2.credit)
                  FROM teaching tg2
                  JOIN section s2 ON s2.section_id = tg2.section_id
                  JOIN course c2 ON c2.course_id = s2.course_id
                  WHERE tg2.teacher_id = p_teacher_id
                    AND (p_semester = '' OR s2.semester = p_semester)
                    AND (p_year = 0 OR s2.year = p_year)), 0) AS total_credit_load,
        COALESCE((SELECT SUM((TIME_TO_SEC(sch.end_time) - TIME_TO_SEC(sch.start_time)) / 60)
                  FROM teaching tg3
                  JOIN section s3 ON s3.section_id = tg3.section_id
                  JOIN schedule sch ON sch.section_id = tg3.section_id
                  WHERE tg3.teacher_id = p_teacher_id
                    AND (p_semester = '' OR s3.semester = p_semester)
                    AND (p_year = 0 OR s3.year = p_year)), 0) AS total_weekly_minutes;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_author_announcements
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_author_announcements`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_author_announcements`(IN p_author_user_id INT UNSIGNED)
BEGIN
    SELECT
        a.announcement_id,
        a.author_user_id,
        a.title,
        a.content,
        a.status,
        a.is_pinned,
        a.published_at,
        a.created_at,
        a.updated_at,
        u.name AS teacher_name,
        1 AS is_author,
        GROUP_CONCAT(CONCAT(at.target_type, ':', at.target_id) SEPARATOR ',') AS targets
    FROM announcement a
    JOIN user u ON u.user_id = a.author_user_id
    LEFT JOIN announcement_target at ON at.announcement_id = a.announcement_id
    WHERE a.author_user_id = p_author_user_id
    GROUP BY a.announcement_id, u.name, a.author_user_id
    ORDER BY a.created_at DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_available_sections
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_available_sections`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_available_sections`(IN p_student_id INT UNSIGNED,
    IN p_year INT,
    IN p_semester VARCHAR(20),
    IN p_total_weeks INT)
BEGIN
    SELECT
        x.section_id,
        x.course_id,
        x.course_name,
        x.credit,
        x.teacher_name,
        x.capacity,
        x.enrollment_start,
        x.enrollment_end,
        x.is_open,
        x.enrolled_count,
        x.is_my_course,
        x.is_same_course_selected,
        x.restriction_count,
        x.allowed_restriction_count,
        CASE WHEN x.restriction_count > 0 THEN 1 ELSE 0 END AS is_restricted,
        CASE WHEN x.restriction_count = 0 OR x.allowed_restriction_count > 0 THEN 1 ELSE 0 END AS is_major_allowed,
        x.conflict_flag,
        CASE
            WHEN x.is_my_course > 0 OR x.is_same_course_selected > 0 THEN 0
            WHEN x.restriction_count > 0 AND x.allowed_restriction_count = 0 THEN 0
            WHEN x.is_open <> 1 THEN 0
            WHEN x.capacity <= 0 OR x.enrolled_count >= x.capacity THEN 0
            WHEN x.conflict_flag > 0 THEN 0
            ELSE 1
        END AS can_enroll,
        CASE
            WHEN x.is_my_course > 0 OR x.is_same_course_selected > 0 THEN '已选此课'
            WHEN x.restriction_count > 0 AND x.allowed_restriction_count = 0 THEN '专业限制'
            WHEN x.is_open <> 1 THEN '不在选课时间'
            WHEN x.capacity <= 0 OR x.enrolled_count >= x.capacity THEN '名额已满'
            WHEN x.conflict_flag > 0 THEN '时间冲突'
            ELSE ''
        END AS disabled_reason
    FROM (
        SELECT
            sec.section_id,
            sec.course_id,
            c.name AS course_name,
            c.credit,
            COALESCE(tea.teacher_names, '待定') AS teacher_name,
            sec.capacity,
            sec.enrollment_start,
            sec.enrollment_end,
            (sec.enrollment_start IS NOT NULL
             AND sec.enrollment_end IS NOT NULL
             AND NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end) AS is_open,
            (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id) AS enrolled_count,
            (SELECT COUNT(*) FROM takes WHERE section_id = sec.section_id AND student_id = p_student_id) AS is_my_course,
            (SELECT COUNT(*)
             FROM takes tk
             JOIN section old_sec ON old_sec.section_id = tk.section_id
             WHERE tk.student_id = p_student_id
               AND old_sec.year = sec.year
               AND old_sec.semester = sec.semester
               AND old_sec.course_id = sec.course_id) AS is_same_course_selected,
            (SELECT COUNT(*) FROM restriction r WHERE r.section_id = sec.section_id) AS restriction_count,
            (SELECT COUNT(*)
             FROM restriction r
             JOIN student s ON s.major_id = r.major_id AND s.user_id = p_student_id
             WHERE r.section_id = sec.section_id) AS allowed_restriction_count,
            (SELECT COUNT(*)
             FROM schedule sch_new
             JOIN takes tk ON tk.student_id = p_student_id
             JOIN section old_sec
               ON old_sec.section_id = tk.section_id
              AND old_sec.year = sec.year
              AND old_sec.semester = sec.semester
              AND old_sec.section_id <> sec.section_id
             JOIN schedule sch_old ON sch_old.section_id = old_sec.section_id
             WHERE sch_new.section_id = sec.section_id
               AND sch_new.day_of_week = sch_old.day_of_week
               AND COALESCE(sch_new.week_start, 1) <= COALESCE(sch_old.week_end, p_total_weeks)
               AND COALESCE(sch_new.week_end, p_total_weeks) >= COALESCE(sch_old.week_start, 1)
               AND sch_new.start_time < sch_old.end_time
               AND sch_new.end_time > sch_old.start_time
             LIMIT 1) AS conflict_flag
        FROM section sec
        JOIN course c ON sec.course_id = c.course_id
        LEFT JOIN (
            SELECT tg.section_id,
                   GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR '、') AS teacher_names
            FROM teaching tg
            JOIN user u ON u.user_id = tg.teacher_id
            GROUP BY tg.section_id
        ) tea ON tea.section_id = sec.section_id
        WHERE sec.year = p_year
          AND sec.semester = p_semester
    ) x
    ORDER BY x.course_id ASC, x.section_id ASC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_student_enroll_section
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_student_enroll_section`;
delimiter ;;
CREATE PROCEDURE `sp_project_student_enroll_section`(IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED,
    IN p_year INT,
    IN p_semester VARCHAR(20),
    IN p_total_weeks INT)
BEGIN
    DECLARE v_section_exists INT DEFAULT 0;
    DECLARE v_student_exists INT DEFAULT 0;
    DECLARE v_sec_year INT DEFAULT NULL;
    DECLARE v_sec_semester VARCHAR(20) DEFAULT NULL;
    DECLARE v_course_id INT UNSIGNED DEFAULT NULL;
    DECLARE v_capacity INT DEFAULT 0;
    DECLARE v_is_open INT DEFAULT 0;
    DECLARE v_student_major INT UNSIGNED DEFAULT NULL;
    DECLARE v_same_course_count INT DEFAULT 0;
    DECLARE v_enrolled_count INT DEFAULT 0;
    DECLARE v_restriction_count INT DEFAULT 0;
    DECLARE v_allowed_count INT DEFAULT 0;
    DECLARE v_conflict_count INT DEFAULT 0;
    DECLARE v_conflict_course_name VARCHAR(255) DEFAULT NULL;
    DECLARE v_conflict_day INT DEFAULT NULL;
    DECLARE v_new_start_time TIME DEFAULT NULL;
    DECLARE v_new_end_time TIME DEFAULT NULL;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 0 AS ok, '选课失败：数据库异常' AS message;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_student_exists
    FROM student
    WHERE user_id = p_student_id;

    IF v_student_exists = 0 THEN
        ROLLBACK;
        SELECT 0 AS ok, '选课失败：未找到学生信息' AS message;
    ELSE
        SELECT major_id INTO v_student_major
        FROM student
        WHERE user_id = p_student_id
        LIMIT 1;

        SELECT COUNT(*) INTO v_section_exists
        FROM section
        WHERE section_id = p_section_id;

        IF v_section_exists = 0 THEN
            ROLLBACK;
            SELECT 0 AS ok, '选课失败：未找到开课节信息' AS message;
        ELSE
            SELECT
                sec.year,
                sec.semester,
                sec.course_id,
                sec.capacity,
                (sec.enrollment_start IS NOT NULL
                 AND sec.enrollment_end IS NOT NULL
                 AND NOW() BETWEEN sec.enrollment_start AND sec.enrollment_end)
            INTO v_sec_year, v_sec_semester, v_course_id, v_capacity, v_is_open
            FROM section sec
            WHERE sec.section_id = p_section_id
            FOR UPDATE;

            IF v_sec_year <> p_year OR v_sec_semester <> p_semester THEN
                ROLLBACK;
                SELECT 0 AS ok, '选课失败：仅允许选择当前学期开课' AS message;
            ELSEIF v_is_open <> 1 THEN
                ROLLBACK;
                SELECT 0 AS ok, '选课失败：当前不在选课时间范围内' AS message;
            ELSE
                SELECT COUNT(*) INTO v_same_course_count
                FROM takes tk
                JOIN section old_sec ON old_sec.section_id = tk.section_id
                WHERE tk.student_id = p_student_id
                  AND old_sec.year = p_year
                  AND old_sec.semester = p_semester
                  AND old_sec.course_id = v_course_id;

                IF v_same_course_count > 0 THEN
                    ROLLBACK;
                    SELECT 0 AS ok, '选课失败：已经选修过该课程' AS message;
                ELSE
                    SELECT COUNT(*) INTO v_enrolled_count
                    FROM takes
                    WHERE section_id = p_section_id;

                    IF v_capacity <= 0 OR v_enrolled_count >= v_capacity THEN
                        ROLLBACK;
                        SELECT 0 AS ok, '选课失败：该开课节容量已满' AS message;
                    ELSE
                        SELECT COUNT(*), COALESCE(SUM(CASE WHEN major_id = v_student_major THEN 1 ELSE 0 END), 0)
                        INTO v_restriction_count, v_allowed_count
                        FROM restriction
                        WHERE section_id = p_section_id;

                        IF v_restriction_count > 0 AND v_allowed_count = 0 THEN
                            ROLLBACK;
                            SELECT 0 AS ok, '选课失败：你的所属专业不在该课程允许的限选专业范围内！' AS message;
                        ELSE
                            SELECT COUNT(*) INTO v_conflict_count
                            FROM schedule sch_new
                            JOIN takes tk ON tk.student_id = p_student_id
                            JOIN section old_sec
                              ON old_sec.section_id = tk.section_id
                             AND old_sec.year = p_year
                             AND old_sec.semester = p_semester
                             AND old_sec.section_id <> p_section_id
                            JOIN schedule sch_old ON sch_old.section_id = old_sec.section_id
                            WHERE sch_new.section_id = p_section_id
                              AND sch_new.day_of_week = sch_old.day_of_week
                              AND COALESCE(sch_new.week_start, 1) <= COALESCE(sch_old.week_end, p_total_weeks)
                              AND COALESCE(sch_new.week_end, p_total_weeks) >= COALESCE(sch_old.week_start, 1)
                              AND sch_new.start_time < sch_old.end_time
                              AND sch_new.end_time > sch_old.start_time;

                            IF v_conflict_count > 0 THEN
                                SELECT c_old.name, sch_new.day_of_week, sch_new.start_time, sch_new.end_time
                                INTO v_conflict_course_name, v_conflict_day, v_new_start_time, v_new_end_time
                                FROM schedule sch_new
                                JOIN takes tk ON tk.student_id = p_student_id
                                JOIN section old_sec
                                  ON old_sec.section_id = tk.section_id
                                 AND old_sec.year = p_year
                                 AND old_sec.semester = p_semester
                                 AND old_sec.section_id <> p_section_id
                                JOIN schedule sch_old ON sch_old.section_id = old_sec.section_id
                                JOIN course c_old ON c_old.course_id = old_sec.course_id
                                WHERE sch_new.section_id = p_section_id
                                  AND sch_new.day_of_week = sch_old.day_of_week
                                  AND COALESCE(sch_new.week_start, 1) <= COALESCE(sch_old.week_end, p_total_weeks)
                                  AND COALESCE(sch_new.week_end, p_total_weeks) >= COALESCE(sch_old.week_start, 1)
                                  AND sch_new.start_time < sch_old.end_time
                                  AND sch_new.end_time > sch_old.start_time
                                LIMIT 1;

                                ROLLBACK;
                                SELECT 0 AS ok,
                                       CONCAT('选课失败：与已选课程时间冲突（',
                                              v_conflict_course_name,
                                              '，周',
                                              v_conflict_day,
                                              ' ',
                                              TIME_FORMAT(v_new_start_time, '%H:%i'),
                                              '-',
                                              TIME_FORMAT(v_new_end_time, '%H:%i'),
                                              '）') AS message;
                            ELSE
                                INSERT INTO takes (student_id, section_id, enrolled_at)
                                VALUES (p_student_id, p_section_id, NOW());

                                COMMIT;
                                SELECT 1 AS ok, '选课成功' AS message;
                            END IF;
                        END IF;
                    END IF;
                END IF;
            END IF;
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_student_drop_section
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_student_drop_section`;
delimiter ;;
CREATE PROCEDURE `sp_project_student_drop_section`(IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED,
    IN p_year INT,
    IN p_semester VARCHAR(20))
BEGIN
    DECLARE v_section_exists INT DEFAULT 0;
    DECLARE v_is_open INT DEFAULT 0;
    DECLARE v_deleted INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SELECT 0 AS ok, '退选异常：数据库异常' AS message;
    END;

    START TRANSACTION;

    SELECT COUNT(*) INTO v_section_exists
    FROM section
    WHERE section_id = p_section_id;

    IF v_section_exists = 0 THEN
        ROLLBACK;
        SELECT 0 AS ok, '退选失败：未找到开课节信息' AS message;
    ELSE
        SELECT (enrollment_start IS NOT NULL
                AND enrollment_end IS NOT NULL
                AND NOW() BETWEEN enrollment_start AND enrollment_end)
        INTO v_is_open
        FROM section
        WHERE section_id = p_section_id
        FOR UPDATE;

        IF v_is_open <> 1 THEN
            ROLLBACK;
            SELECT 0 AS ok, '不在退选时间范围内，禁止退选' AS message;
        ELSE
            DELETE t
            FROM takes t
            JOIN section sec ON sec.section_id = t.section_id
            WHERE t.student_id = p_student_id
              AND t.section_id = p_section_id
              AND sec.year = p_year
              AND sec.semester = p_semester;

            SET v_deleted = ROW_COUNT();

            IF v_deleted > 0 THEN
                COMMIT;
                SELECT 1 AS ok, '退选成功' AS message;
            ELSE
                ROLLBACK;
                SELECT 0 AS ok, '未找到您可以退选的该课程记录，或不在允许的学期内。' AS message;
            END IF;
        END IF;
    END IF;
END
;;
delimiter ;
-- ----------------------------
-- Procedure structure for sp_project_get_classrooms
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_classrooms`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_classrooms`(IN p_classroom_id INT UNSIGNED)
BEGIN
    SELECT classroom_id, building, room_number, capacity, type
    FROM classroom
    WHERE p_classroom_id = 0 OR classroom_id = p_classroom_id
    ORDER BY building, room_number;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_classroom_conflicts
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_classroom_conflicts`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_classroom_conflicts`(IN p_week INT,
    IN p_day_of_week INT,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_year INT,
    IN p_semester VARCHAR(20),
    IN p_total_weeks INT,
    IN p_classroom_id INT UNSIGNED)
BEGIN
    SELECT
        classroom_id,
        schedule_id,
        start_time,
        end_time,
        COALESCE(week_start, 1) AS week_start,
        COALESCE(week_end, p_total_weeks) AS week_end,
        course_name,
        COALESCE(teacher_names, '未安排教师') AS teacher_names
    FROM v_project_schedule_overview
    WHERE day_of_week = p_day_of_week
      AND year = p_year
      AND semester = p_semester
      AND p_start_time < end_time
      AND p_end_time > start_time
      AND p_week BETWEEN COALESCE(week_start, 1) AND COALESCE(week_end, p_total_weeks)
      AND (p_classroom_id = 0 OR classroom_id = p_classroom_id)
    ORDER BY classroom_id, start_time, schedule_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_section_announcements
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_section_announcements`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_section_announcements`(IN p_section_id INT UNSIGNED,
    IN p_current_user_id INT UNSIGNED)
BEGIN
    SELECT
        a.announcement_id,
        a.author_user_id,
        u.name AS teacher_name,
        a.title,
        a.content,
        a.is_pinned,
        a.published_at,
        a.created_at,
        a.updated_at,
        (a.author_user_id = p_current_user_id) AS is_author
    FROM announcement a
    JOIN announcement_target at ON at.announcement_id = a.announcement_id
    JOIN user u ON u.user_id = a.author_user_id
    WHERE at.target_type = 'section'
      AND at.target_id = p_section_id
      AND a.status = 'published'
    ORDER BY a.is_pinned DESC, a.published_at DESC, a.created_at DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_base_info
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_base_info`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_base_info`(IN p_student_id INT UNSIGNED)
BEGIN
    SELECT
        u.*,
        s.student_no,
        s.grade,
        s.enrollment_year,
        s.dept_id,
        s.major_id,
        d.dept_name,
        m.major_name
    FROM user u
    JOIN student s ON s.user_id = u.user_id
    JOIN department d ON d.dept_id = s.dept_id
    LEFT JOIN major m ON m.major_id = s.major_id
    WHERE u.user_id = p_student_id
    LIMIT 1;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_current_courses
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_current_courses`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_current_courses`(IN p_student_id INT UNSIGNED,
    IN p_year INT,
    IN p_semester VARCHAR(20))
BEGIN
    SELECT
        v.section_id,
        v.course_name,
        v.credit,
        v.primary_teacher_name AS teacher_name,
        v.enrollment_start,
        v.enrollment_end,
        (NOW() BETWEEN v.enrollment_start AND v.enrollment_end) AS is_open
    FROM takes t
    JOIN v_project_section_overview v ON v.section_id = t.section_id
    WHERE t.student_id = p_student_id
      AND v.year = p_year
      AND v.semester = p_semester
    ORDER BY t.enrolled_at DESC;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_current_schedules
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_current_schedules`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_current_schedules`(IN p_student_id INT UNSIGNED,
    IN p_year INT,
    IN p_semester VARCHAR(20))
BEGIN
    SELECT
        section_id,
        day_of_week,
        start_time,
        end_time,
        location,
        COALESCE(week_start, 1) AS week_start,
        COALESCE(week_end, 16) AS week_end
    FROM v_project_student_schedule_overview
    WHERE student_id = p_student_id
      AND year = p_year
      AND semester = p_semester
    ORDER BY section_id, day_of_week, start_time, schedule_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_portal_summary
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_portal_summary`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_portal_summary`(IN p_student_id INT UNSIGNED,
    IN p_year_min INT,
    IN p_recent_limit INT)
BEGIN
    SELECT COUNT(*) AS enrolled_count
    FROM takes
    WHERE student_id = p_student_id;

    SELECT
        c.name AS course,
        c.credit,
        CONCAT(sec.year, '-', sec.semester) AS semester,
        sec.year AS sec_year,
        MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) AS final_score
    FROM takes t
    JOIN section sec ON sec.section_id = t.section_id
    JOIN course c ON c.course_id = sec.course_id
    LEFT JOIN exam e ON e.student_id = t.student_id
                    AND e.section_id = t.section_id
    WHERE t.student_id = p_student_id
      AND sec.year >= p_year_min
    GROUP BY t.section_id, c.name, c.credit, sec.year, sec.semester, t.enrolled_at
    HAVING MAX(CASE WHEN e.exam_type = 'final' THEN e.score ELSE NULL END) IS NOT NULL
    ORDER BY sec.year DESC, t.enrolled_at DESC
    LIMIT p_recent_limit;

    SELECT MAX(enrollment_end) AS enroll_end
    FROM section
    WHERE NOW() BETWEEN enrollment_start AND enrollment_end;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_schedule`(IN p_student_id INT UNSIGNED)
BEGIN
    SELECT
        course_id,
        course_name,
        credit,
        section_id,
        semester,
        year,
        schedule_id,
        day_of_week,
        start_time,
        end_time,
        location,
        COALESCE(week_start, 1) AS week_start,
        COALESCE(week_end, 16) AS week_end,
        primary_teacher_name AS teacher_name,
        teacher_title
    FROM v_project_student_schedule_overview
    WHERE student_id = p_student_id
    ORDER BY year DESC, semester DESC, day_of_week, start_time, schedule_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_student_visible_announcements
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_student_visible_announcements`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_student_visible_announcements`(IN p_student_id INT UNSIGNED,
    IN p_query VARCHAR(200),
    IN p_limit INT,
    IN p_offset INT)
BEGIN
    DECLARE v_major_id INT UNSIGNED DEFAULT 0;
    DECLARE v_query_like VARCHAR(220) DEFAULT NULL;

    SELECT COALESCE(major_id, 0)
    INTO v_major_id
    FROM student
    WHERE user_id = p_student_id
    LIMIT 1;

    IF p_query IS NOT NULL AND TRIM(p_query) <> '' THEN
        SET v_query_like = CONCAT('%', TRIM(p_query), '%');
    END IF;

    SELECT COUNT(DISTINCT a.announcement_id) AS total
    FROM announcement a
    JOIN announcement_target t ON a.announcement_id = t.announcement_id
    JOIN user u ON u.user_id = a.author_user_id
    LEFT JOIN section sec ON t.target_type = 'section' AND sec.section_id = t.target_id
    LEFT JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN takes mk ON mk.section_id = sec.section_id AND mk.student_id = p_student_id
    WHERE a.status = 'published'
      AND (
          t.target_type IN ('all', 'students')
          OR (t.target_type = 'major' AND t.target_id = v_major_id)
          OR (t.target_type = 'section' AND mk.student_id IS NOT NULL)
      )
      AND (v_query_like IS NULL OR a.title LIKE v_query_like OR a.content LIKE v_query_like);

    SELECT
        a.announcement_id,
        ANY_VALUE(a.title) AS title,
        ANY_VALUE(a.content) AS content,
        ANY_VALUE(a.is_pinned) AS is_pinned,
        ANY_VALUE(a.published_at) AS published_at,
        ANY_VALUE(u.name) AS teacher_name,
        ANY_VALUE(a.created_at) AS created_at,
        GROUP_CONCAT(DISTINCT CONCAT(t.target_type, ':', COALESCE(t.target_id, '')) SEPARATOR ',') AS targets,
        ANY_VALUE(c.name) AS course_name,
        ANY_VALUE(sec.semester) AS semester,
        ANY_VALUE(sec.year) AS year
    FROM announcement a
    JOIN announcement_target t ON a.announcement_id = t.announcement_id
    JOIN user u ON u.user_id = a.author_user_id
    LEFT JOIN section sec ON t.target_type = 'section' AND sec.section_id = t.target_id
    LEFT JOIN course c ON sec.course_id = c.course_id
    LEFT JOIN takes mk ON mk.section_id = sec.section_id AND mk.student_id = p_student_id
    WHERE a.status = 'published'
      AND (
          t.target_type IN ('all', 'students')
          OR (t.target_type = 'major' AND t.target_id = v_major_id)
          OR (t.target_type = 'section' AND mk.student_id IS NOT NULL)
      )
      AND (v_query_like IS NULL OR a.title LIKE v_query_like OR a.content LIKE v_query_like)
    GROUP BY a.announcement_id
    ORDER BY MAX(a.is_pinned) DESC, MAX(a.published_at) DESC, MAX(a.created_at) DESC
    LIMIT p_limit OFFSET p_offset;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_teacher_visible_announcements
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_teacher_visible_announcements`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_teacher_visible_announcements`(IN p_teacher_id INT UNSIGNED,
    IN p_query VARCHAR(200),
    IN p_limit INT,
    IN p_offset INT)
BEGIN
    DECLARE v_query_like VARCHAR(220) DEFAULT NULL;

    IF p_query IS NOT NULL AND TRIM(p_query) <> '' THEN
        SET v_query_like = CONCAT('%', TRIM(p_query), '%');
    END IF;

    SELECT COUNT(DISTINCT a.announcement_id) AS total
    FROM announcement a
    JOIN announcement_target at ON at.announcement_id = a.announcement_id
    JOIN user u ON u.user_id = a.author_user_id
    LEFT JOIN section sec ON at.target_type = 'section' AND sec.section_id = at.target_id
    LEFT JOIN course c ON sec.course_id = c.course_id
    WHERE a.status = 'published'
      AND (
          (at.target_type = 'section' AND at.target_id IN (
              SELECT section_id FROM teaching WHERE teacher_id = p_teacher_id
          ))
          OR at.target_type IN ('all', 'teachers')
      )
      AND (v_query_like IS NULL OR a.title LIKE v_query_like OR a.content LIKE v_query_like);

    SELECT
        a.announcement_id,
        ANY_VALUE(a.author_user_id) AS author_user_id,
        ANY_VALUE(u.name) AS teacher_name,
        ANY_VALUE(a.title) AS title,
        ANY_VALUE(a.content) AS content,
        ANY_VALUE(a.is_pinned) AS is_pinned,
        ANY_VALUE(a.published_at) AS published_at,
        ANY_VALUE(a.created_at) AS created_at,
        ANY_VALUE(a.updated_at) AS updated_at,
        GROUP_CONCAT(DISTINCT CONCAT(at.target_type, ':', COALESCE(at.target_id, '')) SEPARATOR ',') AS targets,
        ANY_VALUE(c.name) AS course_name,
        ANY_VALUE(sec.semester) AS semester,
        ANY_VALUE(sec.year) AS year
    FROM announcement a
    JOIN announcement_target at ON at.announcement_id = a.announcement_id
    JOIN user u ON u.user_id = a.author_user_id
    LEFT JOIN section sec ON at.target_type = 'section' AND sec.section_id = at.target_id
    LEFT JOIN course c ON sec.course_id = c.course_id
    WHERE a.status = 'published'
      AND (
          (at.target_type = 'section' AND at.target_id IN (
              SELECT section_id FROM teaching WHERE teacher_id = p_teacher_id
          ))
          OR at.target_type IN ('all', 'teachers')
      )
      AND (v_query_like IS NULL OR a.title LIKE v_query_like OR a.content LIKE v_query_like)
    GROUP BY a.announcement_id
    ORDER BY MAX(a.is_pinned) DESC, MAX(a.published_at) DESC, MAX(a.created_at) DESC
    LIMIT p_limit OFFSET p_offset;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_project_get_time_slots
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_project_get_time_slots`;
delimiter ;;
CREATE PROCEDURE `sp_project_get_time_slots`()
BEGIN
    SELECT slot_id, slot_name, start_time, end_time
    FROM time_slot
    ORDER BY start_time, end_time, slot_id;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_publish_exam
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_publish_exam`;
delimiter ;;
CREATE PROCEDURE `sp_publish_exam`(IN  p_teacher_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    IN  p_exam_type  VARCHAR(20),
    IN  p_exam_date  DATE,
    OUT p_inserted   INT,
    OUT p_ok         TINYINT,
    OUT p_msg        VARCHAR(200) CHARACTER SET utf8mb4)
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
        INSERT IGNORE INTO exam (teacher_id, student_id, section_id, exam_type, exam_date, score)
        SELECT p_teacher_id, tk.student_id, p_section_id, p_exam_type, p_exam_date, NULL
        FROM takes tk
        WHERE tk.section_id = p_section_id;

        SET p_inserted = ROW_COUNT();
        SET p_ok = 1;
        SET p_msg = CONCAT('考试已发布，共 ', p_inserted, ' 名学生');
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_record_attendance
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_record_attendance`;
delimiter ;;
CREATE PROCEDURE `sp_record_attendance`(IN  p_teacher_id   INT UNSIGNED,
    IN  p_schedule_id  INT UNSIGNED,
    IN  p_student_id   INT UNSIGNED,
    IN  p_week         TINYINT UNSIGNED,
    IN  p_status       VARCHAR(20),
    IN  p_note         VARCHAR(200),
    OUT p_success      TINYINT,
    OUT p_message      VARCHAR(500))
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_teaches INT DEFAULT 0;
    DECLARE v_enrolled INT DEFAULT 0;

    SELECT section_id INTO v_section_id FROM schedule WHERE schedule_id = p_schedule_id;

    IF v_section_id IS NULL THEN
        SET p_success = 0;
        SET p_message = 'Schedule not found.';
    ELSE
        SELECT COUNT(*) INTO v_teaches FROM teaching
        WHERE teacher_id = p_teacher_id AND section_id = v_section_id;

        SELECT COUNT(*) INTO v_enrolled FROM takes
        WHERE student_id = p_student_id AND section_id = v_section_id;

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
                status      = p_status,
                note        = p_note,
                recorded_by = p_teacher_id,
                recorded_at = NOW();

            SET p_success = 1;
            SET p_message = 'Attendance recorded.';
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_remove_teaching
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_remove_teaching`;
delimiter ;;
CREATE PROCEDURE `sp_remove_teaching`(IN  p_teacher_id INT UNSIGNED,
    IN  p_section_id INT UNSIGNED,
    OUT p_success    TINYINT,
    OUT p_message    VARCHAR(500))
BEGIN
    DECLARE v_teaches   INT DEFAULT 0;
    DECLARE v_has_exams INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches
    FROM teaching WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    SELECT COUNT(*) INTO v_has_exams
    FROM exam WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Assignment not found or not authorized.';
    ELSEIF v_has_exams > 0 THEN
        SET p_success = 0;
        SET p_message = CONCAT('Cannot remove: you have ', v_has_exams,
                               ' exam record(s) tied to this section. Delete those first.');
    ELSE
        DELETE FROM teaching WHERE teacher_id = p_teacher_id AND section_id = p_section_id;
        SET p_success = 1;
        SET p_message = 'Teaching assignment removed successfully.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_save_exam
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_save_exam`;
delimiter ;;
CREATE PROCEDURE `sp_save_exam`(IN  p_teacher_id  INT UNSIGNED,
    IN  p_student_id  INT UNSIGNED,
    IN  p_section_id  INT UNSIGNED,
    IN  p_exam_type   VARCHAR(20),
    IN  p_score       DECIMAL(5,2),
    IN  p_exam_date   DATE,
    OUT p_exam_id     INT UNSIGNED)
BEGIN
    INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score)
    VALUES (p_teacher_id, p_student_id, p_section_id, p_exam_date, p_exam_type, p_score)
    ON DUPLICATE KEY UPDATE
        score     = VALUES(score),
        exam_type = VALUES(exam_type);

    SET p_exam_id = LAST_INSERT_ID();
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_exam_score
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_exam_score`;
delimiter ;;
CREATE PROCEDURE `sp_update_exam_score`(IN p_exam_id    INT UNSIGNED,
    IN p_score      DECIMAL(5,2),
    IN p_teacher_id INT UNSIGNED)
BEGIN
    UPDATE exam
    SET score = p_score
    WHERE exam_id = p_exam_id AND teacher_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_letter_grade
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_letter_grade`;
delimiter ;;
CREATE PROCEDURE `sp_update_letter_grade`(IN p_student_id INT UNSIGNED,
    IN p_section_id INT UNSIGNED,
    IN p_grade      VARCHAR(5))
BEGIN
    UPDATE takes
    SET grade = p_grade
    WHERE student_id = p_student_id AND section_id = p_section_id;
    SELECT ROW_COUNT() AS affected_rows;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_schedule
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_schedule`;
delimiter ;;
CREATE PROCEDURE `sp_update_schedule`(IN p_schedule_id INT UNSIGNED,
    IN p_day_of_week INT,
    IN p_start_time TIME,
    IN p_end_time TIME,
    IN p_classroom_id INT UNSIGNED,
    IN p_teacher_id INT UNSIGNED,
    OUT p_success BOOLEAN,
    OUT p_message VARCHAR(255))
BEGIN
    DECLARE v_section_id INT UNSIGNED;
    DECLARE v_room_conflict INT;
    DECLARE v_teacher_conflict INT;

    SELECT s.section_id INTO v_section_id
    FROM schedule s
    JOIN teaching tg ON s.section_id = tg.section_id
    WHERE s.schedule_id = p_schedule_id AND tg.teacher_id = p_teacher_id;

    IF v_section_id IS NULL THEN
        SET p_success = FALSE;
        SET p_message = 'Not authorized or schedule not found';
    ELSEIF p_end_time <= p_start_time THEN
        SET p_success = FALSE;
        SET p_message = 'End time must be after start time';
    ELSE
        SET v_room_conflict = fn_check_room_conflict(
            p_classroom_id, p_day_of_week, p_start_time, p_end_time, p_schedule_id
        );

        IF v_room_conflict > 0 THEN
            SET p_success = FALSE;
            SET p_message = 'Room is already reserved at this time';
        ELSE
            SET v_teacher_conflict = fn_check_teacher_conflict(
                p_teacher_id, p_day_of_week, p_start_time, p_end_time, p_schedule_id
            );

            IF v_teacher_conflict > 0 THEN
                SET p_success = FALSE;
                SET p_message = 'Teacher has conflicting schedule at this time';
            ELSE
                UPDATE schedule
                SET day_of_week = p_day_of_week,
                    start_time = p_start_time,
                    end_time = p_end_time,
                    classroom_id = p_classroom_id
                WHERE schedule_id = p_schedule_id;

                SET p_success = TRUE;
                SET p_message = 'Schedule updated successfully';
            END IF;
        END IF;
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_section_info
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_section_info`;
delimiter ;;
CREATE PROCEDURE `sp_update_section_info`(IN  p_teacher_id    INT UNSIGNED,
    IN  p_section_id    INT UNSIGNED,
    IN  p_capacity      SMALLINT UNSIGNED,
    IN  p_enroll_start  DATETIME,
    IN  p_enroll_end    DATETIME,
    OUT p_success       TINYINT,
    OUT p_message       VARCHAR(500))
BEGIN
    DECLARE v_teaches INT DEFAULT 0;

    SELECT COUNT(*) INTO v_teaches FROM teaching
    WHERE teacher_id = p_teacher_id AND section_id = p_section_id;

    IF v_teaches = 0 THEN
        SET p_success = 0;
        SET p_message = 'Not authorized: you do not teach this section.';
    ELSEIF p_capacity IS NOT NULL AND p_capacity < 1 THEN
        SET p_success = 0;
        SET p_message = 'Capacity must be at least 1.';
    ELSE
        UPDATE section
        SET
            capacity         = IFNULL(p_capacity, capacity),
            enrollment_start = IFNULL(p_enroll_start, enrollment_start),
            enrollment_end   = IFNULL(p_enroll_end,   enrollment_end)
        WHERE section_id = p_section_id;

        SET p_success = 1;
        SET p_message = 'Section info updated successfully.';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_teacher_avatar
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_teacher_avatar`;
delimiter ;;
CREATE PROCEDURE `sp_update_teacher_avatar`(IN p_teacher_id INT UNSIGNED,
    IN p_image      VARCHAR(255))
BEGIN
    UPDATE user SET image = p_image WHERE user_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END
;;
delimiter ;

-- ----------------------------
-- Procedure structure for sp_update_teacher_profile
-- ----------------------------
DROP PROCEDURE IF EXISTS `sp_update_teacher_profile`;
delimiter ;;
CREATE PROCEDURE `sp_update_teacher_profile`(IN p_teacher_id INT UNSIGNED,
    IN p_name       VARCHAR(100),
    IN p_phone      VARCHAR(20),
    IN p_gender     VARCHAR(10))
BEGIN
    UPDATE user
    SET name = p_name, phone = p_phone, gender = p_gender
    WHERE user_id = p_teacher_id;
    SELECT ROW_COUNT() AS affected_rows;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table announcement
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_project_announcement_values_bi`;
delimiter ;;
CREATE TRIGGER `trg_project_announcement_values_bi` BEFORE INSERT ON `announcement` FOR EACH ROW BEGIN
    SET NEW.title = TRIM(NEW.title);

    IF NEW.title = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement title cannot be empty';
    END IF;

    IF NEW.status NOT IN ('draft', 'published', 'archived') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement status is invalid';
    END IF;

    IF NEW.status = 'published' AND NEW.published_at IS NULL THEN
        SET NEW.published_at = NOW();
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table announcement
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_project_announcement_values_bu`;
delimiter ;;
CREATE TRIGGER `trg_project_announcement_values_bu` BEFORE UPDATE ON `announcement` FOR EACH ROW BEGIN
    SET NEW.title = TRIM(NEW.title);

    IF NEW.title = '' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement title cannot be empty';
    END IF;

    IF NEW.status NOT IN ('draft', 'published', 'archived') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement status is invalid';
    END IF;

    IF NEW.status = 'published' AND NEW.published_at IS NULL THEN
        SET NEW.published_at = NOW();
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table announcement_target
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_project_announcement_target_values_bi`;
delimiter ;;
CREATE TRIGGER `trg_project_announcement_target_values_bi` BEFORE INSERT ON `announcement_target` FOR EACH ROW BEGIN
    SET NEW.target_type = LOWER(TRIM(NEW.target_type));

    IF NEW.target_type NOT IN ('all', 'students', 'teachers', 'major', 'section') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement target type is invalid';
    END IF;

    IF NEW.target_type IN ('all', 'students', 'teachers') AND NEW.target_id IS NULL THEN
        SET NEW.target_id = 0;
    END IF;

    IF NEW.target_type IN ('major', 'section') AND (NEW.target_id IS NULL OR NEW.target_id = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement target id is required';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table announcement_target
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_project_announcement_target_values_bu`;
delimiter ;;
CREATE TRIGGER `trg_project_announcement_target_values_bu` BEFORE UPDATE ON `announcement_target` FOR EACH ROW BEGIN
    SET NEW.target_type = LOWER(TRIM(NEW.target_type));

    IF NEW.target_type NOT IN ('all', 'students', 'teachers', 'major', 'section') THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement target type is invalid';
    END IF;

    IF NEW.target_type IN ('all', 'students', 'teachers') AND NEW.target_id IS NULL THEN
        SET NEW.target_id = 0;
    END IF;

    IF NEW.target_type IN ('major', 'section') AND (NEW.target_id IS NULL OR NEW.target_id = 0) THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Announcement target id is required';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table department
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_department_code_bi`;
delimiter ;;
CREATE TRIGGER `trg_department_code_bi` BEFORE INSERT ON `department` FOR EACH ROW BEGIN
    IF NEW.`dept_code` IS NULL
       OR NEW.`dept_code` NOT REGEXP '^[A-Z]{1,10}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'dept_code must be 1-10 uppercase letters';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table department
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_department_code_bu`;
delimiter ;;
CREATE TRIGGER `trg_department_code_bu` BEFORE UPDATE ON `department` FOR EACH ROW BEGIN
    IF NEW.`dept_code` IS NULL
       OR NEW.`dept_code` NOT REGEXP '^[A-Z]{1,10}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'dept_code must be 1-10 uppercase letters';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table exam
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_exam_score_bi`;
delimiter ;;
CREATE TRIGGER `trg_exam_score_bi` BEFORE INSERT ON `exam` FOR EACH ROW BEGIN
    IF NEW.`score` IS NOT NULL
       AND (NEW.`score` < 0 OR NEW.`score` > 100) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'score must be between 0 and 100';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table exam
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_exam_score_bu`;
delimiter ;;
CREATE TRIGGER `trg_exam_score_bu` BEFORE UPDATE ON `exam` FOR EACH ROW BEGIN
    IF NEW.`score` IS NOT NULL
       AND (NEW.`score` < 0 OR NEW.`score` > 100) THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'score must be between 0 and 100';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table major
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_major_code_bi`;
delimiter ;;
CREATE TRIGGER `trg_major_code_bi` BEFORE INSERT ON `major` FOR EACH ROW BEGIN
    IF NEW.`major_code` IS NULL
       OR NEW.`major_code` NOT REGEXP '^[A-Z]{1,10}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'major_code must be 1-10 uppercase letters';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table major
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_major_code_bu`;
delimiter ;;
CREATE TRIGGER `trg_major_code_bu` BEFORE UPDATE ON `major` FOR EACH ROW BEGIN
    IF NEW.`major_code` IS NULL
       OR NEW.`major_code` NOT REGEXP '^[A-Z]{1,10}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'major_code must be 1-10 uppercase letters';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table student
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_student_no_bi`;
delimiter ;;
CREATE TRIGGER `trg_student_no_bi` BEFORE INSERT ON `student` FOR EACH ROW BEGIN
    IF NEW.`student_no` IS NULL
       OR NEW.`student_no` NOT REGEXP '^[0-9]{8}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'student_no must be exactly 8 digits';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table student
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_student_no_bu`;
delimiter ;;
CREATE TRIGGER `trg_student_no_bu` BEFORE UPDATE ON `student` FOR EACH ROW BEGIN
    IF NEW.`student_no` IS NULL
       OR NEW.`student_no` NOT REGEXP '^[0-9]{8}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'student_no must be exactly 8 digits';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table takes
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_project_takes_enrolled_at_bi`;
delimiter ;;
CREATE TRIGGER `trg_project_takes_enrolled_at_bi` BEFORE INSERT ON `takes` FOR EACH ROW BEGIN
    IF NEW.enrolled_at IS NULL THEN
        SET NEW.enrolled_at = NOW();
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table teaching
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_no_duplicate_teaching`;
delimiter ;;
CREATE TRIGGER `trg_no_duplicate_teaching` BEFORE INSERT ON `teaching` FOR EACH ROW BEGIN
    DECLARE v_cnt INT DEFAULT 0;
    SELECT COUNT(*) INTO v_cnt FROM teaching
    WHERE teacher_id = NEW.teacher_id AND section_id = NEW.section_id;
    IF v_cnt > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Teacher is already assigned to this section';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table user
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_user_contact_bi`;
delimiter ;;
CREATE TRIGGER `trg_user_contact_bi` BEFORE INSERT ON `user` FOR EACH ROW BEGIN
    -- 邮箱：必须为合法格式，且以 @school.edu 结尾
    IF NEW.`email` IS NULL
       OR NEW.`email` NOT REGEXP '^[A-Za-z0-9._%+-]+@school\\.edu$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'email must be valid and end with @school.edu';
    END IF;

    -- 手机号：允许 NULL 或空串；如果填写则必须是11位数字
    IF NEW.`phone` IS NOT NULL
       AND NEW.`phone` <> ''
       AND NEW.`phone` NOT REGEXP '^[0-9]{11}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'phone must be exactly 11 digits';
    END IF;
END
;;
delimiter ;

-- ----------------------------
-- Triggers structure for table user
-- ----------------------------
DROP TRIGGER IF EXISTS `trg_user_contact_bu`;
delimiter ;;
CREATE TRIGGER `trg_user_contact_bu` BEFORE UPDATE ON `user` FOR EACH ROW BEGIN
    IF NEW.`email` IS NULL
       OR NEW.`email` NOT REGEXP '^[A-Za-z0-9._%+-]+@school\\.edu$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'email must be valid and end with @school.edu';
    END IF;

    IF NEW.`phone` IS NOT NULL
       AND NEW.`phone` <> ''
       AND NEW.`phone` NOT REGEXP '^[0-9]{11}$' THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'phone must be exactly 11 digits';
    END IF;
END
;;
delimiter ;

SET FOREIGN_KEY_CHECKS = 1;
