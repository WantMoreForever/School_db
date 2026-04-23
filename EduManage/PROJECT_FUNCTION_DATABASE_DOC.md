# EduManage 教学管理系统项目使用与数据库说明

## 1. 文档说明

本文档基于当前 `EduManage` 教学管理系统项目代码、`school_db_backup.sql`、`admin_reusable_db_objects.sql`、`project_reusable_db_objects.sql` 和 `fix_takes_status_routines.sql` 整理，用于课程项目交付、答辩说明和后续维护。

当前项目功能已经基本定型，文档重点说明：

- 项目运行入口和角色使用方式。
- 管理员、教师、学生三端功能。
- 当前数据库表结构、关系和内置对象。
- 已整合的视图、函数、存储过程和触发器。
- 当前代码与数据库对象的配合方式。

补充文档：

- `docs/api-list.md`：管理员、教师、学生三端主要接口清单。
- `docs/api-contract.md`：统一 JSON 响应结构。
- `docs/database-schema.md`：当前数据库表结构、字段、约束、索引和关系说明。
- `docs/error-codes.md`：错误码规范。
- `docs/index-design.md`：常用查询索引设计、冗余索引清理与验证建议。
- `docs/permission-matrix.md`：权限矩阵与审计说明。
- `docs/deployment-guide.md`：部署、配置、验证和常见问题。
- `docs/defense-guide.md`：答辩说明、架构图、ER 关系、演示账号和演示路线。

重要说明：当前 `takes` 表没有 `status` 字段，选课关系以表中存在的记录表示“已选/有效选课”。`takes` 当前字段为：`student_id`、`section_id`、`grade`、`enrolled_at`。

## 2. 项目概览

`EduManage` 教学管理系统（英文名称：EduManage, Teaching Management System）是一个面向高校场景的教学管理平台，技术栈为：

- 后端：PHP
- 数据库：MySQL
- 前端：HTML、CSS、JavaScript
- 数据访问：PDO
- 编码：UTF-8 / utf8mb4

系统按角色分为三端：

| 角色 | 入口 | 主要职责 |
| --- | --- | --- |
| 管理员 | `admin/index.php` | 维护基础数据、用户、课程、教室、排课、公告和日志。 |
| 教师 | `teacher/index.php` | 管理任教课程、成绩、考试、考勤、公告和工作量。 |
| 学生 | `student/spa.html` | 查看首页、个人信息、课表、选课、成绩、考试、空教室和公告。 |

统一登录入口：

- `login/index.php`
- `login/login.php`

登录成功后，系统根据用户是否存在于 `admin`、`teacher`、`student` 表中进行角色分流。

## 2.1 配置目录说明

当前项目已经把主要运行配置集中到 `config/` 目录，后续维护时建议优先修改这里，不要直接去业务页面或接口里查找硬编码常量。

| 文件 | 作用 | 主要影响 |
| --- | --- | --- |
| `config/app.php` | 应用运行环境、时区、Session 兜底目录、日志策略 | 会影响全站时间、日志保留、调试行为 |
| `config/api.php` | JSON/HTML/JS 响应头、无缓存头、CORS、错误码映射 | 会影响三端接口输出和跨域行为 |
| `config/auth.php` | 角色表、登录后跳转页、超级管理员角色、后台敏感动作列表 | 会影响登录分流、权限判断和后台安全保护 |
| `config/database.php` | 数据库主机、端口、库名、账号、密码、字符集 | 会影响整个项目数据库连接 |
| `config/enums.php` | 学期、性别、考试类型、教室类型、考勤状态等枚举标签 | 会影响表单选项、页面中文标签、部分接口展示 |
| `config/frontend.php` | 静态资源版本号与 CDN 地址 | 会影响前端缓存刷新和外部资源加载 |
| `config/upload.php` | 头像上传限制、导入文件扩展名限制 | 会影响头像上传和批量导入 |
| `config/app_config.php` | 统一配置读取器，提供 `app_config()` | 会影响整个项目读取配置的方式 |
| `config/paths.php` | 页面、接口、静态资源、上传目录的统一目录表 | 会影响跳转、资源加载、上传访问路径 |
| `config/frontend-paths.php` | 把后端配置输出为前端 `window.APP_PATHS` | 会影响学生端和教师端读取路径、版本号、枚举 |

推荐读取方式：

- PHP：`app_config('database.host')`
- PHP：`app_catalog_url('student', 'api', 'profile')`
- 前端：`window.APP_PATHS.student.api.profile`

修改示例：

```php
// 例 1：修改头像大小限制到 3MB
// 文件：config/upload.php
'avatar' => [
    'max_size' => 3 * 1024 * 1024,
]

// 例 2：修改数据库主机
// 文件：config/database.php
'host' => getenv('DB_HOST') ?: '127.0.0.1',
```

需要谨慎修改的配置：

- `config/database.php`：改错会导致整个系统无法连接数据库。
- `config/auth.php` 中的 `super_admin_role`：改动前要确认数据库 `admin.role` 的存量数据是否一致。
- `config/enums.php` 中的 `semester`、`announcement_targets`、`exam_type` 键名：这些键名可能与数据库字段值或过程参数绑定，通常只建议改中文标签，不建议随意改键名。
- `config/paths.php`：改动前要确认对应文件真实存在，否则容易出现 404 或 include 失败。
- `config/frontend.php`：版本号改动会导致浏览器重新拉取资源，CDN 地址改动会影响后台和前端依赖加载。

当前仍未完全收口到 `config/` 的配置点：

- 测试脚本中的演示邮箱、数据库名等固定值属于测试样本，不建议和业务配置混合。
- 后台示例数据中的演示邮箱仍属于页面示例内容，不属于运行时配置。

## 2.2 索引设计说明

当前项目的数据库索引优化说明已经单独整理到：

- `docs/index-design.md`
- `tests/sql/apply_index_optimization.sql`

本轮索引优化重点围绕以下高频或关键查询链路：

- 学生端选课、已选课程、课表与选课时间冲突检测；
- 管理端排课、教师冲突检测、教室冲突检测；
- 教师端考试事件、成绩录入、班级成绩统计；
- 学生端成绩单、班级成绩页中的“最近一次期末/期中/测验成绩”查询；
- 公告可见范围过滤；
- 系统日志按时间清理。

当前已经落地到数据库结构中的核心索引包括：

- `section(year, semester, course_id, section_id)`
- `schedule(section_id, day_of_week, start_time, end_time)`
- `schedule(classroom_id, day_of_week, start_time, end_time)`
- `takes(student_id, enrolled_at)`
- `exam(teacher_id, section_id, exam_date, exam_type)`
- `exam(student_id, section_id, exam_type, exam_date)`
- `announcement_target(target_type, target_id, announcement_id)`
- `system_log(created_at)`

已经从当前结构中清理的冗余索引包括：

- `user.uq_email`
- `takes.idx_takes_section_status`
- `takes.idx_takes_student_status`
- `section.idx_section_course`
- `section.idx_section_year_sem`
- `schedule.idx_schedule_section`
- `schedule.idx_schedule_classroom`
- `announcement_target.idx_target_lookup`

当前建议的验证顺序是：

1. 对运行中的真实库执行 `SHOW INDEX` 或 `SHOW CREATE TABLE`，确认结构没有落后于仓库。
2. 对关键 SQL 执行 `EXPLAIN`，确认命中新联合索引。
3. 在学生端、教师端、管理端完成页面回归。

这样可以确认“文档、备份结构、运行中数据库实例”三者保持一致。

## 3. 部署与运行

完整部署步骤见 `docs/deployment-guide.md`。本节保留课程项目运行所需的核心信息。

### 3.1 环境要求

建议环境：

- PHP 8.0 或以上
- MySQL 8.0 或以上
- Nginx 或 Apache
- phpstudy 或同类本地 PHP 集成环境

项目当前数据库默认配置位于：

- `config/database.php`
- `components/db.php`

默认连接信息：

| 配置项 | 默认值 |
| --- | --- |
| 主机 | `localhost` |
| 端口 | `3306` |
| 数据库 | `school_db` |
| 用户名 | `root` |
| 字符集 | `utf8mb4` |

密码使用当前本地配置文件中的值，也可以通过环境变量覆盖：

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`
- `DB_CHARSET`

### 3.2 数据库导入顺序

如果是新环境部署，建议按以下顺序执行：

1. 导入 `school_db_backup.sql`
2. 如当前备份未包含管理员复用对象，再导入 `admin_reusable_db_objects.sql`
3. 如当前备份未包含项目复用对象，再导入 `project_reusable_db_objects.sql`
4. 如果遇到旧例程仍引用 `takes.status`，执行 `fix_takes_status_routines.sql`

当前项目代码已经优先调用 `sp_project_*` 和 `sp_admin_*` 存储过程；如果某些对象没有导入，代码会尽量回退到原 SQL 查询，避免页面直接崩溃。

### 3.3 运行入口

以站点根目录为项目根目录时，可访问：

| 页面 | 路径 |
| --- | --- |
| 登录页 | `/login/` |
| 管理员后台 | `/admin/index.php` |
| 教师门户 | `/teacher/index.php` |
| 学生门户 | `/student/spa.html` |

## 4. 项目目录说明

| 路径 | 说明 |
| --- | --- |
| `admin/` | 管理员页面、接口、样式、脚本、局部模板。 |
| `teacher/` | 教师门户页面、接口、样式和教师端 SQL 过程文件。 |
| `student/` | 学生单页门户、接口、样式和脚本。 |
| `login/` | 登录、退出和登录页样式。 |
| `components/` | 公共数据库连接、登录认证、日志、学生数据、成绩工具。 |
| `config/` | 数据库配置、路径配置、前端路径配置。 |
| `uploads/` | 头像、登录图等上传资源。 |
| `tests/smoke/` | 项目 smoke 测试脚本。 |
| `school_db_backup.sql` | 当前数据库完整备份。 |
| `admin_reusable_db_objects.sql` | 管理端复用视图、过程和触发器。 |
| `project_reusable_db_objects.sql` | 全项目复用视图、过程和触发器。 |
| `fix_takes_status_routines.sql` | 修复旧过程引用 `takes.status` 的兼容 SQL。 |

## 5. 登录与权限

系统账号统一存储在 `user` 表中，角色由扩展表决定：

- `admin`：管理员
- `teacher`：教师
- `student`：学生

登录逻辑：

1. 用户输入邮箱和密码。
2. 系统校验 `user.status`，只有 `active` 账号可以继续访问。
3. 系统识别角色并跳转到对应端。
4. 访问各端接口时继续校验登录状态和角色权限。

管理员角色中：

- `super_admin` 可以管理普通管理员。
- 普通管理员不能进入管理员管理页面。
- 系统必须保留至少一个 `super_admin`，否则无法进入 `admin/admin_manage.php` 管理管理员账号。

超级管理员由 `user` 表和 `admin` 表共同确定，必要字段如下：

| 表 | 必要字段 | 说明 |
| --- | --- | --- |
| `user` | `user_id` | 与 `admin.user_id` 对应。 |
| `user` | `email` | 登录邮箱，必须唯一。 |
| `user` | `password` | 登录密码哈希或当前系统认可的密码值。 |
| `user` | `name` | 后台显示名称。 |
| `user` | `status` | 必须为 `active`。 |
| `admin` | `user_id` | 指向 `user.user_id`。 |
| `admin` | `role` | 必须为 `super_admin`。 |

## 6. 管理员端功能

管理员端是系统主数据管理中心。

| 功能 | 文件 | 说明 |
| --- | --- | --- |
| 后台首页 | `admin/index.php` | 展示学生、教师、课程、公告、教室、排课、院系、专业统计。 |
| 学生管理 | `admin/student.php` | 新增、编辑、启用/禁用、重置密码、批量导入学生。 |
| 教师管理 | `admin/teacher.php` | 新增、编辑、启用/禁用、重置密码、批量导入教师。 |
| 管理员管理 | `admin/admin_manage.php` | `super_admin` 管理普通管理员。 |
| 课程管理 | `admin/course.php` | 维护课程名、学分、学时、描述。 |
| 教室管理 | `admin/classroom.php` | 维护教学楼、房间号、容量、教室类型。 |
| 院系管理 | `admin/department.php` | 维护院系代码和名称，并统计关联数据。 |
| 专业管理 | `admin/major.php` | 维护专业代码、专业名称和所属院系。 |
| 排课管理 | `admin/schedule_manage.php` | 管理课程、教师、教室、节次、周次和容量。 |
| 公告管理 | `admin/announcement.php` | 发布、编辑、删除、筛选公告，支持不同投放对象。 |
| 系统日志 | `admin/syslog.php` | 查看用户操作日志。 |
| 个人资料 | `admin/profile.php` | 修改管理员资料、密码和头像。 |

管理端主要接口：

- `admin/api/personnel.php`
- `admin/api/resources.php`
- `admin/api/schedule.php`
- `admin/api/announcement.php`
- `admin/api/import_students.php`
- `admin/api/import_teachers.php`

## 7. 教师端功能

教师端入口：

- `teacher/index.php`
- `teacher/index.html`

教师端主要功能：

| 功能 | 说明 |
| --- | --- |
| 仪表盘 | 展示授课班级、学生数、考试数、平均分等统计。 |
| 我的课程 | 查看当前教师任教的课程班级。 |
| 成绩管理 | 查看学生成绩、录入考试成绩、自动分配字母成绩。 |
| 考试管理 | 发布考试、维护考试记录、批量导入成绩。 |
| 课表管理 | 查看、增加、修改、删除本人任教课程的排课。 |
| 课程申请 | 教师申请任教课程或维护任教关系。 |
| 课程公告 | 向自己任教的班级发布公告。 |
| 公告收件箱 | 查看面向教师、全体或本人课程的公告。 |
| 考勤管理 | 按课程排课和周次记录学生考勤。 |
| 工作量报告 | 按学期统计课程数、学生数、考试数、成绩完成度等。 |
| 个人设置 | 修改教师个人资料、头像和密码。 |

教师端接口：

- `teacher/api/teacher.php`
- `teacher/api/grades.php`
- `teacher/api/announcement.php`
- `teacher/api/attendance.php`
- `teacher/api/schedule.php`
- `teacher/api/application.php`
- `teacher/api/workload.php`

教师端大量业务逻辑使用数据库存储过程，例如：

- `sp_get_teacher_info`
- `sp_get_teacher_sections`
- `sp_get_final_scores`
- `sp_save_exam`
- `sp_publish_exam`
- `sp_record_attendance`
- `sp_get_workload_summary`
- `sp_project_get_teacher_visible_announcements`

## 8. 学生端功能

学生端入口：

- `student/spa.html`

学生端采用单页应用形式，通过 JavaScript 调用 `student/api/` 下的接口。

| 功能 | 说明 |
| --- | --- |
| 首页门户 | 展示学生信息、GPA、学分、选课数、最近成绩。 |
| 个人资料 | 查看和更新手机号、头像等信息。 |
| 修改密码 | 修改当前登录账号密码。 |
| 课程表 | 查看当前学生已选课程的课表。 |
| 选课系统 | 查看可选课程、已选课程、课程余量，支持选课和退课。 |
| 成绩查询 | 查看考试成绩、课程成绩、GPA 和学期筛选。 |
| 考试信息 | 查看考试安排。 |
| 空闲教室 | 按周次、星期、节次、教室条件查询空闲教室。 |
| 通知公告 | 查看面向全体、学生、专业或已选课程的公告。 |

学生端接口：

- `student/api/student_portal.php`
- `student/api/profile.php`
- `student/api/change_pwd.php`
- `student/api/schedule.php`
- `student/api/course_select.php`
- `student/api/my_grades.php`
- `student/api/exam_info.php`
- `student/api/free_classroom.php`
- `student/api/announcement.php`
- `student/api/sidebar.php`

学生端目前已经优先使用项目复用存储过程：

- `sp_project_get_student_base_info`
- `sp_project_get_student_schedule`
- `sp_project_get_student_current_courses`
- `sp_project_get_student_current_schedules`
- `sp_project_get_available_sections`
- `sp_project_get_student_portal_summary`
- `sp_project_get_student_visible_announcements`
- `sp_project_get_time_slots`
- `sp_project_get_classrooms`
- `sp_project_get_classroom_conflicts`

## 9. 数据库结构说明

数据库名称：`school_db`

当前数据库结构的字段级权威说明已经独立整理到：

- `docs/database-schema.md`

本章保留结构摘要，方便从总览快速理解业务分层；字段类型、是否可空、默认值、索引和外键请以 `docs/database-schema.md` 和 `school_db_backup.sql` 为准。

数据库表可以按业务划分为五组。

### 9.1 账号与角色

| 表 | 说明 |
| --- | --- |
| `user` | 所有用户基础账号，包含姓名、邮箱、密码、状态、性别、手机号、头像。 |
| `admin` | 管理员角色表，记录管理员角色级别。 |
| `teacher` | 教师角色表，记录职称和所属院系。 |
| `student` | 学生角色表，记录学号、年级、入学年份、院系、专业。 |
| `advisor` | 导师和学生的指导关系。 |

### 9.2 组织与资源

| 表 | 说明 |
| --- | --- |
| `department` | 院系信息，包含院系代码和名称。 |
| `major` | 专业信息，包含专业代码、名称和所属院系。 |
| `course` | 课程基础信息，包含课程名、学分、学时、描述。 |
| `classroom` | 教室信息，包含教学楼、房间号、容量、类型。 |
| `time_slot` | 节次时间定义。 |
| `config` | 系统配置，使用 JSON 保存配置值。 |

### 9.3 教学安排

| 表 | 说明 |
| --- | --- |
| `section` | 开课班级，表示某课程在某学年学期开设。 |
| `teaching` | 教师与开课班级的任教关系。 |
| `schedule` | 开课班级的上课时间、教室和周次。 |
| `restriction` | 开课班级对专业的选课限制。 |

### 9.4 学习过程

| 表 | 说明 |
| --- | --- |
| `takes` | 学生选课关系，记录学生、开课班级、字母成绩和选课时间。 |
| `exam` | 考试与成绩记录，包含教师、学生、班级、考试类型、日期和分数。 |
| `attendance` | 考勤记录，按排课、学生、周次保存出勤状态。 |

`takes` 当前结构摘要：

| 字段 | 说明 |
| --- | --- |
| `student_id` | 学生用户 ID。 |
| `section_id` | 开课班级 ID。 |
| `grade` | 字母成绩，如 `A`、`B+`、`F`，可为空。 |
| `enrolled_at` | 选课时间。 |

当前没有 `takes.status`。退课采用删除对应 `takes` 记录的方式处理。

### 9.5 公告与日志

| 表 | 说明 |
| --- | --- |
| `announcement` | 公告主表，包含发布人、标题、内容、状态、置顶和发布时间。 |
| `announcement_target` | 公告投放目标，支持 `all`、`students`、`teachers`、`major`、`section`。 |
| `system_log` | 系统操作日志。 |

### 9.6 核心关系

核心业务链路如下：

1. `user` 扩展为 `admin`、`teacher`、`student`。
2. `department` 管理 `major`，学生和教师归属院系。
3. `course` 通过 `section` 在具体学年学期开课。
4. `teacher` 通过 `teaching` 绑定 `section`。
5. `section` 通过 `schedule` 绑定上课时间和教室。
6. `student` 通过 `takes` 绑定 `section`，形成选课关系。
7. `exam` 和 `attendance` 记录成绩与考勤。
8. `announcement` 通过 `announcement_target` 控制可见范围。

当前结构同步中已经完成的重点调整：

- 新增联合索引：`section.idx_section_term_course`、`schedule.idx_schedule_section_day_time`、`schedule.idx_schedule_classroom_day_time`、`takes.idx_takes_student_enrolled`、`exam.idx_exam_teacher_section_event`、`exam.idx_exam_student_section_type_date`、`announcement_target.idx_target_type_id_announcement`
- 新增日志时间索引：`system_log.idx_log_created_at`
- 删除冗余索引：`uq_email`、`idx_takes_section_status`、`idx_takes_student_status`、`idx_section_course`、`idx_section_year_sem`、`idx_schedule_section`、`idx_schedule_classroom`、`idx_target_lookup`
- 文档不再保留过时字段 `takes.status`

## 10. 数据库视图

### 10.1 原有成绩视图

| 视图 | 说明 |
| --- | --- |
| `v_student_exam_grades` | 学生考试成绩聚合视图，服务学生成绩查询。 |

### 10.2 管理端复用视图

| 视图 | 说明 |
| --- | --- |
| `v_admin_dashboard_counts` | 管理后台首页统计。 |
| `v_admin_department_stats` | 院系统计。 |
| `v_admin_student_list` | 学生管理列表。 |
| `v_admin_teacher_list` | 教师管理列表。 |
| `v_admin_schedule_list` | 排课管理列表。 |

### 10.3 全项目复用视图

| 视图 | 说明 |
| --- | --- |
| `v_project_section_teachers` | 汇总每个开课班级的教师信息。 |
| `v_project_section_overview` | 汇总开课班级、课程、教师、人数等信息。 |
| `v_project_schedule_overview` | 汇总排课、课程、教室、教师信息。 |
| `v_project_student_schedule_overview` | 汇总学生已选课程课表。 |

## 11. 数据库函数

| 函数 | 说明 |
| --- | --- |
| `fn_check_room_conflict` | 检查教室时间冲突。 |
| `fn_check_teacher_conflict` | 检查教师时间冲突。 |
| `fn_get_student_course_gpa` | 根据 `takes.grade` 和课程学分计算 GPA。 |
| `fn_score_to_grade` | 将百分制成绩转换为字母成绩。 |
| `fn_student_attendance_rate` | 计算学生在某班级的出勤率。 |
| `fn_student_section_avg` | 计算学生在某班级的综合成绩。 |
| `fn_teacher_section_count` | 统计教师任教班级数量。 |

## 12. 数据库存储过程

### 12.1 学生相关

| 存储过程 | 说明 |
| --- | --- |
| `sp_calc_student_stats` | 计算学生 GPA、学分等统计。 |
| `sp_get_exam_semesters` | 获取学生有成绩记录的学期。 |
| `sp_get_student_exams` | 获取学生考试成绩列表。 |
| `sp_get_course_avg_by_student` | 获取学生各课程成绩和 GPA 相关数据。 |
| `sp_get_student_attendance_summary` | 获取学生考勤统计。 |

### 12.2 教师课程、成绩、考试

| 存储过程 | 说明 |
| --- | --- |
| `sp_get_teacher_info` | 获取教师个人信息。 |
| `sp_update_teacher_profile` | 更新教师个人资料。 |
| `sp_update_teacher_avatar` | 更新教师头像。 |
| `sp_get_teacher_sections` | 获取教师任教班级。 |
| `sp_get_section_students` | 获取班级学生与成绩信息。 |
| `sp_get_section_exams` | 获取班级考试记录。 |
| `sp_save_exam` | 保存考试成绩。 |
| `sp_update_exam_score` | 更新考试分数。 |
| `sp_delete_exam` | 删除考试记录。 |
| `sp_publish_exam` | 发布考试并为已选课学生生成考试记录。 |
| `sp_cancel_exam_event` | 取消考试事件。 |
| `sp_auto_assign_grades` | 自动分配字母成绩。 |
| `sp_update_letter_grade` | 更新学生字母成绩。 |
| `sp_batch_import_exam` | 批量导入考试成绩。 |
| `sp_get_final_scores` | 获取班级期末成绩列表。 |
| `sp_get_student_final_score` | 获取某学生某班级期末成绩。 |
| `sp_get_course_avg_score` | 获取班级成绩统计。 |
| `sp_get_grade_distribution` | 获取成绩分布。 |
| `sp_get_exam_comparison` | 获取不同考试类型对比。 |
| `sp_get_exam_entry_students` | 获取考试录入学生列表。 |
| `sp_get_exam_events` | 获取考试事件。 |
| `sp_get_pending_exams` | 获取待完成考试。 |

### 12.3 教师任教、排课、考勤、工作量

| 存储过程 | 说明 |
| --- | --- |
| `sp_get_courses_to_apply` | 获取教师可申请任教课程。 |
| `sp_apply_to_teach` | 教师申请任教。 |
| `sp_remove_teaching` | 移除任教关系。 |
| `sp_update_section_info` | 更新开课班级信息。 |
| `sp_get_advisor_students` | 获取导师指导学生。 |
| `sp_add_schedule` | 新增排课。 |
| `sp_update_schedule` | 更新排课。 |
| `sp_delete_schedule` | 删除排课。 |
| `sp_get_schedule` | 获取班级排课。 |
| `sp_get_teacher_schedule` | 获取教师课表。 |
| `sp_get_teacher_weekly_schedule` | 获取教师某周课表。 |
| `sp_get_teacher_week_range` | 获取教师课表周次范围。 |
| `sp_record_attendance` | 单条考勤记录。 |
| `sp_batch_record_attendance` | 批量考勤记录。 |
| `sp_get_schedule_attendance` | 获取某排课某周考勤。 |
| `sp_get_section_attendance_report` | 获取班级考勤报表。 |
| `sp_get_dashboard_stats` | 获取教师首页统计。 |
| `sp_get_teacher_semesters` | 获取教师任教学期。 |
| `sp_get_workload_summary` | 获取教师工作量汇总。 |
| `sp_get_workload_by_section` | 按班级统计教师工作量。 |

### 12.4 管理端复用过程

| 存储过程 | 说明 |
| --- | --- |
| `sp_admin_get_dashboard_counts` | 管理首页统计。 |
| `sp_admin_get_departments` | 获取院系列表。 |
| `sp_admin_get_majors` | 获取专业列表。 |
| `sp_admin_get_courses` | 获取课程列表。 |
| `sp_admin_get_classrooms` | 获取教室列表。 |
| `sp_admin_get_department_stats` | 获取院系统计。 |
| `sp_admin_get_students` | 获取学生管理列表。 |
| `sp_admin_get_teachers` | 获取教师管理列表。 |
| `sp_admin_get_manageable_admins` | 获取可管理管理员列表。 |
| `sp_admin_get_profile_user` | 获取管理员个人资料。 |
| `sp_admin_get_recent_system_logs` | 获取最近系统日志。 |
| `sp_admin_get_schedule_reference_data` | 获取排课页面参考数据。 |
| `sp_admin_get_schedule_list` | 获取排课管理列表。 |

### 12.5 全项目复用过程

| 存储过程 | 说明 |
| --- | --- |
| `sp_project_get_student_base_info` | 获取学生基础信息。 |
| `sp_project_get_student_schedule` | 获取学生完整课表。 |
| `sp_project_get_student_current_courses` | 获取学生当前学期已选课程。 |
| `sp_project_get_student_current_schedules` | 获取学生当前学期课表。 |
| `sp_project_get_available_sections` | 获取学生可选课程。 |
| `sp_project_get_time_slots` | 获取节次列表。 |
| `sp_project_get_classrooms` | 获取教室列表。 |
| `sp_project_get_classroom_conflicts` | 获取空教室查询中的占用冲突。 |
| `sp_project_get_student_portal_summary` | 获取学生首页摘要。 |
| `sp_project_get_student_visible_announcements` | 获取学生可见公告。 |
| `sp_project_get_section_announcements` | 获取课程公告。 |
| `sp_project_get_author_announcements` | 获取发布人自己的公告。 |
| `sp_project_get_teacher_visible_announcements` | 获取教师可见公告。 |

## 13. 数据库触发器

### 13.1 原有数据校验触发器

| 触发器 | 说明 |
| --- | --- |
| `trg_department_code_bi` / `trg_department_code_bu` | 校验院系代码为 1 到 10 位大写字母。 |
| `trg_major_code_bi` / `trg_major_code_bu` | 校验专业代码为 1 到 10 位大写字母。 |
| `trg_student_no_bi` / `trg_student_no_bu` | 校验学号为 8 位数字。 |
| `trg_exam_score_bi` / `trg_exam_score_bu` | 校验考试成绩在 0 到 100 之间。 |
| `trg_user_contact_bi` / `trg_user_contact_bu` | 校验邮箱和手机号格式。 |
| `trg_no_duplicate_teaching` | 防止重复任教绑定。 |

### 13.2 管理端补充触发器

| 触发器 | 说明 |
| --- | --- |
| `trg_admin_user_image_blank_bi` / `trg_admin_user_image_blank_bu` | 将空头像值规范为 `NULL`。 |
| `trg_admin_course_values_bi` / `trg_admin_course_values_bu` | 校验课程学分、学时必须大于 0。 |
| `trg_admin_classroom_capacity_bi` / `trg_admin_classroom_capacity_bu` | 校验教室容量必须大于 0。 |
| `trg_admin_section_values_bi` / `trg_admin_section_values_bu` | 校验开课容量和选课时间范围。 |
| `trg_admin_schedule_values_bi` / `trg_admin_schedule_values_bu` | 校验排课周次和上课时间范围。 |

### 13.3 全项目补充触发器

| 触发器 | 说明 |
| --- | --- |
| `trg_project_takes_enrolled_at_bi` | 插入选课记录时自动补齐 `enrolled_at`。 |
| `trg_project_announcement_values_bi` / `trg_project_announcement_values_bu` | 校验公告标题、状态和发布时间。 |
| `trg_project_announcement_target_values_bi` / `trg_project_announcement_target_values_bu` | 校验公告投放对象类型和目标 ID。 |

## 14. 当前代码与数据库对象的配合

当前代码采用“存储过程优先，原 SQL 回退”的方式：

- `components/db.php` 提供 `app_call_rows()` 和 `app_call_multi_result_rows()`。
- 管理端查询优先调用 `sp_admin_*`。
- 学生端高频查询优先调用 `sp_project_*`。
- 教师公告等通用查询优先调用 `sp_project_*`。
- 教师成绩、考勤、工作量等复杂业务继续使用已有 `sp_get_*`、`sp_save_*`、`sp_record_*` 等过程。

这样做的好处是：

- 数据库逻辑更集中，便于答辩说明。
- 页面查询逻辑更统一。
- 如果某个补充 SQL 文件未导入，页面仍有机会回退到原查询，降低课程项目运行风险。

## 15. 测试说明

项目包含 smoke 测试：

```powershell
powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1
```

该测试覆盖：

- 登录页访问
- 管理员登录
- 管理员课程、公告、日志页面
- 管理员发布公告
- 教师登录
- 教师成绩、考试、考勤接口
- 学生登录
- 学生选课、成绩、公告相关接口

当前已验证 smoke 测试通过。

## 16. 项目总结

当前项目已经形成完整的高校教学管理系统闭环：

1. 管理员维护院系、专业、课程、教室、用户和排课。
2. 教师管理任教班级、考试、成绩、考勤、课程公告和工作量。
3. 学生查看个人信息、课表、选课、成绩、考试、空教室和公告。
4. 数据库通过视图、函数、存储过程和触发器承担统计、校验和复用查询能力。

本项目适合作为课程设计项目提交，重点可以从以下角度说明：

- 三角色权限划分清晰。
- 数据库关系完整，覆盖教学管理核心流程。
- 存储过程和触发器承担了关键业务规则。
- 代码保留回退逻辑，降低演示环境出错风险。
