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

 Date: 27/03/2026 14:11:13
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
) ENGINE = InnoDB AUTO_INCREMENT = 8 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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
) ENGINE = InnoDB AUTO_INCREMENT = 5 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of department
-- ----------------------------
INSERT INTO `department` VALUES (1, 'Computer Science', 'CS');
INSERT INTO `department` VALUES (2, 'Mathematics', 'MATH');
INSERT INTO `department` VALUES (3, 'Business Administration', 'BA');
INSERT INTO `department` VALUES (4, 'Electrical Engineering', 'EE');

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
) ENGINE = InnoDB AUTO_INCREMENT = 12 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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
INSERT INTO `exam` VALUES (8, 5, 1, 1, '2025-04-20', 'midterm', 88.00);
INSERT INTO `exam` VALUES (9, 7, 2, 2, '2025-04-22', 'midterm', 79.00);
INSERT INTO `exam` VALUES (10, 5, 1, 1, '2025-03-15', 'quiz', 95.00);
INSERT INTO `exam` VALUES (11, 6, 3, 4, '2025-10-10', 'quiz', 72.00);

-- ----------------------------
-- Table structure for section
-- ----------------------------
DROP TABLE IF EXISTS `section`;
CREATE TABLE `section`  (
  `section_id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `semester` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '学期（Spring/Fall）',
  `year` year NOT NULL COMMENT '学年',
  `course_id` int(10) UNSIGNED NOT NULL COMMENT '所属课程',
  PRIMARY KEY (`section_id`) USING BTREE,
  UNIQUE INDEX `uq_section`(`course_id` ASC, `semester` ASC, `year` ASC) USING BTREE,
  INDEX `idx_section_course`(`course_id` ASC) USING BTREE,
  INDEX `idx_section_year_sem`(`year` ASC, `semester` ASC) USING BTREE,
  CONSTRAINT `fk_section_course` FOREIGN KEY (`course_id`) REFERENCES `course` (`course_id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE = InnoDB AUTO_INCREMENT = 7 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

-- ----------------------------
-- Records of section
-- ----------------------------
INSERT INTO `section` VALUES (1, 'Spring', 2025, 1);
INSERT INTO `section` VALUES (2, 'Spring', 2025, 2);
INSERT INTO `section` VALUES (3, 'Spring', 2025, 3);
INSERT INTO `section` VALUES (4, 'Fall', 2025, 4);
INSERT INTO `section` VALUES (5, 'Fall', 2025, 5);
INSERT INTO `section` VALUES (6, 'Spring', 2025, 7);

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
INSERT INTO `takes` VALUES (2, 2, 'A-', 'enrolled', '2025-02-19 10:00:00');
INSERT INTO `takes` VALUES (2, 3, NULL, 'enrolled', '2025-02-19 10:05:00');
INSERT INTO `takes` VALUES (3, 4, 'B', 'enrolled', '2025-08-25 08:30:00');
INSERT INTO `takes` VALUES (3, 5, NULL, 'enrolled', '2025-08-25 08:35:00');
INSERT INTO `takes` VALUES (4, 1, 'C+', 'enrolled', '2025-02-20 14:00:00');

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
) ENGINE = InnoDB AUTO_INCREMENT = 9 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = DYNAMIC;

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

SET FOREIGN_KEY_CHECKS = 1;
