-- ============================================================
-- 业务验证测试数据（基于 school_db_backup.sql 当前表结构设计）
-- 用途：
-- 1. 为教务系统提供一批更贴近真实业务的验证数据
-- 2. 覆盖正常样例、边界样例、异常样例和规则冲突样例
-- 3. 便于验证选课、公告、排课、成绩、考勤与账号状态逻辑
--
-- 使用建议：
-- - 先导入 school_db_backup.sql 的建表、过程、触发器
-- - 再执行本文件
-- - 本文件使用高位 ID（900000+），尽量避免与现有数据冲突
-- ============================================================

SET NAMES utf8mb4;

-- ============================================================
-- 一、组织与专业
-- [正常样例] 构造两个院系、三个专业，供学生归属、专业限选和公告定向使用
-- ============================================================
INSERT INTO department (dept_id, dept_name, dept_code)
VALUES
    (9001, '测试计算机学院', 'TCS'),
    (9002, '测试电子信息学院', 'TEE')
ON DUPLICATE KEY UPDATE
    dept_name = VALUES(dept_name),
    dept_code = VALUES(dept_code);

INSERT INTO major (major_id, major_name, major_code, dept_id)
VALUES
    (9101, '测试软件工程', 'TSWE', 9001),
    (9102, '测试人工智能', 'TAI', 9001),
    (9103, '测试电子信息', 'TEEI', 9002)
ON DUPLICATE KEY UPDATE
    major_name = VALUES(major_name),
    major_code = VALUES(major_code),
    dept_id = VALUES(dept_id);

-- ============================================================
-- 二、账号、管理员、教师、学生
-- [正常样例] 活跃管理员、教师、学生
-- [异常样例] inactive / banned 账号
-- [前置样例] 默认密码 123456 的“重置密码后”账号（是否首次登录强制改密依赖应用层）
-- ============================================================
INSERT INTO user (user_id, name, email, password, status, gender, phone, image)
VALUES
    (900001, '测试超管', 'qa.superadmin@school.edu', '123456', 'active', 'male', '13990000001', NULL),
    (900002, '测试教务管理员', 'qa.admin@school.edu', '123456', 'active', 'female', '13990000002', NULL),
    (900101, '林致远', 'lin.zhiyuan@school.edu', '123456', 'active', 'male', '13990000101', NULL),
    (900102, '周若桐', 'zhou.ruotong@school.edu', '123456', 'active', 'female', '13990000102', NULL),
    (900103, '陈景行', 'chen.jingxing@school.edu', '123456', 'active', 'male', '13990000103', NULL),
    (900104, '许安澜', 'xu.anlan@school.edu', '123456', 'inactive', 'female', '13990000104', NULL),
    (900201, '沈星河', 'shen.xinghe.test@school.edu', '123456', 'active', 'male', '13990000201', NULL),
    (900202, '顾明远', 'gu.mingyuan.test@school.edu', '123456', 'active', 'male', '13990000202', NULL),
    (900203, '何语汐', 'he.yuxi.test@school.edu', '123456', 'active', 'female', '13990000203', NULL),
    (900204, '宋知夏', 'song.zhixia.test@school.edu', '123456', 'active', 'female', '13990000204', NULL),
    (900205, '唐沐阳', 'tang.muyang.test@school.edu', '123456', 'inactive', 'male', '13990000205', NULL),
    (900206, '温清禾', 'wen.qinghe.test@school.edu', '123456', 'banned', 'female', '13990000206', NULL),
    (900207, '陆嘉宁', 'lu.jianing.test@school.edu', '123456', 'active', 'female', '13990000207', NULL),
    (900208, '苏文岚', 'su.wenlan.test@school.edu', '123456', 'active', 'female', '13990000208', NULL),
    (900209, '乔以安', 'qiao.yian.test@school.edu', '123456', 'active', 'male', '13990000209', NULL)
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
    (900001, 'super_admin'),
    (900002, 'admin')
ON DUPLICATE KEY UPDATE
    role = VALUES(role);

INSERT INTO teacher (user_id, title, dept_id)
VALUES
    (900101, '教授', 9001),
    (900102, '讲师', 9001),
    (900103, '副教授', 9001),
    (900104, '讲师', 9002)
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    dept_id = VALUES(dept_id);

INSERT INTO student (user_id, student_no, grade, enrollment_year, dept_id, major_id)
VALUES
    (900201, '92002001', '大二', 2024, 9001, 9101),
    (900202, '92002002', '大二', 2024, 9001, 9101),
    (900203, '92002003', '大三', 2023, 9001, 9102),
    (900204, '92002004', '大二', 2024, 9002, 9103),
    (900205, '92002005', '大一', 2025, 9001, 9101),
    (900206, '92002006', '大三', 2023, 9001, 9102),
    (900207, '92002007', '大二', 2024, 9001, 9101),
    (900208, '92002008', '大三', 2023, 9001, 9102),
    (900209, '92002009', '大一', 2025, 9001, 9101)
ON DUPLICATE KEY UPDATE
    student_no = VALUES(student_no),
    grade = VALUES(grade),
    enrollment_year = VALUES(enrollment_year),
    dept_id = VALUES(dept_id),
    major_id = VALUES(major_id);

INSERT IGNORE INTO advisor (teacher_id, student_id)
VALUES
    (900101, 900201),
    (900101, 900202),
    (900103, 900203),
    (900102, 900207);

-- ============================================================
-- 三、课程与教室
-- [正常样例] 课程、教室基础主数据
-- ============================================================
INSERT INTO course (course_id, name, credit, hours, description)
VALUES
    (9201, '测试-数据结构', 3.0, 48, '用于验证选课、成绩、考勤、公告班级范围的核心课程'),
    (9202, '测试-操作系统', 4.0, 64, '用于验证与数据结构课程的选课时间冲突'),
    (9203, '测试-人工智能导论', 3.0, 48, '用于验证专业限制与教师班级公告可见性'),
    (9204, '测试-数字电路', 3.5, 56, '用于验证教室占用冲突'),
    (9205, '测试-数据库原理', 3.0, 48, '用于验证教师课表冲突'),
    (9206, '测试-软件测试', 2.0, 32, '用于验证课程人数已满'),
    (9207, '测试-计算机网络', 3.0, 48, '用于验证开放但未满的普通选课场景')
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    credit = VALUES(credit),
    hours = VALUES(hours),
    description = VALUES(description);

INSERT INTO classroom (classroom_id, building, room_number, capacity, type)
VALUES
    (9501, '测试一教', '101', 60, 'normal'),
    (9502, '测试一教', '202', 45, 'multimedia'),
    (9503, '测试实验楼', '301', 40, 'lab'),
    (9504, '测试实验楼', '302', 35, 'lab'),
    (9505, '测试综合楼', '501', 20, 'multimedia')
ON DUPLICATE KEY UPDATE
    building = VALUES(building),
    room_number = VALUES(room_number),
    capacity = VALUES(capacity),
    type = VALUES(type);

-- ============================================================
-- 四、开课节、任教、专业限制、排课
-- [正常样例] 9301 / 9302 / 9303 / 9306 / 9307
-- [冲突样例] 9304（教室冲突）、9305（教师课表冲突）
-- ============================================================
INSERT INTO section (section_id, semester, year, course_id, enrollment_start, enrollment_end, capacity)
VALUES
    (9301, 'Fall', 2026, 9201, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 40),
    (9302, 'Fall', 2026, 9202, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 35),
    (9303, 'Fall', 2026, 9203, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 30),
    (9304, 'Fall', 2026, 9204, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 30),
    (9305, 'Fall', 2026, 9205, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 40),
    (9306, 'Fall', 2026, 9206, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 2),
    (9307, 'Fall', 2026, 9207, '2026-08-20 08:00:00', '2026-09-15 23:59:59', 25)
ON DUPLICATE KEY UPDATE
    enrollment_start = VALUES(enrollment_start),
    enrollment_end = VALUES(enrollment_end),
    capacity = VALUES(capacity);

INSERT IGNORE INTO teaching (teacher_id, section_id)
VALUES
    (900101, 9301),
    (900102, 9302),
    (900103, 9303),
    (900104, 9304),
    (900103, 9305),
    (900102, 9306),
    (900101, 9307);

-- [正常样例] 人工智能导论仅允许测试人工智能专业选课
INSERT IGNORE INTO restriction (section_id, major_id)
VALUES
    (9303, 9102);

INSERT INTO schedule (schedule_id, section_id, day_of_week, start_time, end_time, classroom_id, week_start, week_end)
VALUES
    -- [正常样例] 数据结构：周一 08:00-09:40
    (9401, 9301, 1, '08:00:00', '09:40:00', 9501, 1, 16),
    -- [冲突样例] 与 9401 时间重叠，供学生选课冲突验证
    (9402, 9302, 1, '09:00:00', '10:40:00', 9502, 1, 16),
    -- [正常样例] AI 导论
    (9403, 9303, 2, '10:00:00', '11:40:00', 9503, 1, 16),
    -- [冲突样例] 与 9401 同教室、同时间段重叠，供教室冲突验证
    (9404, 9304, 1, '08:30:00', '10:10:00', 9501, 1, 16),
    -- [冲突样例] 与 9403 为同一教师且时间重叠，供教师课表冲突验证
    (9405, 9305, 2, '10:30:00', '12:10:00', 9504, 1, 16),
    -- [正常样例] 软件测试，小班容量课程
    (9406, 9306, 3, '14:00:00', '15:40:00', 9505, 1, 16),
    -- [正常样例] 普通开放课程
    (9407, 9307, 4, '16:00:00', '17:40:00', 9502, 1, 16)
ON DUPLICATE KEY UPDATE
    section_id = VALUES(section_id),
    day_of_week = VALUES(day_of_week),
    start_time = VALUES(start_time),
    end_time = VALUES(end_time),
    classroom_id = VALUES(classroom_id),
    week_start = VALUES(week_start),
    week_end = VALUES(week_end);

-- ============================================================
-- 五、选课数据
-- [正常样例] 学生已选课程
-- [边界样例] 9306 容量=2 且已满
-- [规则验证前置] 900201 选了 9301，之后尝试 9302 会产生时间冲突
-- [规则验证前置] 900204 是电子信息专业，尝试 9303 会因 restriction 被拦截
-- ============================================================
INSERT IGNORE INTO takes (student_id, section_id, grade, enrolled_at)
VALUES
    (900201, 9301, NULL, '2026-08-21 09:00:00'),
    (900202, 9301, NULL, '2026-08-21 09:05:00'),
    (900203, 9303, NULL, '2026-08-21 09:10:00'),
    (900204, 9304, NULL, '2026-08-21 09:15:00'),
    (900207, 9306, NULL, '2026-08-21 09:20:00'),
    (900208, 9306, NULL, '2026-08-21 09:25:00'),
    (900207, 9301, NULL, '2026-08-21 09:30:00'),
    (900208, 9301, NULL, '2026-08-21 09:35:00'),
    (900209, 9307, NULL, '2026-08-21 09:40:00');

-- ============================================================
-- 六、成绩与考试
-- [边界样例] 0 / 59 / 60 / 100
-- 用途：验证分数边界、成绩分布、及格线与字母成绩映射
-- ============================================================
INSERT INTO exam (exam_id, teacher_id, student_id, section_id, exam_date, exam_type, score)
VALUES
    (9701, 900101, 900201, 9301, '2026-12-20', 'final', 0.00),
    (9702, 900101, 900202, 9301, '2026-12-20', 'final', 59.00),
    (9703, 900101, 900207, 9301, '2026-12-20', 'final', 60.00),
    (9704, 900101, 900208, 9301, '2026-12-20', 'final', 100.00)
ON DUPLICATE KEY UPDATE
    teacher_id = VALUES(teacher_id),
    student_id = VALUES(student_id),
    section_id = VALUES(section_id),
    exam_date = VALUES(exam_date),
    exam_type = VALUES(exam_type),
    score = VALUES(score);

UPDATE takes
SET grade = CASE student_id
    WHEN 900201 THEN 'F'
    WHEN 900202 THEN 'F'
    WHEN 900207 THEN 'D'
    WHEN 900208 THEN 'A'
    ELSE grade
END
WHERE section_id = 9301
  AND student_id IN (900201, 900202, 900207, 900208);

-- ============================================================
-- 七、考勤数据
-- [正常/边界样例] 同一节课下四种不同考勤状态
-- ============================================================
INSERT INTO attendance (attendance_id, schedule_id, student_id, week, status, note, recorded_by)
VALUES
    (9801, 9401, 900201, 3, 'present', '正常到课', 900101),
    (9802, 9401, 900202, 3, 'absent', '请假未到', 900101),
    (9803, 9401, 900207, 3, 'late', '迟到 10 分钟', 900101),
    (9804, 9401, 900208, 3, 'excused', '校级竞赛请假', 900101)
ON DUPLICATE KEY UPDATE
    status = VALUES(status),
    note = VALUES(note),
    recorded_by = VALUES(recorded_by);

-- ============================================================
-- 八、公告与公告目标
-- [正常样例] all / students / teachers / major / section
-- [异常样例] draft 状态公告（不应被已发布列表看见）
-- 用途：验证公告可见范围限制
-- ============================================================
INSERT INTO announcement
    (announcement_id, author_user_id, title, content, status, is_pinned, published_at, created_at, updated_at)
VALUES
    (9601, 900001, '【测试】全校系统维护公告', '面向所有用户的全体公告。', 'published', 1, '2026-09-01 08:00:00', '2026-09-01 08:00:00', '2026-09-01 08:00:00'),
    (9602, 900001, '【测试】学生事务提醒', '仅学生端应可见。', 'published', 0, '2026-09-02 09:00:00', '2026-09-02 09:00:00', '2026-09-02 09:00:00'),
    (9603, 900001, '【测试】教师排课提醒', '仅教师端应可见。', 'published', 0, '2026-09-03 10:00:00', '2026-09-03 10:00:00', '2026-09-03 10:00:00'),
    (9604, 900001, '【测试】人工智能专业定向公告', '仅 major_id=9102 的学生应可见。', 'published', 0, '2026-09-04 11:00:00', '2026-09-04 11:00:00', '2026-09-04 11:00:00'),
    (9605, 900101, '【测试】数据结构班级通知', '仅已选 9301 班级的学生与任课教师应可见。', 'published', 0, '2026-09-05 12:00:00', '2026-09-05 12:00:00', '2026-09-05 12:00:00'),
    (9606, 900001, '【测试】草稿公告', '这是一条未发布公告，用于验证草稿不可见。', 'draft', 0, NULL, '2026-09-06 13:00:00', '2026-09-06 13:00:00'),
    (9607, 900001, '【测试】AI 导论班级公告', '仅已选 9303 的学生与任课教师应可见。', 'published', 0, '2026-09-07 14:00:00', '2026-09-07 14:00:00', '2026-09-07 14:00:00')
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
    (9901, 9601, 'all', 0),
    (9902, 9602, 'students', 0),
    (9903, 9603, 'teachers', 0),
    (9904, 9604, 'major', 9102),
    (9905, 9605, 'section', 9301),
    (9906, 9606, 'all', 0),
    (9907, 9607, 'section', 9303)
ON DUPLICATE KEY UPDATE
    announcement_id = VALUES(announcement_id),
    target_type = VALUES(target_type),
    target_id = VALUES(target_id);

-- ============================================================
-- 九、场景提示（执行后可直接验证）
-- 1. [正常样例] 900203（AI 专业）查看公告，应能看到 9601/9602/9604/9607
-- 2. [正常样例] 900201（软件工程）查看公告，应能看到 9601/9602/9605，不应看到 9604/9603/9607
-- 3. [冲突样例] 900201 已选 9301，再尝试选 9302，应触发时间冲突
-- 4. [边界样例] 9306 容量=2 且已被 900207/900208 占满，其他活跃学生尝试选课应提示已满
-- 5. [规则样例] 900204 为电子信息专业，尝试选择 9303 应被专业限制拦截
-- 6. [冲突样例] 9404 与 9401 同教室时间重叠；9405 与 9403 为同一教师时间重叠
-- 7. [边界样例] 9701~9704 分别覆盖分数 0/59/60/100
-- 8. [异常样例] 900205=inactive、900206=banned、900104=inactive，可用于验证账号状态限制
-- 9. [前置样例] 900209 密码为默认值 123456，可用于“重置密码后默认密码登录”验证；
--    但“首次登录必须改密”是否生效，取决于应用层逻辑，当前 schema 无独立标志字段
-- ============================================================
