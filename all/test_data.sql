-- ============================================================
-- 教师门户 测试数据
-- 执行前请确保已运行 db.sql 建好表结构
-- 执行方式：mysql -u root -p school_db < test_data.sql
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 清空所有数据（保留表结构），按外键依赖顺序
TRUNCATE TABLE exam;
TRUNCATE TABLE takes;
TRUNCATE TABLE schedule;
TRUNCATE TABLE teaching;
TRUNCATE TABLE advisor;
TRUNCATE TABLE restriction;
TRUNCATE TABLE section;
TRUNCATE TABLE course;
TRUNCATE TABLE admin;
TRUNCATE TABLE student;
TRUNCATE TABLE teacher;
TRUNCATE TABLE user;
TRUNCATE TABLE department;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 1. 院系
-- ============================================================
INSERT INTO department (dept_id, dept_name, dept_code) VALUES
(1, '计算机学院',     'CS'),
(2, '数学与统计学院', 'MATH'),
(3, '物理学院',       'PHY');

-- ============================================================
-- 2. 用户（password 明文，JiaoWu 直接比对字符串）
-- ============================================================
INSERT INTO user (user_id, name, email, password, status, gender, phone) VALUES
-- 教师
(1, '张伟',   'teacher@school.edu',  '123456', 'active', 'male',   '13800000001'),
-- 学生
(2, '李明',   'student1@school.edu', '123456', 'active', 'male',   '13800000002'),
(3, '王芳',   'student2@school.edu', '123456', 'active', 'female', '13800000003'),
(4, '陈晓',   'student3@school.edu', '123456', 'active', 'male',   '13800000004'),
(5, '刘洋',   'student4@school.edu', '123456', 'active', 'female', '13800000005'),
(6, '赵磊',   'student5@school.edu', '123456', 'active', 'male',   '13800000006'),
-- 管理员
(7, '系统管理员', 'admin@school.edu', '123456', 'active', 'other', NULL);

-- ============================================================
-- 3. 教师（关联 user + department）
-- ============================================================
INSERT INTO teacher (user_id, title, dept_id) VALUES
(1, '副教授', 1);

-- ============================================================
-- 4. 学生
-- ============================================================
INSERT INTO student (user_id, student_no, grade, enrollment_year, dept_id) VALUES
(2, 'S20210001', '大三', 2021, 1),
(3, 'S20210002', '大三', 2021, 2),
(4, 'S20220001', '大二', 2022, 1),
(5, 'S20220002', '大二', 2022, 3),
(6, 'S20220003', '大二', 2022, 1);

-- ============================================================
-- 5. 管理员
-- ============================================================
INSERT INTO admin (user_id, role) VALUES
(7, 'admin');

-- ============================================================
-- 6. 课程
-- ============================================================
INSERT INTO course (course_id, name, credit, hours, description) VALUES
(1, '数据结构',   3.0, 48, '学习线性表、树、图等基础数据结构及常用算法'),
(2, '操作系统',   3.0, 48, '进程管理、内存管理、文件系统等核心概念'),
(3, '数据库原理', 3.0, 48, 'SQL、关系代数、事务与并发控制'),
(4, '计算机网络', 2.0, 32, 'TCP/IP 协议栈及网络应用原理'),
(5, '离散数学',   2.0, 32, '集合论、图论、逻辑与组合数学');

-- ============================================================
-- 7. 开课节（section）
-- ============================================================
INSERT INTO section (section_id, semester, year, course_id, capacity, enrollment_start, enrollment_end) VALUES
(1, 'Spring', 2026, 1, 40, '2026-01-01 00:00:00', '2026-03-01 00:00:00'),
(2, 'Spring', 2026, 3, 35, '2026-01-01 00:00:00', '2026-03-01 00:00:00'),
(3, 'Fall',   2025, 2, 30, '2025-07-01 00:00:00', '2025-09-01 00:00:00'),
(4, 'Spring', 2026, 4, 45, '2026-01-01 00:00:00', '2026-03-01 00:00:00');

-- ============================================================
-- 8. 教学任务
-- ============================================================
INSERT INTO teaching (teacher_id, section_id) VALUES
(1, 1),
(1, 2),
(1, 3);

-- ============================================================
-- 9. 选课记录
-- ============================================================
INSERT INTO takes (student_id, section_id, grade, status, enrolled_at) VALUES
-- 数据结构（section 1）
(2, 1, 'A',  'enrolled', '2026-01-10 09:00:00'),
(3, 1, 'B+', 'enrolled', '2026-01-10 09:05:00'),
(4, 1, NULL, 'enrolled', '2026-01-10 09:10:00'),
(5, 1, NULL, 'pending',  '2026-01-11 10:00:00'),
(6, 1, NULL, 'pending',  '2026-01-11 10:30:00'),
-- 数据库原理（section 2）
(2, 2, 'A-', 'enrolled', '2026-01-10 09:20:00'),
(3, 2, 'B',  'enrolled', '2026-01-10 09:25:00'),
(4, 2, NULL, 'enrolled', '2026-01-10 09:30:00'),
-- 操作系统（section 3，历史数据）
(2, 3, 'A',  'enrolled', '2025-07-15 08:00:00'),
(3, 3, 'B+', 'enrolled', '2025-07-15 08:05:00'),
(5, 3, 'C+', 'enrolled', '2025-07-15 08:10:00');

-- ============================================================
-- 10. 考试记录
-- ============================================================
INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
-- 数据结构（section 1）
(1, 2, 1, '2026-03-10', 'quiz',    88.0),
(1, 3, 1, '2026-03-10', 'quiz',    79.5),
(1, 4, 1, '2026-03-10', 'quiz',    91.0),
(1, 2, 1, '2026-04-15', 'midterm', 85.0),
(1, 3, 1, '2026-04-15', 'midterm', 76.0),
(1, 4, 1, '2026-04-15', 'midterm', 88.5),
(1, 2, 1, '2026-06-20', 'final',   92.0),
(1, 3, 1, '2026-06-20', 'final',   80.0),
(1, 4, 1, '2026-06-20', 'final',   87.0),
-- 数据库原理（section 2）
(1, 2, 2, '2026-03-12', 'quiz',    95.0),
(1, 3, 2, '2026-03-12', 'quiz',    82.0),
(1, 4, 2, '2026-03-12', 'quiz',    78.0),
(1, 2, 2, '2026-04-18', 'midterm', 90.0),
(1, 3, 2, '2026-04-18', 'midterm', 84.0),
(1, 4, 2, '2026-04-18', 'midterm', 71.0),
(1, 2, 2, '2026-06-22', 'final',   93.0),
(1, 3, 2, '2026-06-22', 'final',   85.0),
-- 操作系统（section 3，历史）
(1, 2, 3, '2025-11-20', 'final',   94.0),
(1, 3, 3, '2025-11-20', 'final',   83.0),
(1, 5, 3, '2025-11-20', 'final',   71.0);

-- ============================================================
-- 11. 课程表
-- ============================================================
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end) VALUES
-- 数据结构（section 1）：周一、周三 08:00-09:40
(1, 1, '08:00:00', '09:40:00', '教学楼A-101', 1, 16),
(1, 3, '08:00:00', '09:40:00', '教学楼A-101', 1, 16),
-- 数据库原理（section 2）：周二、周四 10:00-11:40
(2, 2, '10:00:00', '11:40:00', '实验楼B-201', 1, 16),
(2, 4, '10:00:00', '11:40:00', '实验楼B-201', 1, 16),
-- 操作系统（section 3）：周五 14:00-16:40
(3, 5, '14:00:00', '16:40:00', '教学楼C-305', 1, 16);

-- ============================================================
-- 12. 导师关系
-- ============================================================
INSERT INTO advisor (teacher_id, student_id) VALUES
(1, 2),
(1, 3);

-- ============================================================
-- 验证结果
-- ============================================================
SELECT '=== 数据插入完成 ===' AS '';
SELECT CONCAT('department: ', COUNT(*), ' 条') AS 统计 FROM department
UNION ALL SELECT CONCAT('user: ',       COUNT(*), ' 条') FROM user
UNION ALL SELECT CONCAT('teacher: ',    COUNT(*), ' 条') FROM teacher
UNION ALL SELECT CONCAT('student: ',    COUNT(*), ' 条') FROM student
UNION ALL SELECT CONCAT('course: ',     COUNT(*), ' 条') FROM course
UNION ALL SELECT CONCAT('section: ',    COUNT(*), ' 条') FROM section
UNION ALL SELECT CONCAT('teaching: ',   COUNT(*), ' 条') FROM teaching
UNION ALL SELECT CONCAT('takes: ',      COUNT(*), ' 条') FROM takes
UNION ALL SELECT CONCAT('exam: ',       COUNT(*), ' 条') FROM exam
UNION ALL SELECT CONCAT('schedule: ',   COUNT(*), ' 条') FROM schedule
UNION ALL SELECT CONCAT('advisor: ',    COUNT(*), ' 条') FROM advisor;