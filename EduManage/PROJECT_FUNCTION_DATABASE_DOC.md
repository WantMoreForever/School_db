# EduManage 教学管理系统项目功能与数据库说明

## 1. 文档用途

本文档基于当前 `EduManage` 项目代码、目录结构与 `school_db_backup.sql` 整理，用于课程项目提交、教师查阅、课堂验收与答辩展示。

本文重点说明：

- 当前项目结构与三端入口；
- 管理员、教师、学生三端已经实现的功能；
- 数据库表、视图、函数、存储过程、触发器、索引的组织方式；
- 代码层与数据库对象的协作关系。

配套文档建议一并查看：

- `README.md`
- `docs/deployment-guide.md`
- `docs/database-schema.md`
- `docs/project-highlights.md`
- `docs/defense-guide.md`
- `docs/user-manual.md`

## 2. 项目概览

`EduManage` 是一个面向高校教学场景的教学管理系统，采用 PHP + MySQL + HTML/CSS/JavaScript 实现。项目支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx，适合课程设计演示、答辩展示和后续教学场景扩展。

系统按照三类角色组织：

| 角色 | 入口 | 主要职责 |
| --- | --- | --- |
| 管理员 | `admin/index.php` | 维护基础数据、用户、课程、教室、排课、公告和系统日志。 |
| 教师 | `teacher/index.php` | 管理任教课程、考试、成绩、考勤、课程公告和工作量。 |
| 学生 | `student/spa.html` | 查看门户、选课、课表、成绩、考试安排、空闲教室和通知公告。 |

统一登录入口：

- `login/`
- `login/login.php`

登录后，系统会根据账号所在角色表自动分流到对应端。

## 3. 当前项目结构

### 3.1 目录结构

| 路径 | 说明 |
| --- | --- |
| `admin/` | 管理员页面、接口、脚本、样式与局部模板。 |
| `teacher/` | 教师端页面、接口、脚本与样式。 |
| `student/` | 学生端单页门户、接口、脚本与样式。 |
| `login/` | 登录、退出与登录页样式。 |
| `components/` | 公共数据库、认证、日志、成绩辅助与学生数据组件。 |
| `config/` | 应用、数据库、权限、路径、前端与上传配置。 |
| `docs/` | 部署、数据库、亮点、答辩等说明文档。 |
| `tests/` | smoke 测试、测试数据与验证脚本。 |
| `uploads/` | 头像、登录图等上传资源。 |
| `school_db_backup.sql` | 数据库初始化主脚本。 |

### 3.2 配置目录

当前运行配置已经集中在 `config/` 目录：

| 文件 | 作用 |
| --- | --- |
| `config/database.php` | 数据库连接配置。 |
| `config/app.php` | 应用环境、时区、调试和 Session 相关配置。 |
| `config/auth.php` | 角色映射、登录分流与后台权限配置。 |
| `config/api.php` | 接口响应头、缓存与错误码配置。 |
| `config/enums.php` | 学期、考勤、教室类型等枚举标签。 |
| `config/frontend.php` | 静态资源版本与前端资源配置。 |
| `config/upload.php` | 上传限制与导入文件约束。 |
| `config/paths.php` | 页面、接口、静态资源与上传目录路径表。 |
| `config/frontend-paths.php` | 向前端输出 `window.APP_PATHS`。 |
| `config/app_config.php` | 统一配置读取入口。 |

## 4. 部署与初始化要点

### 4.1 环境说明

项目支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx。建议版本：

- PHP 8.0 或以上
- MySQL 8.0 或以上
- 字符集使用 UTF-8 / utf8mb4

### 4.2 数据库初始化主线

新环境部署时，数据库导入顺序应保持为：

1. `school_db_backup.sql`
2. `tests/sql/demo_seed.sql`

其中：

- `school_db_backup.sql` 是数据库初始化主脚本，也是当前结构、视图、函数、存储过程、触发器与索引的权威来源。
- `tests/sql/demo_seed.sql` 用于导入演示账号和演示数据。
- `tests/sql/qa_business_validation_seed.sql` 仅用于补充业务验证，不属于答辩主链路。

### 4.3 运行入口

| 页面 | 路径 |
| --- | --- |
| 登录页 | `/login/` 或 `/login/login.php` |
| 管理员端 | `/admin/index.php` |
| 教师端 | `/teacher/index.php` |
| 学生端 | `/student/spa.html` |

## 5. 功能模块说明

### 5.1 管理员端

管理员端负责基础教学数据治理和系统管理，当前模块包括：

| 功能 | 页面 | 说明 |
| --- | --- | --- |
| 后台首页 | `admin/index.php` | 展示用户、课程、公告、教室与排课统计。 |
| 学生管理 | `admin/student.php` | 学生新增、编辑、状态管理、密码重置与批量导入。 |
| 教师管理 | `admin/teacher.php` | 教师新增、编辑、状态管理、密码重置与批量导入。 |
| 管理员管理 | `admin/admin_manage.php` | 超级管理员维护管理员账号。 |
| 课程管理 | `admin/course.php` | 维护课程名称、学分、学时与描述。 |
| 教室管理 | `admin/classroom.php` | 维护教学楼、房间号、容量与教室类型。 |
| 院系管理 | `admin/department.php` | 维护院系代码和院系名称。 |
| 专业管理 | `admin/major.php` | 维护专业及所属院系。 |
| 排课管理 | `admin/schedule_manage.php` | 管理开课班级、教师、教室、周次与节次。 |
| 公告管理 | `admin/announcement.php` | 发布、编辑、删除与筛选公告。 |
| 系统日志 | `admin/syslog.php` | 查看用户关键操作记录。 |
| 个人资料 | `admin/profile.php` | 修改管理员资料、头像和密码。 |

主要接口目录：

- `admin/api/personnel.php`
- `admin/api/resources.php`
- `admin/api/schedule.php`
- `admin/api/announcement.php`
- `admin/api/import_students.php`
- `admin/api/import_teachers.php`

### 5.2 教师端

教师端强调“教学执行”场景，当前模块包括：

| 功能 | 说明 |
| --- | --- |
| 仪表盘 | 展示授课班级、学生数、考试数、平均分等统计。 |
| 我的课程 | 查看当前任教班级与学生信息。 |
| 成绩管理 | 查看成绩、录入成绩、自动分配字母成绩。 |
| 考试管理 | 发布考试、维护考试事件、批量导入成绩。 |
| 课表管理 | 查看、增加、修改、删除本人排课。 |
| 课程申请 | 申请任教课程或维护任教关系。 |
| 课程公告 | 面向任教班级发布公告。 |
| 公告收件箱 | 查看面向教师、全体或本人课程的公告。 |
| 考勤管理 | 按排课和周次记录学生考勤。 |
| 工作量报告 | 按学期统计班级数、学生数、考试数和完成度。 |
| 个人设置 | 修改资料、头像和密码。 |

主要接口目录：

- `teacher/api/teacher.php`
- `teacher/api/grades.php`
- `teacher/api/schedule.php`
- `teacher/api/attendance.php`
- `teacher/api/application.php`
- `teacher/api/announcement.php`
- `teacher/api/workload.php`

### 5.3 学生端

学生端采用单页门户形式，当前模块包括：

| 功能 | 说明 |
| --- | --- |
| 首页门户 | 展示学生信息、GPA、学分、选课数和最近成绩。 |
| 个人资料 | 查看和更新手机号、头像等信息。 |
| 修改密码 | 修改当前账号密码。 |
| 课程表 | 查看已选课程课表。 |
| 选课系统 | 查看可选课程、已选课程、课程余量并进行选退课。 |
| 成绩查询 | 查看考试成绩、课程成绩、GPA 与学期筛选结果。 |
| 考试信息 | 查看考试安排。 |
| 空闲教室 | 按周次、星期、节次和教室条件查询。 |
| 通知公告 | 查看面向学生、专业或课程的公告。 |

主要接口目录：

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

## 6. 数据库总体设计

### 6.1 主脚本中的对象规模

当前 `school_db_backup.sql` 中包含：

| 对象类型 | 数量 |
| --- | --- |
| 数据表 | 21 |
| 视图 | 5 |
| 函数 | 7 |
| 存储过程 | 60 |
| 触发器 | 16 |

这说明本项目数据库设计不是“只有表结构”，而是通过视图、函数、触发器和存储过程共同承载教学业务规则。

### 6.2 数据表分组

| 分组 | 代表数据表 | 说明 |
| --- | --- | --- |
| 账号与角色 | `user`、`admin`、`teacher`、`student`、`advisor` | 统一账号与角色扩展模型。 |
| 组织与资源 | `department`、`major`、`course`、`classroom`、`time_slot`、`config` | 维护院系、专业、课程、教室、节次与系统配置。 |
| 教学安排 | `section`、`teaching`、`schedule`、`restriction` | 描述开课、任教、排课与选课限制。 |
| 学习过程 | `takes`、`exam`、`attendance` | 描述选课关系、考试成绩与考勤记录。 |
| 公告与审计 | `announcement`、`announcement_target`、`system_log` | 支持精准投放公告与后台日志审计。 |

### 6.3 核心关系

核心业务链路可以概括为：

1. `user` 作为统一账号主表，扩展出 `admin`、`teacher`、`student`。
2. `department` 管理 `major`，教师和学生都归属院系。
3. `course` 通过 `section` 在具体学年学期开设。
4. `teacher` 通过 `teaching` 绑定到 `section`。
5. `section` 通过 `schedule` 绑定教室、时间与周次。
6. `student` 通过 `takes` 与 `section` 形成选课关系。
7. `exam` 与 `attendance` 分别记录考试成绩与考勤过程。
8. `announcement` 通过 `announcement_target` 控制可见范围。

补充说明：

- `takes` 当前字段为 `student_id`、`section_id`、`grade`、`enrolled_at`，不存在 `takes.status`。
- 系统中“退课”通过删除对应 `takes` 记录实现。

## 7. 数据库视图

当前主脚本中定义的 5 个视图如下：

| 视图 | 说明 |
| --- | --- |
| `v_project_section_teachers` | 汇总每个开课班级的任课教师信息。 |
| `v_project_section_overview` | 汇总开课班级、课程、教师与选课人数信息。 |
| `v_project_schedule_overview` | 汇总排课、课程、教室与教师信息。 |
| `v_project_student_schedule_overview` | 汇总学生已选课程课表。 |
| `v_student_exam_grades` | 汇总学生考试成绩、课程与教师信息。 |

这些视图为课表展示、成绩展示、教师信息聚合等场景提供了稳定的数据视角。

## 8. 数据库函数

当前主脚本中定义的函数如下：

| 函数 | 作用 |
| --- | --- |
| `fn_check_room_conflict` | 检查教室时间冲突。 |
| `fn_check_teacher_conflict` | 检查教师时间冲突。 |
| `fn_get_student_course_gpa` | 计算学生课程 GPA。 |
| `fn_score_to_grade` | 将百分制成绩映射为字母成绩。 |
| `fn_student_attendance_rate` | 计算学生在班级内的出勤率。 |
| `fn_student_section_avg` | 计算学生班级综合成绩。 |
| `fn_teacher_section_count` | 统计教师任教班级数量。 |

## 9. 存储过程设计

### 9.1 设计定位

本项目将核心教学业务封装在存储过程中，尤其集中体现在教师端与学生端高频流程。这样的设计有三点价值：

1. 业务规则集中在数据库层，便于统一维护；
2. 复杂查询可以被多端复用，减少重复 SQL；
3. 在答辩展示中可以直观说明“数据库不仅存数据，也承担业务计算与过程控制”。

### 9.2 存储过程分组

| 分组 | 过程清单 | 主要用途 |
| --- | --- | --- |
| 学生统计与成绩 | `sp_calc_student_stats`、`sp_get_exam_semesters`、`sp_get_student_exams`、`sp_get_course_avg_by_student`、`sp_get_student_attendance_summary` | 支持学生门户、成绩页和考勤统计。 |
| 教师资料与班级 | `sp_get_teacher_info`、`sp_update_teacher_profile`、`sp_update_teacher_avatar`、`sp_get_teacher_sections`、`sp_get_section_students`、`sp_get_section_exams` | 支持教师资料维护、任教班级查询和班级考试读取。 |
| 考试与成绩管理 | `sp_save_exam`、`sp_update_exam_score`、`sp_delete_exam`、`sp_publish_exam`、`sp_cancel_exam_event`、`sp_auto_assign_grades`、`sp_update_letter_grade`、`sp_batch_import_exam`、`sp_get_final_scores`、`sp_get_student_final_score`、`sp_get_course_avg_score`、`sp_get_grade_distribution`、`sp_get_exam_comparison`、`sp_get_exam_entry_students`、`sp_get_exam_events`、`sp_get_pending_exams` | 封装考试发布、成绩录入、成绩统计和考试事件管理。 |
| 任教、排课与导师关系 | `sp_get_courses_to_apply`、`sp_apply_to_teach`、`sp_remove_teaching`、`sp_update_section_info`、`sp_get_advisor_students`、`sp_add_schedule`、`sp_update_schedule`、`sp_delete_schedule`、`sp_get_schedule`、`sp_get_teacher_schedule`、`sp_get_teacher_weekly_schedule`、`sp_get_teacher_week_range` | 支持教师申请任教、排课维护、教师课表与导师视图。 |
| 考勤与工作量 | `sp_record_attendance`、`sp_batch_record_attendance`、`sp_get_schedule_attendance`、`sp_get_section_attendance_report`、`sp_get_dashboard_stats`、`sp_get_teacher_semesters`、`sp_get_workload_summary`、`sp_get_workload_by_section` | 支持考勤记录、报表与教师工作量统计。 |
| 全项目复用过程 | `sp_project_get_student_base_info`、`sp_project_get_student_schedule`、`sp_project_get_student_current_courses`、`sp_project_get_student_current_schedules`、`sp_project_get_available_sections`、`sp_project_get_time_slots`、`sp_project_get_classrooms`、`sp_project_get_classroom_conflicts`、`sp_project_get_student_portal_summary`、`sp_project_get_student_visible_announcements`、`sp_project_get_section_announcements`、`sp_project_get_author_announcements`、`sp_project_get_teacher_visible_announcements` | 为选课、课表、空闲教室、门户摘要和公告可见范围提供统一过程接口。 |

说明：

- 当前项目的“教学核心链路”主要依赖上述存储过程完成。
- 管理员端以基础数据维护为主，更多依托表约束、索引、触发器和规范化结构完成治理。

## 10. 触发器与约束

当前主脚本中的触发器分为三类：

| 类别 | 触发器 | 作用 |
| --- | --- | --- |
| 基础编码与格式校验 | `trg_department_code_bi`、`trg_department_code_bu`、`trg_major_code_bi`、`trg_major_code_bu`、`trg_student_no_bi`、`trg_student_no_bu`、`trg_user_contact_bi`、`trg_user_contact_bu` | 校验院系代码、专业代码、学号、邮箱和手机号格式。 |
| 教学数据校验 | `trg_exam_score_bi`、`trg_exam_score_bu`、`trg_no_duplicate_teaching`、`trg_project_takes_enrolled_at_bi` | 校验成绩范围、避免重复任教、自动补齐选课时间。 |
| 公告业务校验 | `trg_project_announcement_values_bi`、`trg_project_announcement_values_bu`、`trg_project_announcement_target_values_bi`、`trg_project_announcement_target_values_bu` | 规范公告状态、发布时间、投放类型与目标值。 |

除触发器外，数据库还通过主键、唯一约束、外键约束保证：

- 账号邮箱、手机号、学号唯一；
- 任教关系、选课关系、公告投放关系不重复；
- 排课、成绩、考勤、公告与人员之间的引用关系清晰稳定。

## 11. 索引设计要点

当前索引结构以 `school_db_backup.sql` 为准，已经围绕高频教学查询做了优化。重点索引包括：

| 索引 | 主要服务场景 |
| --- | --- |
| `section(year, semester, course_id, section_id)` | 学期班级检索、选课页和排课页数据读取。 |
| `schedule(section_id, day_of_week, start_time, end_time)` | 教师课表读取、班级排课查看。 |
| `schedule(classroom_id, day_of_week, start_time, end_time)` | 教室冲突检测、空闲教室查询。 |
| `takes(student_id, enrolled_at)` | 学生当前课程、最近选课记录。 |
| `exam(teacher_id, section_id, exam_date, exam_type)` | 教师考试事件与考试发布管理。 |
| `exam(student_id, section_id, exam_type, exam_date)` | 学生成绩查询与最近成绩统计。 |
| `announcement_target(target_type, target_id, announcement_id)` | 公告精准投放与可见范围过滤。 |
| `system_log(created_at)` | 系统日志按时间筛选与清理。 |

这些索引与存储过程、视图一起构成了系统数据库性能与可维护性的基础。

## 12. 代码与数据库对象的协作方式

项目在应用层采用 PDO 连接数据库，在数据库层通过结构化对象承担规则和计算：

- `components/db.php` 提供 `app_call_rows()`、`app_call_multi_result_rows()` 等统一过程调用能力；
- 教师端接口大量直接调用 `sp_get_*`、`sp_save_*`、`sp_publish_*`、`sp_record_*` 等过程；
- 学生端接口广泛复用 `sp_project_*` 过程完成选课、课表、门户、公告和空闲教室查询；
- 管理员端围绕基础数据维护，依托规范表结构、索引、触发器和约束保证数据一致性。

因此，当前项目的数据库设计是“表结构 + 视图 + 函数 + 存储过程 + 触发器 + 索引”的协同体系，而不是单纯依赖页面层拼接 SQL。

## 13. 演示账号与答辩说明

当前演示账号如下：

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@school.edu` | `123456` |
| 教师 | `teacher@school.edu` | `123456` |
| 学生 | `student@school.edu` | `123456` |

答辩时建议强调以下几点：

1. 三类角色形成完整教学业务闭环；
2. `school_db_backup.sql` 是数据库初始化主脚本，保证演示环境快速恢复；
3. 核心教学业务由存储过程封装，体现数据库层设计能力；
4. 视图、函数、触发器和索引共同支撑统计、校验与查询性能；
5. 文档、测试与演示数据已经形成清晰交付体系。

## 14. 总结

当前 `EduManage` 已形成较完整的高校教学管理项目结构：

- 功能上覆盖管理员、教师、学生三端核心链路；
- 数据库上不仅有表，还具备视图、函数、存储过程、触发器和索引；
- 部署上以 `school_db_backup.sql` 为初始化主线，结构清晰，适合课堂展示；
- 文档上已经具备部署、数据库、亮点和答辩资料，方便教师查阅和项目说明。
