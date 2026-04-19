/*
 Navicat Premium Data Transfer

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : school_db

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 30/03/2026 18:31:50
*/

SET NAMES utf8mb4;
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
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for department
-- ----------------------------
DROP TABLE IF EXISTS `department`;
CREATE TABLE `department`  (
  `dept_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '院系名称',
  `dept_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '院系代码',
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
  CONSTRAINT `fk_exam_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 28 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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
  INDEX `idx_section_course`(`course_id` ASC) USING BTREE,
  INDEX `idx_section_year_sem`(`year` ASC, `semester` ASC) USING BTREE,
  CONSTRAINT `fk_section_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for section_restriction
-- ----------------------------
DROP TABLE IF EXISTS `restriction`;
CREATE TABLE `restriction`  (
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '开课节',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '允许选修的院系',
  PRIMARY KEY (`section_id`, `dept_id`) USING BTREE,
  INDEX `idx_restriction_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_restriction_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_restriction_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '开课节选课院系限制表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for section_schedule
-- ----------------------------
DROP TABLE IF EXISTS `schedule`;
CREATE TABLE `schedule`  (
  `schedule_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '关联的开课节',
  `day_of_week` tinyint(3) UNSIGNED NOT NULL COMMENT '星期几 (1=周一, 7=周日)',
  `start_time` time NOT NULL COMMENT '上课开始时间',
  `end_time` time NOT NULL COMMENT '上课结束时间',
  `location` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '上课地点/教室',
  `week_start` tinyint(2) UNSIGNED NULL DEFAULT 1 COMMENT 'First week of class (1-16)',
  `week_end` tinyint(2) UNSIGNED NULL DEFAULT 13 COMMENT 'Last week of class (1-16)',
  PRIMARY KEY (`schedule_id`) USING BTREE,
  INDEX `idx_schedule_section`(`section_id` ASC) USING BTREE,
  CONSTRAINT `fk_schedule_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 14 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '排课时间表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Table structure for student
-- ----------------------------
DROP TABLE IF EXISTS `student`;
CREATE TABLE `student`  (
  `user_id` int(10) UNSIGNED NOT NULL COMMENT '关联 user.user_id',
  `student_no` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '学号（唯一）',
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
-- Table structure for takes
-- ----------------------------
DROP TABLE IF EXISTS `takes`;
CREATE TABLE `takes`  (
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `grade` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '成绩（A/B+/C 等字母制）',
  `status` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'enrolled' COMMENT '选课状态：enrolled=已选，dropped=已退，pending=待审核',
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '选课时间',
  PRIMARY KEY (`student_id`, `section_id`) USING BTREE,
  INDEX `idx_takes_section`(`section_id` ASC) USING BTREE,
  INDEX `idx_takes_status`(`status` ASC) USING BTREE,
  INDEX `idx_takes_enrolled`(`enrolled_at` ASC) USING BTREE,
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
  `phone` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '手机号',
  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL COMMENT '头像文件名',
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_user_email`(`email` ASC) USING BTREE,
  UNIQUE INDEX `uq_user_phone`(`phone` ASC) USING BTREE,
  INDEX `idx_user_status`(`status` ASC) USING BTREE,
  INDEX `idx_user_created_at`(`created_at` ASC) USING BTREE,
  CONSTRAINT `uq_email` UNIQUE(email)
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;



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
--INSERT INTO `config` VALUES ('office_phone', '\"12345678\"', '学生页展示的办公电话', '2026-04-18 20:09:36');
--INSERT INTO `config` VALUES ('schedule.grid_end_h', '22', '课表结束小时', '2026-04-18 20:09:36');
--INSERT INTO `config` VALUES ('schedule.grid_start_h', '8', '课表开始小时', '2026-04-18 20:09:36');
--INSERT INTO `config` VALUES ('schedule.total_weeks', '17', '学期总周数', '2026-04-18 20:43:04');
--INSERT INTO `config` VALUES ('term.fall_start_date', '\"2026-09-07\"', NULL, '2026-04-18 21:12:00');
--INSERT INTO `config` VALUES ('term.spring_start_date', '\"2026-03-02\"', NULL, '2026-04-18 21:12:00');

-- ----------------------------
-- Table structure for major
-- ----------------------------
DROP TABLE IF EXISTS `major`;
CREATE TABLE `major`  (
  `major_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `major_name` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专业名称',
  `major_code` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '专业代码',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '所属院系',
  PRIMARY KEY (`major_id`) USING BTREE,
  UNIQUE INDEX `uq_major_name`(`major_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_major_code`(`major_code` ASC) USING BTREE,
  INDEX `idx_major_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_major_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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
  CONSTRAINT `fk_log_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

SET FOREIGN_KEY_CHECKS = 1;
