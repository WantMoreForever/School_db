/*
 Navicat Premium Data Transfer

 Source Server         : localhost_3306
 Source Server Type    : MySQL
 Source Server Version : 80012 (8.0.12)
 Source Host           : localhost:3306
 Source Schema         : school

 Target Server Type    : MySQL
 Target Server Version : 80012 (8.0.12)
 File Encoding         : 65001

 Date: 27/03/2026 13:24:46
*/

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- Table structure for admin
-- ----------------------------
DROP TABLE IF EXISTS `admin`;
CREATE TABLE `admin`  (
  `user_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'admin',
  PRIMARY KEY (`user_id`) USING BTREE,
  CONSTRAINT `fk_admin_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for advisor
-- ----------------------------
DROP TABLE IF EXISTS `advisor`;
CREATE TABLE `advisor`  (
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `start_date` date NULL DEFAULT NULL,
  PRIMARY KEY (`teacher_id`, `student_id`) USING BTREE,
  INDEX `idx_advisor_student`(`student_id` ASC) USING BTREE,
  CONSTRAINT `fk_advisor_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_advisor_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for classroom
-- ----------------------------
DROP TABLE IF EXISTS `classroom`;
CREATE TABLE `classroom`  (
  `room_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `building` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `room_no` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL,
  `room_type` enum('lecture','lab','seminar') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'lecture',
  PRIMARY KEY (`room_id`) USING BTREE,
  UNIQUE INDEX `uq_room`(`building` ASC, `room_no` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for course
-- ----------------------------
DROP TABLE IF EXISTS `course`;
CREATE TABLE `course`  (
  `course_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `credit` decimal(3, 1) NOT NULL,
  `hours` tinyint(3) UNSIGNED NOT NULL,
  `course_type` enum('required','elective','public') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'elective',
  `description` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`course_id`) USING BTREE,
  UNIQUE INDEX `uq_course_name`(`name` ASC) USING BTREE,
  INDEX `idx_course_dept`(`dept_id` ASC) USING BTREE,
  INDEX `idx_course_credit`(`credit` ASC) USING BTREE,
  CONSTRAINT `fk_course_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for department
-- ----------------------------
DROP TABLE IF EXISTS `department`;
CREATE TABLE `department`  (
  `dept_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `dept_name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `dept_code` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `intro` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL,
  PRIMARY KEY (`dept_id`) USING BTREE,
  UNIQUE INDEX `uq_dept_name`(`dept_name` ASC) USING BTREE,
  UNIQUE INDEX `uq_dept_code`(`dept_code` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for exam
-- ----------------------------
DROP TABLE IF EXISTS `exam`;
CREATE TABLE `exam`  (
  `exam_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `section_id` int(10) UNSIGNED NOT NULL,
  `teacher_id` int(10) UNSIGNED NOT NULL,
  `exam_date` date NOT NULL,
  `exam_type` enum('midterm','final','quiz','makeup') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'final',
  `location` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `duration_min` smallint(5) UNSIGNED NULL DEFAULT NULL,
  PRIMARY KEY (`exam_id`) USING BTREE,
  UNIQUE INDEX `uq_exam`(`section_id` ASC, `exam_date` ASC, `exam_type` ASC) USING BTREE,
  INDEX `idx_exam_section`(`section_id` ASC) USING BTREE,
  INDEX `idx_exam_teacher`(`teacher_id` ASC) USING BTREE,
  INDEX `idx_exam_date`(`exam_date` ASC) USING BTREE,
  CONSTRAINT `fk_exam_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_exam_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `teacher` (`user_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 6 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for exam_record
-- ----------------------------
DROP TABLE IF EXISTS `exam_record`;
CREATE TABLE `exam_record`  (
  `exam_id` int(10) UNSIGNED NOT NULL,
  `student_id` int(10) UNSIGNED NOT NULL,
  `score` decimal(5, 2) NULL DEFAULT NULL,
  `is_absent` tinyint(1) NOT NULL DEFAULT 0,
  `remark` varchar(200) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`exam_id`, `student_id`) USING BTREE,
  INDEX `idx_exam_record_student`(`student_id` ASC) USING BTREE,
  CONSTRAINT `fk_record_exam` FOREIGN KEY (`exam_id`) REFERENCES `exam` (`exam_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_record_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for section
-- ----------------------------
DROP TABLE IF EXISTS `section`;
CREATE TABLE `section`  (
  `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `course_id` int(10) UNSIGNED NOT NULL,
  `room_id` int(10) UNSIGNED NULL DEFAULT NULL,
  `semester` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `year` year NOT NULL,
  `capacity` smallint(5) UNSIGNED NOT NULL,
  `schedule` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `status` enum('open','closed','cancelled') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'open',
  PRIMARY KEY (`section_id`) USING BTREE,
  UNIQUE INDEX `uq_section`(`course_id` ASC, `semester` ASC, `year` ASC) USING BTREE,
  INDEX `idx_section_course`(`course_id` ASC) USING BTREE,
  INDEX `idx_section_year_sem`(`year` ASC, `semester` ASC) USING BTREE,
  INDEX `idx_section_room`(`room_id` ASC) USING BTREE,
  CONSTRAINT `fk_section_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_section_room` FOREIGN KEY (`room_id`) REFERENCES `classroom` (`room_id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for student
-- ----------------------------
DROP TABLE IF EXISTS `student`;
CREATE TABLE `student`  (
  `user_id` int(10) UNSIGNED NOT NULL,
  `student_no` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `grade` varchar(10) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `enrollment_year` year NOT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_student_no`(`student_no` ASC) USING BTREE,
  INDEX `idx_student_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_student_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for takes
-- ----------------------------
DROP TABLE IF EXISTS `takes`;
CREATE TABLE `takes`  (
  `student_id` int(10) UNSIGNED NOT NULL,
  `section_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','enrolled','dropped') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'pending',
  `grade` varchar(5) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `enrolled_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`student_id`, `section_id`) USING BTREE,
  INDEX `idx_takes_section`(`section_id` ASC) USING BTREE,
  CONSTRAINT `fk_takes_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_takes_student` FOREIGN KEY (`student_id`) REFERENCES `student` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for teacher
-- ----------------------------
DROP TABLE IF EXISTS `teacher`;
CREATE TABLE `teacher`  (
  `user_id` int(10) UNSIGNED NOT NULL,
  `teacher_no` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `title` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_teacher_no`(`teacher_no` ASC) USING BTREE,
  INDEX `idx_teacher_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_teacher_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_teacher_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

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
) ENGINE = InnoDB CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

-- ----------------------------
-- Table structure for user
-- ----------------------------
DROP TABLE IF EXISTS `user`;
CREATE TABLE `user`  (
  `user_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `email` varchar(150) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `status` enum('active','inactive','banned') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `gender` enum('male','female','other') CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `phone` varchar(20) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  `image` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NULL DEFAULT NULL,
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_user_email`(`email` ASC) USING BTREE,
  UNIQUE INDEX `uq_user_phone`(`phone` ASC) USING BTREE,
  INDEX `idx_user_status`(`status` ASC) USING BTREE,
  INDEX `idx_user_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8 COLLATE = utf8_unicode_ci ROW_FORMAT = Dynamic;

SET FOREIGN_KEY_CHECKS = 1;
