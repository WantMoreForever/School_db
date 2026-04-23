# EduManage 数据库结构说明

## 1. 文档范围

本文档以当前 [school_db_backup.sql](/d:/phpstudy_pro/WWW/EduManage/school_db_backup.sql:1) 为准，作为 `EduManage` 教学管理系统数据库结构的权威说明，用于：

- 开发时快速理解表结构、字段和关联关系；
- 排查接口或页面问题时核对真实数据库约束；
- 回归确认索引、唯一约束和外键是否已经同步到文档；
- 新增查询、字段或索引时判断变更影响。

当前文档已同步以下结构调整结果：

- 新增：
  `section.idx_section_term_course`
  `schedule.idx_schedule_section_day_time`
  `schedule.idx_schedule_classroom_day_time`
  `takes.idx_takes_student_enrolled`
  `exam.idx_exam_teacher_section_event`
  `exam.idx_exam_student_section_type_date`
  `announcement_target.idx_target_type_id_announcement`
  `system_log.idx_log_created_at`
- 修改：
  数据库结构文档改为字段级说明，不再只保留表名摘要。
- 删除：
  旧文档中不再保留已经过时的 `takes.status` 描述；
  旧冗余索引 `uq_email`、`idx_takes_section_status`、`idx_takes_student_status`、`idx_section_course`、`idx_section_year_sem`、`idx_schedule_section`、`idx_schedule_classroom`、`idx_target_lookup` 已从当前结构中移除。

## 2. 核心业务关系

项目的核心关系可以概括为：

1. `user` 是统一账号主表，`admin`、`teacher`、`student` 通过 `user_id` 扩展出角色。
2. `department` 管理院系，`major` 隶属于院系，教师与学生归属院系，学生还可绑定专业。
3. `course` 表示课程定义，`section` 表示课程在某个学年学期的具体开课班级。
4. `teacher` 通过 `teaching` 与 `section` 形成任教关系。
5. `section` 通过 `schedule` 绑定具体的上课时间、教室和周次范围。
6. `student` 通过 `takes` 与 `section` 形成选课关系。
7. `exam` 记录教师对学生在某个开课班级下的考试与成绩，`attendance` 记录某次排课下的考勤。
8. `announcement` 通过 `announcement_target` 控制公告的可见范围。
9. `system_log` 用于记录关键操作，便于后台审计和问题追踪。

## 3. 重要约束规则

- `user.email` 必须唯一，且通过触发器要求为学校邮箱域名格式。
- `user.phone` 唯一，允许为空，但非空时需满足 11 位手机号规则。
- `student.student_no` 唯一，且要求为 8 位数字。
- `department.dept_code`、`major.major_code` 必须为 1 到 10 位大写字母。
- `classroom` 通过 `(building, room_number)` 保证同一教学楼房间唯一。
- `section` 通过 `(course_id, semester, year)` 保证同一课程在同一学期只有一个开课班级记录。
- `teaching` 通过主键 `(teacher_id, section_id)` 防止重复任教绑定。
- `takes` 通过主键 `(student_id, section_id)` 防止重复选课。
- `attendance` 通过唯一键 `(schedule_id, student_id, week)` 保证同一排课同一周同一学生只有一条考勤记录。
- `exam` 通过唯一键 `(teacher_id, student_id, section_id, exam_date)` 保证同一天同一教师对同一学生同一班级只有一条考试记录。
- `announcement_target` 通过唯一键 `(announcement_id, target_type, target_id)` 防止重复投放目标。
- 当前退课逻辑通过删除 `takes` 记录实现，因此当前结构中没有 `takes.status` 字段。

## 4. 常见查询路径

这些查询路径也是理解当前索引设计的关键：

- 选课列表：
  `section(year, semester)` -> `course` -> `teaching` / `restriction` / `takes`
- 学生已选课程与课表：
  `takes(student_id)` -> `section` -> `schedule` -> `classroom`
- 教师课表与冲突检测：
  `teaching(teacher_id)` -> `schedule(section_id, day_of_week, start_time)`
- 教室冲突和空教室查询：
  `schedule(classroom_id, day_of_week, start_time, end_time)` -> `section`
- 成绩录入与统计：
  `exam(teacher_id, section_id)`、`exam(student_id, section_id, exam_type, exam_date)`
- 公告可见范围：
  `announcement_target(target_type, target_id, announcement_id)` -> `announcement`
- 系统日志清理：
  `system_log(created_at)`

## 5. 表结构说明

### 5.1 账号与角色

#### `user`

用途：
统一保存所有账号的基础信息，是管理员、教师、学生三种角色的公共主表。

主键与约束：

- 主键：`user_id`
- 唯一约束：`uq_user_email(email)`、`uq_user_phone(phone)`
- 索引：`idx_user_status(status)`、`idx_user_created_at(created_at)`

关联关系：

- 被 `admin.user_id`、`teacher.user_id`、`student.user_id`、`announcement.author_user_id`、`system_log.user_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `user_id` | `int unsigned` | 否 | 自增 | 用户主键，所有角色表都复用该 ID。 | 主键 |
| `name` | `varchar(100)` | 否 | 无 | 用户姓名，用于列表展示、公告作者、教师/学生信息展示。 | 无 |
| `email` | `varchar(150)` | 否 | 无 | 登录邮箱，当前要求学校邮箱域名格式。 | 唯一索引 |
| `password` | `varchar(255)` | 否 | 无 | 登录密码或哈希值。 | 无 |
| `status` | `enum('active','inactive','banned')` | 否 | `active` | 账号状态，决定是否允许登录。 | 普通索引 |
| `created_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 账号创建时间。 | 普通索引 |
| `gender` | `enum('male','female','other')` | 是 | `NULL` | 性别信息，主要用于资料展示。 | 无 |
| `phone` | `varchar(11)` | 是 | `NULL` | 手机号，允许为空，非空时需唯一。 | 唯一索引 |
| `image` | `varchar(255)` | 是 | `NULL` | 头像文件名。 | 无 |

#### `admin`

用途：
记录拥有管理员后台权限的账号及管理员角色类型。

主键与约束：

- 主键：`user_id`
- 外键：`user_id -> user.user_id`

关联关系：

- 与 `user` 一对一。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `user_id` | `int unsigned` | 否 | 无 | 管理员账号 ID。 | 主键、外键 |
| `role` | `varchar(50)` | 否 | `admin` | 管理员角色，当前业务中关键值包括 `admin` 和 `super_admin`。 | 无 |

#### `teacher`

用途：
保存教师角色扩展信息，包括职称和所属院系。

主键与约束：

- 主键：`user_id`
- 外键：`user_id -> user.user_id`
- 外键：`dept_id -> department.dept_id`
- 索引：`idx_teacher_dept(dept_id)`

关联关系：

- 与 `user` 一对一；
- 与 `department` 多对一；
- 被 `teaching.teacher_id`、`advisor.teacher_id`、`exam.teacher_id`、`attendance.recorded_by` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `user_id` | `int unsigned` | 否 | 无 | 教师账号 ID。 | 主键、外键 |
| `title` | `varchar(50)` | 是 | `NULL` | 教师职称，如讲师、副教授。 | 无 |
| `dept_id` | `int unsigned` | 否 | 无 | 所属院系。 | 普通索引、外键 |

#### `student`

用途：
保存学生角色扩展信息，包括学号、年级、入学年份、院系和专业。

主键与约束：

- 主键：`user_id`
- 唯一约束：`uq_student_no(student_no)`
- 外键：`user_id -> user.user_id`
- 外键：`dept_id -> department.dept_id`
- 外键：`major_id -> major.major_id`
- 索引：`idx_student_dept(dept_id)`、`idx_student_major(major_id)`

关联关系：

- 与 `user` 一对一；
- 与 `department` 多对一；
- 与 `major` 多对一；
- 被 `advisor.student_id`、`takes.student_id`、`exam.student_id`、`attendance.student_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `user_id` | `int unsigned` | 否 | 无 | 学生账号 ID。 | 主键、外键 |
| `student_no` | `varchar(8)` | 否 | 无 | 学号，要求 8 位数字。 | 唯一索引 |
| `grade` | `varchar(10)` | 是 | `NULL` | 年级称谓，如 `Freshman`、`Sophomore`。 | 无 |
| `enrollment_year` | `year` | 是 | `NULL` | 入学年份。 | 无 |
| `dept_id` | `int unsigned` | 否 | 无 | 所属院系。 | 普通索引、外键 |
| `major_id` | `int unsigned` | 是 | `NULL` | 所属专业，可为空。 | 普通索引、外键 |

#### `advisor`

用途：
记录导师与学生之间的指导关系，用于教师端导师视角统计。

主键与约束：

- 主键：`(teacher_id, student_id)`
- 外键：`teacher_id -> teacher.user_id`
- 外键：`student_id -> student.user_id`
- 索引：`idx_advisor_student(student_id)`

关联关系：

- 教师与学生之间的多对多关系表。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `teacher_id` | `int unsigned` | 否 | 无 | 导师教师 ID。 | 主键、外键 |
| `student_id` | `int unsigned` | 否 | 无 | 被指导学生 ID。 | 主键、普通索引、外键 |

### 5.2 组织与资源

#### `department`

用途：
保存院系基础数据。

主键与约束：

- 主键：`dept_id`
- 唯一约束：`uq_dept_name(dept_name)`、`uq_dept_code(dept_code)`

关联关系：

- 被 `major.dept_id`、`teacher.dept_id`、`student.dept_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `dept_id` | `int unsigned` | 否 | 自增 | 院系主键。 | 主键 |
| `dept_name` | `varchar(100)` | 否 | 无 | 院系名称。 | 唯一索引 |
| `dept_code` | `varchar(10)` | 否 | 无 | 院系代码，要求 1 到 10 位大写字母。 | 唯一索引 |

#### `major`

用途：
保存专业基础数据，并归属到具体院系。

主键与约束：

- 主键：`major_id`
- 唯一约束：`uq_major_name(major_name)`、`uq_major_code(major_code)`
- 外键：`dept_id -> department.dept_id`
- 索引：`idx_major_dept(dept_id)`

关联关系：

- 与 `department` 多对一；
- 被 `student.major_id`、`restriction.major_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `major_id` | `int unsigned` | 否 | 自增 | 专业主键。 | 主键 |
| `major_name` | `varchar(100)` | 否 | 无 | 专业名称。 | 唯一索引 |
| `major_code` | `varchar(10)` | 否 | 无 | 专业代码，要求大写字母。 | 唯一索引 |
| `dept_id` | `int unsigned` | 否 | 无 | 所属院系。 | 普通索引、外键 |

#### `course`

用途：
保存课程主数据。

主键与约束：

- 主键：`course_id`
- 唯一约束：`uq_course_name(name)`
- 索引：`idx_course_credit(credit)`

关联关系：

- 被 `section.course_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `course_id` | `int unsigned` | 否 | 自增 | 课程主键。 | 主键 |
| `name` | `varchar(150)` | 否 | 无 | 课程名称。 | 唯一索引 |
| `credit` | `decimal(3,1)` | 否 | 无 | 学分。 | 普通索引 |
| `hours` | `tinyint unsigned` | 否 | 无 | 学时。 | 无 |
| `description` | `text` | 是 | `NULL` | 课程说明。 | 无 |

#### `classroom`

用途：
保存教室资源。

主键与约束：

- 主键：`classroom_id`
- 唯一约束：`uq_classroom_room(building, room_number)`

关联关系：

- 被 `schedule.classroom_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `classroom_id` | `int unsigned` | 否 | 自增 | 教室主键。 | 主键 |
| `building` | `varchar(50)` | 否 | 无 | 教学楼名称。 | 联合唯一索引 |
| `room_number` | `varchar(20)` | 否 | 无 | 房间号。 | 联合唯一索引 |
| `capacity` | `int unsigned` | 否 | `50` | 教室容量。 | 无 |
| `type` | `varchar(20)` | 否 | `normal` | 教室类型，如普通教室、多媒体、机房。 | 无 |

#### `time_slot`

用途：
定义课程节次时间段，供排课页面和空教室页面引用。

主键与约束：

- 主键：`slot_id`

关联关系：

- 当前不通过外键绑定业务表，主要作为参考表。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `slot_id` | `int unsigned` | 否 | 自增 | 节次主键。 | 主键 |
| `slot_name` | `varchar(50)` | 否 | 无 | 节次名称，如第 1 节、第 2 节。 | 无 |
| `start_time` | `time` | 否 | 无 | 节次开始时间。 | 无 |
| `end_time` | `time` | 否 | 无 | 节次结束时间。 | 无 |

#### `config`

用途：
保存系统配置项，使用键值方式管理。

主键与约束：

- 主键：`config_key`

关联关系：

- 当前主要由应用层读取，不通过外键与其他表绑定。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `config_key` | `varchar(120)` | 否 | 无 | 配置键，建议使用点号层级命名。 | 主键 |
| `config_value` | `json` | 否 | 无 | 配置值，支持复杂 JSON。 | 无 |
| `description` | `varchar(255)` | 是 | `NULL` | 配置项说明。 | 无 |
| `updated_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 最后修改时间。 | 无 |

### 5.3 教学安排

#### `section`

用途：
表示某门课程在某个学年学期的具体开课班级。

主键与约束：

- 主键：`section_id`
- 唯一约束：`uq_section(course_id, semester, year)`
- 外键：`course_id -> course.course_id`
- 索引：
  `idx_section_term_course(year, semester, course_id, section_id)`

关联关系：

- 与 `course` 多对一；
- 被 `teaching.section_id`、`schedule.section_id`、`restriction.section_id`、`takes.section_id`、`exam.section_id`、`announcement_target.target_id(部分 target_type)` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `section_id` | `int unsigned` | 否 | 自增 | 开课班级主键。 | 主键 |
| `semester` | `varchar(20)` | 否 | 无 | 学期，当前核心值为 `Spring` / `Fall`。 | 唯一键组成、联合索引组成 |
| `year` | `year` | 否 | 无 | 学年。 | 唯一键组成、联合索引组成 |
| `course_id` | `int unsigned` | 否 | 无 | 所属课程。 | 唯一键组成、联合索引组成、外键 |
| `enrollment_start` | `datetime` | 是 | `NULL` | 选课开始时间。 | 无 |
| `enrollment_end` | `datetime` | 是 | `NULL` | 选课结束时间。 | 无 |
| `capacity` | `smallint unsigned` | 否 | `0` | 选课容量。 | 无 |

#### `teaching`

用途：
保存教师与开课班级的任教关系。

主键与约束：

- 主键：`(teacher_id, section_id)`
- 外键：`teacher_id -> teacher.user_id`
- 外键：`section_id -> section.section_id`
- 索引：`idx_teaching_section(section_id)`

关联关系：

- 教师与开课班级的多对多关系表。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `teacher_id` | `int unsigned` | 否 | 无 | 任教教师 ID。 | 主键、外键 |
| `section_id` | `int unsigned` | 否 | 无 | 任教班级 ID。 | 主键、普通索引、外键 |

#### `schedule`

用途：
记录某个开课班级的上课时间、教室和周次范围。

主键与约束：

- 主键：`schedule_id`
- 外键：`section_id -> section.section_id`
- 外键：`classroom_id -> classroom.classroom_id`
- 索引：
  `idx_schedule_section_day_time(section_id, day_of_week, start_time, end_time)`
  `idx_schedule_classroom_day_time(classroom_id, day_of_week, start_time, end_time)`

关联关系：

- 与 `section` 多对一；
- 与 `classroom` 多对一；
- 被 `attendance.schedule_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `schedule_id` | `int unsigned` | 否 | 自增 | 排课主键。 | 主键 |
| `section_id` | `int unsigned` | 否 | 无 | 关联开课班级。 | 联合索引组成、外键 |
| `day_of_week` | `tinyint unsigned` | 否 | 无 | 星期几，1 到 7。 | 联合索引组成 |
| `start_time` | `time` | 否 | 无 | 上课开始时间。 | 联合索引组成 |
| `end_time` | `time` | 否 | 无 | 上课结束时间。 | 联合索引组成 |
| `classroom_id` | `int unsigned` | 否 | 无 | 上课教室。 | 联合索引组成、外键 |
| `week_start` | `tinyint unsigned` | 是 | `1` | 起始教学周。 | 无 |
| `week_end` | `tinyint unsigned` | 是 | `13` | 结束教学周。 | 无 |

#### `restriction`

用途：
记录开课班级允许哪些专业选修，用于选课资格控制。

主键与约束：

- 主键：`(section_id, major_id)`
- 外键：`section_id -> section.section_id`
- 外键：`major_id -> major.major_id`
- 索引：`idx_restriction_major(major_id)`

关联关系：

- `section` 与 `major` 之间的多对多限制关系表。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `section_id` | `int unsigned` | 否 | 无 | 受限制的开课班级。 | 主键、外键 |
| `major_id` | `int unsigned` | 否 | 无 | 被允许选修的专业。 | 主键、普通索引、外键 |

### 5.4 学习过程

#### `takes`

用途：
表示学生已经选修某个开课班级，也是成绩和课表统计的基础关系表。

主键与约束：

- 主键：`(student_id, section_id)`
- 外键：`student_id -> student.user_id`
- 外键：`section_id -> section.section_id`
- 索引：
  `idx_takes_section(section_id)`
  `idx_takes_enrolled(enrolled_at)`
  `idx_takes_student_enrolled(student_id, enrolled_at)`

关联关系：

- 学生与开课班级之间的多对多关系表；
- 被许多成绩、课表、公告可见范围查询依赖。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `student_id` | `int unsigned` | 否 | 无 | 选课学生 ID。 | 主键、联合索引组成、外键 |
| `section_id` | `int unsigned` | 否 | 无 | 已选开课班级 ID。 | 主键、普通索引、外键 |
| `grade` | `varchar(5)` | 是 | `NULL` | 字母成绩，如 `A`、`B+`、`F`。 | 无 |
| `enrolled_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 选课时间。 | 单列索引、联合索引组成 |

说明：

- 当前结构没有 `status` 字段；
- 退课即删除记录；
- `enrolled_at` 会被触发器在插入时自动补齐。

#### `exam`

用途：
保存考试事件与分数，是成绩录入、统计、成绩页和教师工作量的重要基础表。

主键与约束：

- 主键：`exam_id`
- 唯一约束：`uq_exam(teacher_id, student_id, section_id, exam_date)`
- 外键：`teacher_id -> teacher.user_id`
- 外键：`student_id -> student.user_id`
- 外键：`section_id -> section.section_id`
- 索引：
  `idx_exam_student(student_id)`
  `idx_exam_section(section_id)`
  `idx_exam_date(exam_date)`
  `idx_exam_type(exam_type)`
  `idx_exam_teacher_section_event(teacher_id, section_id, exam_date, exam_type)`
  `idx_exam_student_section_type_date(student_id, section_id, exam_type, exam_date)`

关联关系：

- 与教师、学生、开课班级都为多对一关系；
- 服务教师考试事件、成绩录入、班级成绩统计、学生成绩查询。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `exam_id` | `int unsigned` | 否 | 自增 | 考试记录主键。 | 主键 |
| `teacher_id` | `int unsigned` | 否 | 无 | 监考或评分教师。 | 唯一键组成、联合索引组成、外键 |
| `student_id` | `int unsigned` | 否 | 无 | 参考学生。 | 普通索引、联合索引组成、唯一键组成、外键 |
| `section_id` | `int unsigned` | 否 | 无 | 所属开课班级。 | 普通索引、联合索引组成、唯一键组成、外键 |
| `exam_date` | `date` | 是 | `NULL` | 考试日期。 | 单列索引、联合索引组成、唯一键组成 |
| `exam_type` | `varchar(20)` | 否 | `final` | 考试类型，如期末、期中、测验。 | 单列索引、联合索引组成 |
| `score` | `decimal(5,2)` | 是 | `NULL` | 百分制成绩，触发器限制为 0 到 100。 | 无 |

说明：

- 当前唯一键不包含 `exam_type`，因此同一教师、同一学生、同一班级、同一天只能有一条考试记录。

#### `attendance`

用途：
按排课和周次记录学生考勤。

主键与约束：

- 主键：`attendance_id`
- 唯一约束：`uq_attendance(schedule_id, student_id, week)`
- 外键：`schedule_id -> schedule.schedule_id`
- 外键：`student_id -> student.user_id`
- 外键：`recorded_by -> teacher.user_id`
- 索引：
  `idx_att_student(student_id)`
  `idx_att_schedule(schedule_id)`
  `idx_att_week(week)`
  `idx_att_status(status)`
  `fk_att_recorder(recorded_by)`

关联关系：

- 与 `schedule`、`student`、`teacher` 都为多对一关系。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `attendance_id` | `int unsigned` | 否 | 自增 | 考勤记录主键。 | 主键 |
| `schedule_id` | `int unsigned` | 否 | 无 | 对应某一次排课时间。 | 唯一键组成、普通索引、外键 |
| `student_id` | `int unsigned` | 否 | 无 | 学生 ID。 | 唯一键组成、普通索引、外键 |
| `week` | `tinyint unsigned` | 否 | 无 | 第几周。 | 唯一键组成、普通索引 |
| `status` | `enum('present','absent','late','excused')` | 否 | `present` | 出勤状态。 | 普通索引 |
| `note` | `varchar(200)` | 是 | `NULL` | 备注。 | 无 |
| `recorded_by` | `int unsigned` | 否 | 无 | 记录教师 ID。 | 普通索引、外键 |
| `recorded_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 记录或更新时间。 | 无 |

### 5.5 公告与审计

#### `announcement`

用途：
保存公告主数据。

主键与约束：

- 主键：`announcement_id`
- 外键：`author_user_id -> user.user_id`
- 索引：
  `idx_announcement_author(author_user_id)`
  `idx_announcement_status(status)`
  `idx_announcement_pub(published_at)`

关联关系：

- 与 `user` 多对一；
- 被 `announcement_target.announcement_id` 引用。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `announcement_id` | `int unsigned` | 否 | 自增 | 公告主键。 | 主键 |
| `author_user_id` | `int unsigned` | 否 | 无 | 发布人用户 ID。 | 普通索引、外键 |
| `title` | `varchar(200)` | 否 | 无 | 公告标题。 | 无 |
| `content` | `text` | 否 | 无 | 公告正文。 | 无 |
| `status` | `varchar(20)` | 否 | `published` | 公告状态，当前常见值为 `draft`、`published`、`archived`。 | 普通索引 |
| `is_pinned` | `tinyint(1)` | 否 | `0` | 是否置顶。 | 无 |
| `published_at` | `datetime` | 是 | `CURRENT_TIMESTAMP` | 发布时间。 | 普通索引 |
| `created_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 创建时间。 | 无 |
| `updated_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 更新时间。 | 无 |

#### `announcement_target`

用途：
保存公告的投放范围，可面向全体、学生、教师、专业或具体班级。

主键与约束：

- 主键：`target_row_id`
- 唯一约束：`uq_announcement_target(announcement_id, target_type, target_id)`
- 外键：`announcement_id -> announcement.announcement_id`
- 索引：
  `idx_target_announcement(announcement_id)`
  `idx_target_type_id_announcement(target_type, target_id, announcement_id)`

关联关系：

- 与 `announcement` 多对一；
- `target_id` 在业务上会根据 `target_type` 指向不同对象，但当前不通过数据库外键强绑定。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `target_row_id` | `int unsigned` | 否 | 自增 | 公告投放记录主键。 | 主键 |
| `announcement_id` | `int unsigned` | 否 | 无 | 所属公告。 | 唯一键组成、普通索引、外键 |
| `target_type` | `varchar(20)` | 否 | `section` | 投放类型，当前支持 `all`、`students`、`teachers`、`major`、`section`。 | 唯一键组成、联合索引组成 |
| `target_id` | `int unsigned` | 否 | 无 | 目标 ID，具体含义依赖 `target_type`。 | 唯一键组成、联合索引组成 |

#### `system_log`

用途：
记录系统关键操作，供后台日志页面和问题排查使用。

主键与约束：

- 主键：`log_id`
- 外键：`user_id -> user.user_id`
- 索引：`idx_log_user(user_id)`、`idx_log_created_at(created_at)`

关联关系：

- 与 `user` 多对一。

| 字段 | 类型 | 可空 | 默认值 | 字段用途说明 | 索引/约束 |
| --- | --- | --- | --- | --- | --- |
| `log_id` | `int unsigned` | 否 | 自增 | 日志主键。 | 主键 |
| `user_id` | `int unsigned` | 是 | `NULL` | 操作人 ID，删除用户后允许置空。 | 普通索引、外键 |
| `action` | `varchar(100)` | 否 | 无 | 操作动作描述。 | 无 |
| `target_table` | `varchar(50)` | 是 | `NULL` | 受影响表名。 | 无 |
| `target_id` | `int unsigned` | 是 | `NULL` | 受影响记录主键。 | 无 |
| `created_at` | `datetime` | 否 | `CURRENT_TIMESTAMP` | 操作时间，也是日志清理的主要条件。 | 普通索引 |

## 6. 索引设计说明

### 6.1 当前索引分布总览

| 表 | 索引名 | 字段 | 类型 | 主要服务场景 | 设计原因 |
| --- | --- | --- | --- | --- | --- |
| `user` | `uq_user_email` | `email` | 唯一 | 登录、邮箱查重 | `WHERE email = ?` |
| `user` | `uq_user_phone` | `phone` | 唯一 | 手机号查重 | `WHERE phone = ?` |
| `user` | `idx_user_status` | `status` | 普通 | 账号启用/禁用筛选 | `WHERE status = ?` |
| `user` | `idx_user_created_at` | `created_at` | 普通 | 按创建时间管理用户 | `ORDER BY/过滤时间` |
| `teacher` | `idx_teacher_dept` | `dept_id` | 普通 | 教师按院系筛选 | `WHERE dept_id = ?` |
| `student` | `uq_student_no` | `student_no` | 唯一 | 学号查重和定位 | `WHERE student_no = ?` |
| `student` | `idx_student_dept` | `dept_id` | 普通 | 学生按院系筛选 | `WHERE dept_id = ?` |
| `student` | `idx_student_major` | `major_id` | 普通 | 选课限制、按专业筛选 | `WHERE major_id = ?` |
| `advisor` | `idx_advisor_student` | `student_id` | 普通 | 学生反查导师关系 | `WHERE student_id = ?` |
| `major` | `idx_major_dept` | `dept_id` | 普通 | 专业按院系筛选 | `WHERE dept_id = ?` |
| `course` | `idx_course_credit` | `credit` | 普通 | 学分维度统计 | `WHERE/GROUP BY credit` |
| `classroom` | `uq_classroom_room` | `building, room_number` | 唯一联合 | 教室唯一性校验 | `WHERE building = ? AND room_number = ?` |
| `section` | `uq_section` | `course_id, semester, year` | 唯一联合 | 同课程同学期开课唯一性 | `WHERE course_id = ? AND semester = ? AND year = ?` |
| `section` | `idx_section_term_course` | `year, semester, course_id, section_id` | 普通联合 | 选课页、排课页、学期班级列表 | `WHERE year = ? AND semester = ?` + `ORDER BY course_id, section_id` |
| `teaching` | 主键 | `teacher_id, section_id` | 主键联合 | 教师任教、权限校验 | `WHERE teacher_id = ?` |
| `teaching` | `idx_teaching_section` | `section_id` | 普通 | 班级反查教师 | `JOIN/WHERE section_id = ?` |
| `schedule` | `idx_schedule_section_day_time` | `section_id, day_of_week, start_time, end_time` | 普通联合 | 课表读取、教师冲突检测 | `WHERE section_id = ?` + 排序/时间重叠 |
| `schedule` | `idx_schedule_classroom_day_time` | `classroom_id, day_of_week, start_time, end_time` | 普通联合 | 教室冲突、空教室查询 | `WHERE classroom_id = ? AND day_of_week = ?` + 时间重叠 |
| `restriction` | `idx_restriction_major` | `major_id` | 普通 | 专业限制过滤 | `WHERE major_id = ?` |
| `takes` | 主键 | `student_id, section_id` | 主键联合 | 判重、已选课程关系 | `WHERE student_id = ? AND section_id = ?` |
| `takes` | `idx_takes_section` | `section_id` | 普通 | 统计班级选课人数 | `WHERE section_id = ?` |
| `takes` | `idx_takes_enrolled` | `enrolled_at` | 普通 | 选课时间排序 | `ORDER BY enrolled_at` |
| `takes` | `idx_takes_student_enrolled` | `student_id, enrolled_at` | 普通联合 | 学生当前课程、最近选课记录 | `WHERE student_id = ? ORDER BY enrolled_at DESC` |
| `exam` | `uq_exam` | `teacher_id, student_id, section_id, exam_date` | 唯一联合 | 考试事件防重 | 教师/学生/班级/日期唯一 |
| `exam` | `idx_exam_student` | `student_id` | 普通 | 学生成绩查询 | `WHERE student_id = ?` |
| `exam` | `idx_exam_section` | `section_id` | 普通 | 班级成绩查询 | `WHERE section_id = ?` |
| `exam` | `idx_exam_date` | `exam_date` | 普通 | 按考试日期统计 | `WHERE/ORDER BY exam_date` |
| `exam` | `idx_exam_type` | `exam_type` | 普通 | 按考试类型筛选 | `WHERE exam_type = ?` |
| `exam` | `idx_exam_teacher_section_event` | `teacher_id, section_id, exam_date, exam_type` | 普通联合 | 教师考试事件、未录成绩统计、撤销考试 | `WHERE teacher_id = ? AND section_id = ?` + 日期/类型分组排序 |
| `exam` | `idx_exam_student_section_type_date` | `student_id, section_id, exam_type, exam_date` | 普通联合 | 最近一次期末/期中/测验成绩查询 | `WHERE student_id = ? AND section_id = ? AND exam_type = ?` + `ORDER BY exam_date` |
| `attendance` | `uq_attendance` | `schedule_id, student_id, week` | 唯一联合 | 防止重复考勤 | `WHERE schedule_id = ? AND student_id = ? AND week = ?` |
| `attendance` | `idx_att_student` | `student_id` | 普通 | 学生考勤汇总 | `WHERE student_id = ?` |
| `attendance` | `idx_att_schedule` | `schedule_id` | 普通 | 某次排课考勤列表 | `WHERE schedule_id = ?` |
| `attendance` | `idx_att_week` | `week` | 普通 | 周次维度筛选 | `WHERE week = ?` |
| `attendance` | `idx_att_status` | `status` | 普通 | 按状态统计出勤 | `WHERE status = ?` |
| `attendance` | `fk_att_recorder` | `recorded_by` | 普通 | 记录教师反查 | `WHERE recorded_by = ?` |
| `announcement` | `idx_announcement_author` | `author_user_id` | 普通 | 我的公告、按作者筛选 | `WHERE author_user_id = ?` |
| `announcement` | `idx_announcement_status` | `status` | 普通 | 已发布/草稿筛选 | `WHERE status = ?` |
| `announcement` | `idx_announcement_pub` | `published_at` | 普通 | 发布时间排序 | `ORDER BY published_at` |
| `announcement_target` | `uq_announcement_target` | `announcement_id, target_type, target_id` | 唯一联合 | 防止重复投放目标 | 唯一性约束 |
| `announcement_target` | `idx_target_announcement` | `announcement_id` | 普通 | 公告详情回查投放目标 | `WHERE announcement_id = ?` |
| `announcement_target` | `idx_target_type_id_announcement` | `target_type, target_id, announcement_id` | 普通联合 | 公告可见范围过滤 | `WHERE target_type = ? AND target_id = ?` |
| `system_log` | `idx_log_user` | `user_id` | 普通 | 查看某用户操作日志 | `WHERE user_id = ?` |
| `system_log` | `idx_log_created_at` | `created_at` | 普通 | 历史日志清理 | `WHERE created_at < ?` |

### 6.2 本轮结构同步中的索引变更

新增并已生效：

- `section.idx_section_term_course`
- `schedule.idx_schedule_section_day_time`
- `schedule.idx_schedule_classroom_day_time`
- `takes.idx_takes_student_enrolled`
- `exam.idx_exam_teacher_section_event`
- `exam.idx_exam_student_section_type_date`
- `announcement_target.idx_target_type_id_announcement`
- `system_log.idx_log_created_at`

已删除的冗余索引：

- `user.uq_email`
- `takes.idx_takes_section_status`
- `takes.idx_takes_student_status`
- `section.idx_section_course`
- `section.idx_section_year_sem`
- `schedule.idx_schedule_section`
- `schedule.idx_schedule_classroom`
- `announcement_target.idx_target_lookup`

## 7. 关系说明

### 7.1 学生、课程、选课

- `student.user_id -> takes.student_id`
- `section.section_id -> takes.section_id`
- `course.course_id -> section.course_id`

这条链路用于：

- 学生选课
- 已选课程查询
- 学生成绩统计
- 学生课表生成

### 7.2 教师、任教、排课

- `teacher.user_id -> teaching.teacher_id`
- `section.section_id -> teaching.section_id`
- `section.section_id -> schedule.section_id`
- `classroom.classroom_id -> schedule.classroom_id`

这条链路用于：

- 教师任教管理
- 教师课表
- 教师冲突检测
- 教室占用冲突检测

### 7.3 专业限制与选课资格

- `section.section_id -> restriction.section_id`
- `major.major_id -> restriction.major_id`
- `student.major_id` 与 `restriction.major_id` 配合判断是否可选

这条链路用于：

- 限定专业可选课程
- 选课页过滤
- 选课前资格校验

### 7.4 成绩与考勤

- `takes` 是学生与班级的基础关系
- `exam` 是成绩与考试事件表
- `attendance` 依赖 `schedule` 和 `student`

这条链路用于：

- 教师成绩录入
- 学生成绩页
- 班级成绩统计
- 出勤率统计

### 7.5 公告投放

- `announcement.author_user_id -> user.user_id`
- `announcement_target.announcement_id -> announcement.announcement_id`

其中 `announcement_target.target_type` 决定 `target_id` 的业务含义：

- `all`：全体
- `students`：学生端
- `teachers`：教师端
- `major`：指定专业
- `section`：指定开课班级

## 8. 文档维护建议

后续如果数据库发生变更，建议按下面顺序同步：

1. 先更新 `school_db_backup.sql` 或正式迁移脚本。
2. 再更新本文档中的字段表、约束说明和索引说明。
3. 如果涉及查询性能，再同步更新 `docs/index-design.md`。
4. 如果影响测试样例，再同步更新 `docs/test-data-scenarios.md` 和测试数据 SQL。

判断文档是否过时时，至少检查：

- 是否还出现已删除字段名，例如 `takes.status`
- 是否漏写新增索引或保留了已删除索引
- 字段类型、可空、默认值是否与 `CREATE TABLE` 一致
- 外键和唯一约束是否与实际 SQL 一致
