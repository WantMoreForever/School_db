/*
 Test Data Script for Teacher Portal
 插入测试数据用于功能验证
 包含教师"Youuy"及其相关班级、学生、成绩、课表数据
 v2: 课表数据新增不同周次范围，便于测试周课表功能
 Date: 2026-03-30
*/

USE school_db;

-- ============================================================
-- 1. 清理旧测试数据（可选）
-- ============================================================
DELETE FROM exam WHERE teacher_id >= 100;
DELETE FROM schedule WHERE section_id >= 100;
DELETE FROM takes WHERE section_id >= 100;
DELETE FROM teaching WHERE teacher_id >= 100;
DELETE FROM section WHERE section_id >= 100;
DELETE FROM advisor WHERE teacher_id >= 100;
DELETE FROM teacher WHERE user_id >= 100;
DELETE FROM student WHERE user_id >= 100;
DELETE FROM user WHERE user_id >= 100;

-- ============================================================
-- 2. 创建测试教师 "Youuy"
-- ============================================================
INSERT INTO user (user_id, name, email, password, phone, gender, image, status, created_at)
VALUES (
    100,
    'Youuy',
    'youuy@university.edu',
    MD5('password123'),
    '13800000001',
    'male',
    'avatar_100.jpg',
    'active',
    NOW()
);

INSERT INTO department (dept_id, dept_name, dept_code)
VALUES (101, 'Computer Science', 'CS')
ON DUPLICATE KEY UPDATE dept_name = VALUES(dept_name);

INSERT INTO teacher (user_id, title, dept_id)
VALUES (100, 'Associate Professor', 101);

-- ============================================================
-- 3. 创建测试课程
-- ============================================================
INSERT IGNORE INTO course (course_id, name, credit, hours, description)
VALUES
(101, 'Data Structures',  3, 48, 'Arrays, linked lists, trees, graphs and their algorithms'),
(102, 'Database Design',  4, 64, 'Relational database design, normalization, SQL, and transactions'),
(103, 'Web Development',  3, 48, 'Full-stack web development with modern frameworks'),
(104, 'Algorithms',       3, 48, 'Algorithm design, complexity analysis, and optimization');

-- ============================================================
-- 4. 创建测试班级（section）
-- ============================================================
INSERT INTO section (section_id, course_id, semester, year, enrollment_start, enrollment_end, capacity)
VALUES
(101, 101, 'Spring', 2026, '2026-01-10', '2026-03-31', 40),
(102, 102, 'Spring', 2026, '2026-01-10', '2026-03-31', 35),
(103, 103, 'Spring', 2026, '2026-01-10', '2026-03-31', 50),
(104, 104, 'Spring', 2026, '2026-01-10', '2026-03-31', 30);

-- ============================================================
-- 5. 分配班级给"Youuy"教师
-- ============================================================
INSERT INTO teaching (teacher_id, section_id)
VALUES
(100, 101),
(100, 102),
(100, 103);

-- ============================================================
-- 6. 创建测试学生
-- ============================================================
INSERT INTO user (user_id, name, email, password, phone, gender, image, status, created_at)
VALUES
(200, 'Alice Johnson',  'alice@student.edu',   MD5('student123'), '13801111111', 'female', NULL, 'active', NOW()),
(201, 'Bob Smith',      'bob@student.edu',     MD5('student123'), '13801111112', 'male',   NULL, 'active', NOW()),
(202, 'Carol White',    'carol@student.edu',   MD5('student123'), '13801111113', 'female', NULL, 'active', NOW()),
(203, 'David Brown',    'david@student.edu',   MD5('student123'), '13801111114', 'male',   NULL, 'active', NOW()),
(204, 'Eve Davis',      'eve@student.edu',     MD5('student123'), '13801111115', 'female', NULL, 'active', NOW()),
(205, 'Frank Wilson',   'frank@student.edu',   MD5('student123'), '13801111116', 'male',   NULL, 'active', NOW()),
(206, 'Grace Lee',      'grace@student.edu',   MD5('student123'), '13801111117', 'female', NULL, 'active', NOW()),
(207, 'Henry Miller',   'henry@student.edu',   MD5('student123'), '13801111118', 'male',   NULL, 'active', NOW()),
(208, 'Iris Chen',      'iris@student.edu',    MD5('student123'), '13801111119', 'female', NULL, 'active', NOW()),
(209, 'Jack Taylor',    'jack@student.edu',    MD5('student123'), '13801111120', 'male',   NULL, 'active', NOW()),
(210, 'Karen Martin',   'karen@student.edu',   MD5('student123'), '13801111121', 'female', NULL, 'active', NOW()),
(211, 'Leo Garcia',     'leo@student.edu',     MD5('student123'), '13801111122', 'male',   NULL, 'active', NOW()),
(212, 'Mia Rodriguez',  'mia@student.edu',     MD5('student123'), '13801111123', 'female', NULL, 'active', NOW()),
(213, 'Noah Hall',      'noah@student.edu',    MD5('student123'), '13801111124', 'male',   NULL, 'active', NOW()),
(214, 'Olivia Young',   'olivia@student.edu',  MD5('student123'), '13801111125', 'female', NULL, 'active', NOW());

INSERT INTO student (user_id, student_no, grade, enrollment_year, dept_id)
VALUES
(200, 'STU202001', 'Sophomore',  2024, 101),
(201, 'STU202002', 'Sophomore',  2024, 101),
(202, 'STU202003', 'Sophomore',  2024, 101),
(203, 'STU202004', 'Sophomore',  2024, 101),
(204, 'STU202005', 'Sophomore',  2024, 101),
(205, 'STU202006', 'Sophomore',  2024, 101),
(206, 'STU202007', 'Sophomore',  2024, 101),
(207, 'STU202008', 'Sophomore',  2024, 101),
(208, 'STU202009', 'Sophomore',  2024, 101),
(209, 'STU202010', 'Sophomore',  2024, 101),
(210, 'STU202011', 'Junior',     2023, 101),
(211, 'STU202012', 'Junior',     2023, 101),
(212, 'STU202013', 'Junior',     2023, 101),
(213, 'STU202014', 'Junior',     2023, 101),
(214, 'STU202015', 'Junior',     2023, 101);

-- ============================================================
-- 7. 学生选课
-- ============================================================
INSERT INTO takes (student_id, section_id, grade, status, enrolled_at)
VALUES
(200, 101, NULL,  'enrolled', '2026-01-12'),
(201, 101, NULL,  'enrolled', '2026-01-12'),
(202, 101, NULL,  'enrolled', '2026-01-12'),
(203, 101, NULL,  'enrolled', '2026-01-12'),
(204, 101, NULL,  'enrolled', '2026-01-12'),
(205, 101, NULL,  'enrolled', '2026-01-12'),
(206, 101, NULL,  'enrolled', '2026-01-12'),
(207, 101, NULL,  'enrolled', '2026-01-12'),
(208, 101, NULL,  'enrolled', '2026-01-12'),
(209, 101, 'B',   'enrolled', '2026-01-12');

INSERT INTO takes (student_id, section_id, grade, status, enrolled_at)
VALUES
(200, 102, NULL,  'enrolled', '2026-01-12'),
(201, 102, NULL,  'enrolled', '2026-01-12'),
(202, 102, NULL,  'enrolled', '2026-01-12'),
(210, 102, NULL,  'enrolled', '2026-01-12'),
(211, 102, NULL,  'enrolled', '2026-01-12'),
(212, 102, NULL,  'enrolled', '2026-01-12'),
(213, 102, NULL,  'enrolled', '2026-01-12'),
(214, 102, 'A-',  'enrolled', '2026-01-12');

INSERT INTO takes (student_id, section_id, grade, status, enrolled_at)
VALUES
(203, 103, NULL,  'enrolled', '2026-01-12'),
(204, 103, NULL,  'enrolled', '2026-01-12'),
(205, 103, NULL,  'enrolled', '2026-01-12'),
(206, 103, NULL,  'enrolled', '2026-01-12'),
(207, 103, NULL,  'enrolled', '2026-01-12'),
(208, 103, NULL,  'enrolled', '2026-01-12'),
(209, 103, 'B+',  'enrolled', '2026-01-12'),
(210, 103, NULL,  'enrolled', '2026-01-12'),
(211, 103, NULL,  'enrolled', '2026-01-12'),
(212, 103, NULL,  'enrolled', '2026-01-12');

-- ============================================================
-- 8. 添加课程表
--    section 101: Data Structures  (全程 week 1-16)
--    section 102: Database Design  (全程 week 1-16)
--    section 103: Web Development  (全程 week 1-16)
--
--    额外安排：
--    101 实验课  仅前半学期 week 1-8
--    102 讨论课  仅后半学期 week 9-16
--    103 项目答辩 仅最后冲刺 week 14-16
--
--    这样测试时：
--    week 1-8   → Data Structures 有讲课+实验两条; Database Design 只有讲课
--    week 9-16  → Data Structures 只有讲课; Database Design 有讲课+讨论
--    week 14-16 → Web Development 额外出现项目答辩
-- ============================================================

-- ── section 101: Data Structures ──────────────
-- 周一 09:00-10:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (101, 1, '09:00:00', '10:30:00', 'Room A101', 1, 16);
-- 周三 14:00-15:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (101, 3, '14:00:00', '15:30:00', 'Room A101', 1, 16);
-- 周五 10:00-11:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (101, 5, '10:00:00', '11:30:00', 'Room A101', 1, 16);
-- 周四 19:00-21:00 实验课 仅 week 1-8 (前半学期)
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (101, 4, '19:00:00', '21:00:00', 'Lab 1', 1, 8);

-- ── section 102: Database Design ──────────────
-- 周二 09:00-10:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (102, 2, '09:00:00', '10:30:00', 'Room B201', 1, 16);
-- 周四 14:00-16:00 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (102, 4, '14:00:00', '16:00:00', 'Room B201', 1, 16);
-- 周五 15:00-16:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (102, 5, '15:00:00', '16:30:00', 'Room B202', 1, 16);
-- 周二 19:00-20:30 讨论课 仅 week 9-16 (后半学期)
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (102, 2, '19:00:00', '20:30:00', 'Seminar Room 3', 9, 16);

-- ── section 103: Web Development ──────────────
-- 周二 10:00-11:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (103, 2, '10:00:00', '11:30:00', 'Room C301', 1, 16);
-- 周四 13:00-14:30 讲课  week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (103, 4, '13:00:00', '14:30:00', 'Room C301', 1, 16);
-- 周六 09:00-10:30 上机练习 week 1-16
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (103, 6, '09:00:00', '10:30:00', 'Lab 2', 1, 16);
-- 周一 19:00-21:00 项目答辩 仅 week 14-16 (冲刺阶段)
INSERT INTO schedule (section_id, day_of_week, start_time, end_time, location, week_start, week_end)
VALUES (103, 1, '19:00:00', '21:00:00', 'Presentation Hall', 14, 16);

-- ============================================================
-- 9. 考试成绩 — section 101: Data Structures
-- ============================================================
INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 101, '2026-03-20', 'final',   88.50),
(100, 201, 101, '2026-03-20', 'final',   92.00),
(100, 202, 101, '2026-03-20', 'final',   75.50),
(100, 203, 101, '2026-03-20', 'final',   85.75),
(100, 204, 101, '2026-03-20', 'final',   93.25),
(100, 205, 101, '2026-03-20', 'final',   78.00),
(100, 206, 101, '2026-03-20', 'final',   87.50),
(100, 207, 101, '2026-03-20', 'final',   80.25),
(100, 208, 101, '2026-03-20', 'final',   91.75),
(100, 209, 101, '2026-03-20', 'final',   86.00);

INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 101, '2026-02-15', 'midterm', 82.00),
(100, 201, 101, '2026-02-15', 'midterm', 89.50),
(100, 202, 101, '2026-02-15', 'midterm', 71.25),
(100, 203, 101, '2026-02-15', 'midterm', 80.75),
(100, 204, 101, '2026-02-15', 'midterm', 88.50),
(100, 205, 101, '2026-02-15', 'midterm', 74.50),
(100, 206, 101, '2026-02-15', 'midterm', 83.75),
(100, 207, 101, '2026-02-15', 'midterm', 77.25),
(100, 208, 101, '2026-02-15', 'midterm', 87.00),
(100, 209, 101, '2026-02-15', 'midterm', 81.50);

INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 101, '2026-01-25', 'quiz', 85.00),
(100, 200, 101, '2026-02-08', 'quiz', 86.00),
(100, 200, 101, '2026-02-22', 'quiz', 87.00),
(100, 201, 101, '2026-01-25', 'quiz', 91.00),
(100, 201, 101, '2026-02-08', 'quiz', 92.00),
(100, 201, 101, '2026-02-22', 'quiz', 90.50),
(100, 202, 101, '2026-01-25', 'quiz', 70.00),
(100, 202, 101, '2026-02-08', 'quiz', 72.00),
(100, 202, 101, '2026-02-22', 'quiz', 73.00),
(100, 203, 101, '2026-01-25', 'quiz', 82.00),
(100, 203, 101, '2026-02-08', 'quiz', 83.00),
(100, 203, 101, '2026-02-22', 'quiz', 84.00),
(100, 204, 101, '2026-01-25', 'quiz', 94.00),
(100, 204, 101, '2026-02-08', 'quiz', 95.00),
(100, 204, 101, '2026-02-22', 'quiz', 93.00),
(100, 205, 101, '2026-01-25', 'quiz', 76.00),
(100, 205, 101, '2026-02-08', 'quiz', 77.50),
(100, 205, 101, '2026-02-22', 'quiz', 78.00),
(100, 206, 101, '2026-01-25', 'quiz', 86.00),
(100, 206, 101, '2026-02-08', 'quiz', 87.50),
(100, 206, 101, '2026-02-22', 'quiz', 88.00),
(100, 207, 101, '2026-01-25', 'quiz', 79.00),
(100, 207, 101, '2026-02-08', 'quiz', 80.00),
(100, 207, 101, '2026-02-22', 'quiz', 81.00),
(100, 208, 101, '2026-01-25', 'quiz', 90.00),
(100, 208, 101, '2026-02-08', 'quiz', 91.00),
(100, 208, 101, '2026-02-22', 'quiz', 92.00),
(100, 209, 101, '2026-01-25', 'quiz', 83.00),
(100, 209, 101, '2026-02-08', 'quiz', 84.00),
(100, 209, 101, '2026-02-22', 'quiz', 85.00);

-- ============================================================
-- 10. 考试成绩 — section 102: Database Design
-- ============================================================
INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 102, '2026-03-20', 'final',   89.50),
(100, 201, 102, '2026-03-20', 'final',   94.00),
(100, 202, 102, '2026-03-20', 'final',   76.00),
(100, 210, 102, '2026-03-20', 'final',   88.75),
(100, 211, 102, '2026-03-20', 'final',   91.50),
(100, 212, 102, '2026-03-20', 'final',   85.25),
(100, 213, 102, '2026-03-20', 'final',   87.00),
(100, 214, 102, '2026-03-20', 'final',   92.75);

INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 102, '2026-02-15', 'midterm', 87.00),
(100, 201, 102, '2026-02-15', 'midterm', 91.50),
(100, 202, 102, '2026-02-15', 'midterm', 72.50),
(100, 210, 102, '2026-02-15', 'midterm', 86.00),
(100, 211, 102, '2026-02-15', 'midterm', 89.75),
(100, 212, 102, '2026-02-15', 'midterm', 82.25),
(100, 213, 102, '2026-02-15', 'midterm', 84.50),
(100, 214, 102, '2026-02-15', 'midterm', 90.00);

INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 200, 102, '2026-01-25', 'quiz', 86.00),
(100, 200, 102, '2026-02-08', 'quiz', 87.50),
(100, 201, 102, '2026-01-25', 'quiz', 92.50),
(100, 201, 102, '2026-02-08', 'quiz', 93.00),
(100, 202, 102, '2026-01-25', 'quiz', 71.00),
(100, 202, 102, '2026-02-08', 'quiz', 72.00),
(100, 210, 102, '2026-01-25', 'quiz', 85.50),
(100, 210, 102, '2026-02-08', 'quiz', 86.50),
(100, 211, 102, '2026-01-25', 'quiz', 90.00),
(100, 211, 102, '2026-02-08', 'quiz', 91.00),
(100, 212, 102, '2026-01-25', 'quiz', 81.00),
(100, 212, 102, '2026-02-08', 'quiz', 82.50),
(100, 213, 102, '2026-01-25', 'quiz', 83.75),
(100, 213, 102, '2026-02-08', 'quiz', 85.00),
(100, 214, 102, '2026-01-25', 'quiz', 91.50),
(100, 214, 102, '2026-02-08', 'quiz', 92.50);

-- ============================================================
-- 11. 考试成绩 — section 103: Web Development
-- ============================================================
INSERT INTO exam (teacher_id, student_id, section_id, exam_date, exam_type, score) VALUES
(100, 203, 103, '2026-03-25', 'final',   84.00),
(100, 204, 103, '2026-03-25', 'final',   90.50),
(100, 205, 103, '2026-03-25', 'final',   77.25),
(100, 206, 103, '2026-03-25', 'final',   88.75),
(100, 207, 103, '2026-03-25', 'final',   81.50),
(100, 208, 103, '2026-03-25', 'final',   89.00),
(100, 209, 103, '2026-03-25', 'final',   85.75),
(100, 210, 103, '2026-03-25', 'final',   87.25),
(100, 211, 103, '2026-03-25', 'final',   83.50),
(100, 212, 103, '2026-03-25', 'final',   92.00);

-- ============================================================
-- 12. 验证数据
-- ============================================================
SELECT '=== Youuy 教师信息 ===' AS info;
SELECT u.name, t.title, d.dept_name FROM user u
JOIN teacher t ON u.user_id = t.user_id
JOIN department d ON t.dept_id = d.dept_id
WHERE u.user_id = 100;

SELECT '=== Youuy 的班级 ===' AS info;
SELECT c.name AS course, s.section_id, s.semester, s.year,
       COUNT(tk.student_id) AS enrolled_count
FROM teaching tg
JOIN section s ON tg.section_id = s.section_id
JOIN course c ON s.course_id = c.course_id
LEFT JOIN takes tk ON s.section_id = tk.section_id
WHERE tg.teacher_id = 100
GROUP BY c.name, s.section_id, s.semester, s.year;

SELECT '=== 课表条目（含周次范围）===' AS info;
SELECT c.name AS course, sch.day_of_week,
       sch.start_time, sch.end_time, sch.location,
       sch.week_start, sch.week_end
FROM teaching tg
JOIN schedule sch ON tg.section_id = sch.section_id
JOIN section sec ON sch.section_id = sec.section_id
JOIN course c ON sec.course_id = c.course_id
WHERE tg.teacher_id = 100
ORDER BY c.name, sch.day_of_week, sch.start_time;

SELECT '=== Week 5 周课表（应含所有三门课常规课时）===' AS info;
CALL sp_get_teacher_weekly_schedule(100, 5);

SELECT '=== Week 10 周课表（101 无实验课、102 有讨论课）===' AS info;
CALL sp_get_teacher_weekly_schedule(100, 10);

SELECT '=== Week 15 周课表（103 出现项目答辩）===' AS info;
CALL sp_get_teacher_weekly_schedule(100, 15);

SELECT '=== 周次范围 ===' AS info;
CALL sp_get_teacher_week_range(100);

SELECT '=== 数据插入完成 ===' AS info;