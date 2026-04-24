# EduManage 业务验证测试数据说明

本文档对应 `tests/sql/qa_business_validation_seed.sql`，用于说明这批 QA / 业务验证测试数据的设计目的、关系结构和可验证场景。该文件不是演示 seed。

## 1. 表关系摘要

核心实体关系如下：

- `user` 是所有账号主表，`admin`、`teacher`、`student` 都通过 `user_id` 关联到 `user.user_id`。
- `department` 下面挂 `major`；`teacher.dept_id`、`student.dept_id`、`student.major_id` 分别指向所属院系和专业。
- `course` 通过 `section` 形成具体学期开课班级；`section` 通过 `teaching` 绑定教师，通过 `schedule` 绑定上课时间和教室。
- `takes` 表示学生选了哪个 `section`，也是成绩、公告班级范围和考勤的基础关联表。
- `restriction(section_id, major_id)` 决定某个开课班级是否限制特定专业选修。
- `announcement` + `announcement_target` 决定公告对 `all / students / teachers / major / section` 的可见范围。
- `exam` 绑定 `teacher + student + section + exam_date`，用于成绩边界验证。
- `attendance` 绑定 `schedule + student + week`，用于考勤状态验证。

## 2. 约束与测试设计关注点

数据库层关键约束：

- `user.email` 唯一，且触发器要求必须是合法邮箱并以 `@school.edu` 结尾。
- `user.phone` 唯一；若填写则必须是 11 位数字。
- `student.student_no` 必须是 8 位数字，且唯一。
- `department.dept_code`、`major.major_code` 必须是 1-10 位大写字母。
- `section(course_id, semester, year)` 唯一。
- `classroom(building, room_number)` 唯一。
- `takes(student_id, section_id)`、`teaching(teacher_id, section_id)`、`restriction(section_id, major_id)` 都是组合主键。
- `attendance(schedule_id, student_id, week)` 唯一。
- `exam.score` 触发器限制在 `0 ~ 100`。
- `announcement.status` 必须是 `draft / published / archived`。
- `announcement_target.target_type` 必须是 `all / students / teachers / major / section`。

本批测试数据严格保持外键正确，不构造“无法插入”的脏数据；冲突样例主要体现为“业务上不应允许，但数据库结构本身允许插入”的场景，例如教师课表冲突、教室占用冲突。

## 3. 测试数据分组

### 3.1 正常样例

| 组别 | 关键记录 | 测试目的 |
| --- | --- | --- |
| 组织与专业 | `department` 9001/9002，`major` 9101/9102/9103 | 验证院系、专业、专业限选和公告定向 |
| 正常教师与学生 | `teacher` 900101~900103，`student` 900201~900204、900207~900209 | 验证登录、选课、成绩、公告、考勤 |
| 普通开课节 | `section` 9301/9302/9303/9306/9307 | 验证正常开课、正常排课、正常选课 |
| 普通公告 | `announcement` 9601/9602/9603/9604/9605/9607 | 验证 all / students / teachers / major / section 可见范围 |

### 3.2 边界样例

| 组别 | 关键记录 | 测试目的 |
| --- | --- | --- |
| 课程容量边界 | `section` 9306，`takes` 中 900207/900208 已选满 | 验证“课程人数已满” |
| 成绩边界 | `exam` 9701~9704 | 覆盖 `0 / 59 / 60 / 100` |
| 考勤状态边界 | `attendance` 9801~9804 | 覆盖 `present / absent / late / excused` |

### 3.3 冲突样例

| 组别 | 关键记录 | 测试目的 |
| --- | --- | --- |
| 学生选课时间冲突 | 学生 900201 已选 `9301`，待尝试 `9302` | 验证选课时间重叠拦截 |
| 教室占用冲突 | `schedule` 9401 与 9404 | 验证同一教室同时间段冲突 |
| 教师课表冲突 | `schedule` 9403 与 9405，教师都是 900103 | 验证同一教师同时间段冲突 |
| 专业限制冲突 | `restriction(9303, 9102)`，学生 900204 专业=9103 | 验证非限制专业不可选 |

### 3.4 异常样例

| 组别 | 关键记录 | 测试目的 |
| --- | --- | --- |
| 账号异常 | `user.status` = `inactive` 的 900104 / 900205；`banned` 的 900206 | 验证异常账号无法正常使用业务功能 |
| 公告异常 | `announcement` 9606 状态为 `draft` | 验证草稿公告不进入已发布列表 |

## 4. 推荐验证路径

### 4.1 选课相关

- 学生 `900201` 已选 `9301（测试-数据结构）`，再尝试选 `9302（测试-操作系统）`，应触发时间冲突。
- 学生 `900204`（专业 `9103`）尝试选 `9303（测试-人工智能导论）`，应因 `restriction` 被阻止。
- 学生 `900201` 或 `900202` 尝试选 `9306（测试-软件测试）`，应因容量已满失败。

### 4.2 公告相关

- 学生 `900203`（AI 专业且已选 `9303`）应可见：`9601 / 9602 / 9604 / 9607`。
- 学生 `900201`（软件工程且已选 `9301`）应可见：`9601 / 9602 / 9605`，不应看见 `9604 / 9603 / 9607`。
- 教师 `900103` 应可见：`9601 / 9603 / 9607`。
- 任意角色都不应在“已发布公告列表”里看到 `9606（draft）`。

### 4.3 排课相关

- `9401` 与 `9404` 同时占用 `9501（测试一教-101）`，是典型教室冲突样例。
- `9403` 与 `9405` 都由教师 `900103` 任教且时间重叠，是典型教师课表冲突样例。

### 4.4 成绩与考勤

- `exam` 9701~9704 分别验证 0、59、60、100 的录入与展示边界。
- `attendance` 9801~9804 可验证四种考勤状态是否能正确展示、汇总和统计。

## 5. 无法仅靠 schema 直接构造的场景

“重置密码后首次登录必须修改密码”这一类场景，当前表结构没有独立字段（例如 `must_change_password`、`password_reset_at`、`first_login`）来表达，因此不能仅通过插入数据库记录完整构造。

本批数据中的 `user_id = 900209` 提供了一个“默认密码账号”样例：

- 账号：`qiao.yian.test@school.edu`
- 密码：`123456`

它适合用于测试“重置后默认密码仍可登录”的前置场景；如果要验证“首次登录必须强制改密”，还需要应用层额外逻辑或会话规则配合。
