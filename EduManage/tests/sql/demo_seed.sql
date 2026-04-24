-- ============================================================
-- 演示数据种子（唯一演示 seed）
-- 用途：
-- 1. 配合 school_db_backup.sql 提供稳定、最小、可登录的演示链路
-- 2. 只覆盖答辩、部署和 smoke 所需的基础账号与业务数据
-- 3. 不承担 QA 边界样例或异常样例职责
--
-- 官方导入顺序：
-- 1. 导入 school_db_backup.sql
-- 2. 导入 tests/sql/demo_seed.sql
-- ============================================================

SET NAMES utf8mb4;

INSERT INTO department (dept_id, dept_name, dept_code)
VALUES
    (8101, '演示计算机学院', 'DEMOCS')
ON DUPLICATE KEY UPDATE
    dept_name = VALUES(dept_name),
    dept_code = VALUES(dept_code);

INSERT INTO major (major_id, major_name, major_code, dept_id)
VALUES
    (8201, '演示软件工程', 'DEMOSWE', 8101)
ON DUPLICATE KEY UPDATE
    major_name = VALUES(major_name),
    major_code = VALUES(major_code),
    dept_id = VALUES(dept_id);

INSERT INTO user (user_id, name, email, password, status, gender, phone, image)
VALUES
    (810001, '演示超级管理员', 'admin@school.edu', '123456', 'active', 'other', '13881000001', NULL),
    (810101, '演示教师', 'teacher@school.edu', '123456', 'active', 'male', '13881000101', NULL),
    (810201, '演示学生', 'student@school.edu', '123456', 'active', 'female', '13881000201', NULL)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    email = VALUES(email),
    password = VALUES(password),
    status = VALUES(status),
    gender = VALUES(gender),
    phone = VALUES(phone),
    image = VALUES(image);

INSERT INTO admin (user_id, role)
VALUES
    (810001, 'super_admin')
ON DUPLICATE KEY UPDATE
    role = VALUES(role);

INSERT INTO teacher (user_id, title, dept_id)
VALUES
    (810101, '副教授', 8101)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    dept_id = VALUES(dept_id);

INSERT INTO student (user_id, student_no, grade, enrollment_year, dept_id, major_id)
VALUES
    (810201, '81002001', '大三', 2024, 8101, 8201)
ON DUPLICATE KEY UPDATE
    student_no = VALUES(student_no),
    grade = VALUES(grade),
    enrollment_year = VALUES(enrollment_year),
    dept_id = VALUES(dept_id),
    major_id = VALUES(major_id);

INSERT INTO advisor (teacher_id, student_id)
VALUES
    (810101, 810201)
ON DUPLICATE KEY UPDATE
    student_id = VALUES(student_id);

INSERT INTO course (course_id, name, credit, hours, description)
VALUES
    (8301, '演示-数据库原理', 3.0, 48, '用于演示管理员、教师、学生三端的最小闭环课程')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    credit = VALUES(credit),
    hours = VALUES(hours),
    description = VALUES(description);

INSERT INTO classroom (classroom_id, building, room_number, capacity, type)
VALUES
    (8401, '演示教学楼', '101', 60, 'multimedia')
ON DUPLICATE KEY UPDATE
    building = VALUES(building),
    room_number = VALUES(room_number),
    capacity = VALUES(capacity),
    type = VALUES(type);

INSERT INTO section (section_id, semester, year, course_id, enrollment_start, enrollment_end, capacity)
VALUES
    (8501, 'Spring', 2026, 8301, '2026-02-17 08:00:00', '2026-05-31 23:59:59', 40)
ON DUPLICATE KEY UPDATE
    semester = VALUES(semester),
    year = VALUES(year),
    course_id = VALUES(course_id),
    enrollment_start = VALUES(enrollment_start),
    enrollment_end = VALUES(enrollment_end),
    capacity = VALUES(capacity);

INSERT INTO teaching (teacher_id, section_id)
VALUES
    (810101, 8501)
ON DUPLICATE KEY UPDATE
    section_id = VALUES(section_id);

INSERT INTO schedule (schedule_id, section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
VALUES
    (8601, 8501, 2, '08:00:00', '09:40:00', 8401, 1, 16)
ON DUPLICATE KEY UPDATE
    section_id = VALUES(section_id),
    day_of_week = VALUES(day_of_week),
    start_time = VALUES(start_time),
    end_time = VALUES(end_time),
    classroom_id = VALUES(classroom_id),
    week_start = VALUES(week_start),
    week_end = VALUES(week_end);

INSERT INTO takes (student_id, section_id, grade, enrolled_at)
VALUES
    (810201, 8501, 'A', '2026-02-20 09:00:00')
ON DUPLICATE KEY UPDATE
    grade = VALUES(grade),
    enrolled_at = VALUES(enrolled_at);

INSERT INTO exam (exam_id, teacher_id, student_id, section_id, exam_date, exam_type, score)
VALUES
    (8701, 810101, 810201, 8501, '2026-03-20', 'quiz', 88.00),
    (8702, 810101, 810201, 8501, '2026-04-18', 'midterm', 91.00),
    (8703, 810101, 810201, 8501, '2026-06-20', 'final', 95.00)
ON DUPLICATE KEY UPDATE
    teacher_id = VALUES(teacher_id),
    student_id = VALUES(student_id),
    section_id = VALUES(section_id),
    exam_date = VALUES(exam_date),
    exam_type = VALUES(exam_type),
    score = VALUES(score);

INSERT INTO attendance (attendance_id, schedule_id, student_id, week, status, note, recorded_by)
VALUES
    (8801, 8601, 810201, 3, 'present', '演示样例：正常到课', 810101)
ON DUPLICATE KEY UPDATE
    schedule_id = VALUES(schedule_id),
    student_id = VALUES(student_id),
    week = VALUES(week),
    status = VALUES(status),
    note = VALUES(note),
    recorded_by = VALUES(recorded_by);

INSERT INTO announcement (announcement_id, author_user_id, title, content, status, is_pinned, published_at, created_at, updated_at)
VALUES
    (8901, 810001, '演示系统公告', '这是一条用于部署、答辩和 smoke 的演示公告。', 'published', 1, '2026-04-01 09:00:00', '2026-04-01 09:00:00', '2026-04-01 09:00:00'),
    (8902, 810101, '演示课程公告', '教师端和学生端都应能看到这条班级公告。', 'published', 0, '2026-04-05 10:00:00', '2026-04-05 10:00:00', '2026-04-05 10:00:00')
ON DUPLICATE KEY UPDATE
    author_user_id = VALUES(author_user_id),
    title = VALUES(title),
    content = VALUES(content),
    status = VALUES(status),
    is_pinned = VALUES(is_pinned),
    published_at = VALUES(published_at),
    updated_at = VALUES(updated_at);

INSERT INTO announcement_target (target_row_id, announcement_id, target_type, target_id)
VALUES
    (8911, 8901, 'all', 0),
    (8912, 8902, 'section', 8501)
ON DUPLICATE KEY UPDATE
    announcement_id = VALUES(announcement_id),
    target_type = VALUES(target_type),
    target_id = VALUES(target_id);
