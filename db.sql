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

 Date: 28/03/2026 19:45:52
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
-- Records of admin
-- ----------------------------
INSERT INTO `admin` VALUES (8, 'super_admin');

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
-- Records of advisor
-- ----------------------------
INSERT INTO `advisor` VALUES (5, 1);
INSERT INTO `advisor` VALUES (5, 2);
INSERT INTO `advisor` VALUES (6, 3);
INSERT INTO `advisor` VALUES (7, 4);

-- ----------------------------
-- Table structure for course
-- ----------------------------
DROP TABLE IF EXISTS `course`;
CREATE TABLE `course`  (
  `course_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(150) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '课程名称',
  `credit` decimal(3, 1) NOT NULL COMMENT '学分',
  `hours` tinyint(3) UNSIGNED NOT NULL COMMENT '学时',
  `capacity` smallint(5) UNSIGNED NOT NULL COMMENT '容量',
  `description` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL COMMENT '课程描述',
  PRIMARY KEY (`course_id`) USING BTREE,
  UNIQUE INDEX `uq_course_name`(`name` ASC) USING BTREE,
  INDEX `idx_course_credit`(`credit` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 13 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of course
-- ----------------------------
INSERT INTO `course` VALUES (1, 'Introduction to Programming', 3.0, 48, 60, 'Python basics, data types, control flow');
INSERT INTO `course` VALUES (2, 'Data Structures & Algorithms', 3.0, 48, 50, 'Arrays, linked lists, trees, sorting');
INSERT INTO `course` VALUES (3, 'Database Systems', 3.0, 48, 45, 'ER model, SQL, normalization');
INSERT INTO `course` VALUES (4, 'Linear Algebra', 3.0, 48, 80, 'Vectors, matrices, eigenvalues');
INSERT INTO `course` VALUES (5, 'Calculus I', 4.0, 64, 100, 'Limits, derivatives, integrals');
INSERT INTO `course` VALUES (6, 'Marketing Fundamentals', 2.0, 32, 70, 'Market analysis, consumer behavior');
INSERT INTO `course` VALUES (7, 'Circuit Analysis', 3.0, 48, 40, 'Ohm law, Kirchhoff laws, AC/DC');
INSERT INTO `course` VALUES (8, 'Operating Systems', 3.0, 48, 50, 'Process management, memory, file systems');
INSERT INTO `course` VALUES (9, 'Computer Networks', 3.0, 48, 45, 'TCP/IP, routing, application layer protocols');
INSERT INTO `course` VALUES (10, 'Probability & Statistics', 3.0, 48, 60, 'Random variables, distributions, hypothesis testing');
INSERT INTO `course` VALUES (11, 'General Physics I', 4.0, 64, 60, 'Classical mechanics, thermodynamics, and waves.');
INSERT INTO `course` VALUES (12, 'Quantum Physics Basics', 3.0, 48, 40, 'Introduction to quantum mechanics principles.');

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
-- Records of department
-- ----------------------------
INSERT INTO `department` VALUES (1, 'Computer Science', 'CS');
INSERT INTO `department` VALUES (2, 'Mathematics', 'MATH');
INSERT INTO `department` VALUES (3, 'Business Administration', 'BA');
INSERT INTO `department` VALUES (4, 'Electrical Engineering', 'EE');
INSERT INTO `department` VALUES (5, 'Physics', 'PHYS');

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
-- Records of exam
-- ----------------------------
INSERT INTO `exam` VALUES (1, 5, 1, 1, '2025-06-15', 'final', 92.50);
INSERT INTO `exam` VALUES (2, 5, 4, 1, '2025-06-15', 'final', 73.00);
INSERT INTO `exam` VALUES (3, 7, 1, 2, '2025-06-18', 'final', 88.00);
INSERT INTO `exam` VALUES (4, 7, 2, 2, '2025-06-18', 'final', 91.50);
INSERT INTO `exam` VALUES (5, 6, 3, 4, '2025-12-20', 'final', 85.00);
INSERT INTO `exam` VALUES (6, 5, 1, 3, '2025-04-10', 'final', 78.00);
INSERT INTO `exam` VALUES (7, 5, 2, 3, '2025-04-10', 'final', 82.50);
INSERT INTO `exam` VALUES (8, 5, 1, 1, '2025-04-20', 'final', 88.00);
INSERT INTO `exam` VALUES (9, 7, 2, 2, '2025-04-22', 'final', 79.00);
INSERT INTO `exam` VALUES (10, 5, 1, 1, '2025-03-15', 'final', 95.00);
INSERT INTO `exam` VALUES (11, 6, 3, 4, '2025-10-10', 'final', 72.00);
INSERT INTO `exam` VALUES (12, 5, 1, 7, '2024-03-10', 'final', 93.00);
INSERT INTO `exam` VALUES (13, 5, 1, 7, '2024-04-15', 'final', 87.50);
INSERT INTO `exam` VALUES (14, 5, 1, 7, '2024-06-12', 'final', 91.00);
INSERT INTO `exam` VALUES (15, 5, 2, 7, '2024-03-10', 'final', 80.00);
INSERT INTO `exam` VALUES (16, 5, 2, 7, '2024-04-15', 'final', 75.00);
INSERT INTO `exam` VALUES (17, 5, 2, 7, '2024-06-12', 'final', 78.50);
INSERT INTO `exam` VALUES (18, 7, 1, 8, '2024-03-12', 'final', 88.00);
INSERT INTO `exam` VALUES (19, 7, 1, 8, '2024-04-18', 'final', 84.00);
INSERT INTO `exam` VALUES (20, 7, 1, 8, '2024-06-14', 'final', 86.50);
INSERT INTO `exam` VALUES (21, 7, 4, 8, '2024-03-12', 'final', 65.00);
INSERT INTO `exam` VALUES (22, 7, 4, 8, '2024-04-18', 'final', 60.50);
INSERT INTO `exam` VALUES (23, 7, 4, 8, '2024-06-14', 'final', 63.00);
INSERT INTO `exam` VALUES (24, 6, 1, 9, '2024-04-20', 'final', 90.00);
INSERT INTO `exam` VALUES (25, 6, 1, 9, '2024-06-16', 'final', 92.00);
INSERT INTO `exam` VALUES (26, 6, 2, 9, '2024-04-20', 'final', 83.00);
INSERT INTO `exam` VALUES (27, 6, 2, 9, '2024-06-16', 'final', 89.50);

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
  PRIMARY KEY (`section_id`) USING BTREE,
  UNIQUE INDEX `uq_section`(`course_id` ASC, `semester` ASC, `year` ASC) USING BTREE,
  INDEX `idx_section_course`(`course_id` ASC) USING BTREE,
  INDEX `idx_section_year_sem`(`year` ASC, `semester` ASC) USING BTREE,
  CONSTRAINT `fk_section_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of section
-- ----------------------------
INSERT INTO `section` VALUES (1, 'Spring', 2025, 1, '2025-01-15 08:00:00', '2025-02-28 23:59:59');
INSERT INTO `section` VALUES (2, 'Spring', 2025, 2, '2025-01-15 08:00:00', '2025-02-28 23:59:59');
INSERT INTO `section` VALUES (3, 'Spring', 2025, 3, '2025-01-15 08:00:00', '2025-02-28 23:59:59');
INSERT INTO `section` VALUES (4, 'Fall', 2025, 4, '2025-07-15 08:00:00', '2025-08-31 23:59:59');
INSERT INTO `section` VALUES (5, 'Fall', 2025, 5, '2025-07-15 08:00:00', '2025-08-31 23:59:59');
INSERT INTO `section` VALUES (6, 'Spring', 2025, 7, '2025-01-15 08:00:00', '2025-02-28 23:59:59');
INSERT INTO `section` VALUES (7, 'Spring', 2024, 8, '2024-01-15 08:00:00', '2024-02-28 23:59:59');
INSERT INTO `section` VALUES (8, 'Spring', 2024, 9, '2024-01-15 08:00:00', '2024-02-28 23:59:59');
INSERT INTO `section` VALUES (9, 'Spring', 2024, 10, '2024-01-15 08:00:00', '2024-02-28 23:59:59');
INSERT INTO `section` VALUES (10, 'Spring', 2026, 11, '2026-01-15 08:00:00', '2026-02-28 23:59:59');
INSERT INTO `section` VALUES (11, 'Spring', 2026, 12, '2026-01-15 08:00:00', '2026-02-28 23:59:59');

-- ----------------------------
-- Table structure for section_restriction
-- ----------------------------
DROP TABLE IF EXISTS `section_restriction`;
CREATE TABLE `section_restriction`  (
  `section_id` int(10) UNSIGNED NOT NULL COMMENT '开课节',
  `dept_id` int(10) UNSIGNED NOT NULL COMMENT '允许选修的院系',
  PRIMARY KEY (`section_id`, `dept_id`) USING BTREE,
  INDEX `idx_restriction_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_restriction_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_restriction_section` FOREIGN KEY (`section_id`) REFERENCES `section` (`section_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci COMMENT = '开课节选课院系限制表' ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of section_restriction
-- ----------------------------
INSERT INTO `section_restriction` VALUES (1, 1);
INSERT INTO `section_restriction` VALUES (2, 1);
INSERT INTO `section_restriction` VALUES (2, 2);

-- ----------------------------
-- Table structure for section_schedule
-- ----------------------------
DROP TABLE IF EXISTS `section_schedule`;
CREATE TABLE `section_schedule`  (
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
-- Records of section_schedule
-- ----------------------------
INSERT INTO `section_schedule` VALUES (1, 1, 1, '08:00:00', '09:40:00', 'Teaching Bldg A-101', 1, 13);
INSERT INTO `section_schedule` VALUES (2, 1, 3, '08:00:00', '09:40:00', 'Teaching Bldg A-101', 1, 13);
INSERT INTO `section_schedule` VALUES (3, 2, 2, '10:00:00', '11:40:00', 'Teaching Bldg B-205', 1, 13);
INSERT INTO `section_schedule` VALUES (4, 2, 4, '10:00:00', '11:40:00', 'Teaching Bldg B-205', 1, 13);
INSERT INTO `section_schedule` VALUES (5, 3, 5, '14:00:00', '15:40:00', 'Computer Lab C-302', 1, 13);
INSERT INTO `section_schedule` VALUES (6, 4, 1, '14:00:00', '15:40:00', 'Math Bldg M-101', 1, 13);
INSERT INTO `section_schedule` VALUES (7, 5, 2, '08:00:00', '09:40:00', 'Math Bldg M-102', 1, 13);
INSERT INTO `section_schedule` VALUES (8, 6, 4, '16:00:00', '17:40:00', 'Engineering Bldg E-104', 1, 13);
INSERT INTO `section_schedule` VALUES (9, 7, 1, '10:00:00', '11:40:00', 'Teaching Bldg A-201', 1, 13);
INSERT INTO `section_schedule` VALUES (10, 8, 3, '14:00:00', '15:40:00', 'Teaching Bldg A-202', 1, 13);
INSERT INTO `section_schedule` VALUES (11, 9, 5, '08:00:00', '09:40:00', 'Math Bldg M-105', 1, 13);
INSERT INTO `section_schedule` VALUES (12, 10, 1, '08:00:00', '09:40:00', 'Science Bldg S-101', 1, 13);
INSERT INTO `section_schedule` VALUES (13, 11, 3, '10:00:00', '11:40:00', 'Science Bldg S-202', 1, 13);

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
  PRIMARY KEY (`user_id`) USING BTREE,
  UNIQUE INDEX `uq_student_no`(`student_no` ASC) USING BTREE,
  INDEX `idx_student_dept`(`dept_id` ASC) USING BTREE,
  CONSTRAINT `fk_student_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`dept_id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_student_user` FOREIGN KEY (`user_id`) REFERENCES `user` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of student
-- ----------------------------
INSERT INTO `student` VALUES (1, '2022CS001', 'Sophomore', 2022, 1);
INSERT INTO `student` VALUES (2, '2021CS002', 'Junior', 2021, 1);
INSERT INTO `student` VALUES (3, '2023MATH003', 'Freshman', 2023, 2);
INSERT INTO `student` VALUES (4, '2020BA004', 'Senior', 2020, 3);
INSERT INTO `student` VALUES (10, '2023PHYS001', 'Freshman', 2023, 5);

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
-- Records of takes
-- ----------------------------
INSERT INTO `takes` VALUES (1, 1, 'A', 'enrolled', '2025-02-18 09:05:00');
INSERT INTO `takes` VALUES (1, 2, 'B+', 'enrolled', '2025-02-18 09:10:00');
INSERT INTO `takes` VALUES (1, 3, NULL, 'enrolled', '2025-02-18 09:15:00');
INSERT INTO `takes` VALUES (1, 7, 'A', 'enrolled', '2024-02-19 09:00:00');
INSERT INTO `takes` VALUES (1, 8, 'B+', 'enrolled', '2024-02-19 09:05:00');
INSERT INTO `takes` VALUES (1, 9, 'A-', 'enrolled', '2024-02-19 09:10:00');
INSERT INTO `takes` VALUES (1, 10, NULL, 'enrolled', '2026-03-28 16:43:45');
INSERT INTO `takes` VALUES (1, 11, NULL, 'enrolled', '2026-03-28 16:43:50');
INSERT INTO `takes` VALUES (2, 2, 'A-', 'enrolled', '2025-02-19 10:00:00');
INSERT INTO `takes` VALUES (2, 3, NULL, 'enrolled', '2025-02-19 10:05:00');
INSERT INTO `takes` VALUES (2, 7, 'B', 'enrolled', '2024-02-20 10:00:00');
INSERT INTO `takes` VALUES (2, 9, 'A', 'enrolled', '2024-02-20 10:05:00');
INSERT INTO `takes` VALUES (3, 4, 'B', 'enrolled', '2025-08-25 08:30:00');
INSERT INTO `takes` VALUES (3, 5, NULL, 'enrolled', '2025-08-25 08:35:00');
INSERT INTO `takes` VALUES (4, 1, 'C+', 'enrolled', '2025-02-20 14:00:00');
INSERT INTO `takes` VALUES (4, 8, 'C', 'enrolled', '2024-02-21 14:00:00');
INSERT INTO `takes` VALUES (10, 10, NULL, 'enrolled', '2026-01-20 09:00:00');
INSERT INTO `takes` VALUES (10, 11, NULL, 'enrolled', '2026-01-20 09:15:00');

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
-- Records of teacher
-- ----------------------------
INSERT INTO `teacher` VALUES (5, 'Professor', 1);
INSERT INTO `teacher` VALUES (6, 'Doctor', 2);
INSERT INTO `teacher` VALUES (7, 'Associate Professor', 1);
INSERT INTO `teacher` VALUES (9, 'Professor', 5);

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
-- Records of teaching
-- ----------------------------
INSERT INTO `teaching` VALUES (5, 1);
INSERT INTO `teaching` VALUES (7, 2);
INSERT INTO `teaching` VALUES (5, 3);
INSERT INTO `teaching` VALUES (6, 4);
INSERT INTO `teaching` VALUES (6, 5);
INSERT INTO `teaching` VALUES (5, 7);
INSERT INTO `teaching` VALUES (7, 8);
INSERT INTO `teaching` VALUES (6, 9);
INSERT INTO `teaching` VALUES (9, 10);
INSERT INTO `teaching` VALUES (9, 11);

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
  INDEX `idx_user_created_at`(`created_at` ASC) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 11 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of user
-- ----------------------------
INSERT INTO `user` VALUES (1, 'Alice Wang', 'alice21@scool.edu', 'hashed_pw_001', 'active', '2026-03-25 20:14:55', 'female', '13800002001', 'avatar_1_1774591401.png');
INSERT INTO `user` VALUES (2, 'Bob Li', 'bob@school.edu', 'hashed_pw_002', 'active', '2026-03-25 20:14:55', 'male', '13800000002', NULL);
INSERT INTO `user` VALUES (3, 'Carol Zhang', 'carol@school.edu', 'hashed_pw_003', 'active', '2026-03-25 20:14:55', 'female', '13800000003', NULL);
INSERT INTO `user` VALUES (4, 'David Chen', 'david@school.edu', 'hashed_pw_004', 'inactive', '2026-03-25 20:14:55', 'male', '13800000004', NULL);
INSERT INTO `user` VALUES (5, 'Prof. Sun', 'sun@school.edu', 'hashed_pw_101', 'active', '2026-03-25 20:14:55', 'male', '13900000001', NULL);
INSERT INTO `user` VALUES (6, 'Dr. Liu', 'liu@school.edu', 'hashed_pw_102', 'active', '2026-03-25 20:14:55', 'female', '13900000002', NULL);
INSERT INTO `user` VALUES (7, 'Assoc. Prof. Wu', 'wu@school.edu', 'hashed_pw_103', 'active', '2026-03-25 20:14:55', 'male', '13900000003', NULL);
INSERT INTO `user` VALUES (8, 'Admin Root', 'admin@school.edu', 'hashed_pw_999', 'active', '2026-03-25 20:14:55', 'other', '13700000001', NULL);
INSERT INTO `user` VALUES (9, 'Prof. Newton', 'newton@school.edu', 'hashed_pw_104', 'active', '2026-03-28 10:00:00', 'male', '13900000004', NULL);
INSERT INTO `user` VALUES (10, 'Eve Smith', 'eve@school.edu', 'hashed_pw_005', 'active', '2026-03-28 10:05:00', 'female', '13800000005', NULL);

SET FOREIGN_KEY_CHECKS = 1;
