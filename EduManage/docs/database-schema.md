# EduManage 数据库结构说明

## 1. 文档范围

本文档以 `school_db_backup.sql` 为准，说明 `EduManage` 当前数据库结构、关系与数据库对象设计。用于：

- 教师查阅项目数据库设计；
- 课程答辩时讲解数据结构与业务规则；
- 部署和维护时核对真实数据库对象；
- 后续扩展功能时评估结构影响。

请注意：

- `school_db_backup.sql` 是数据库初始化主脚本；
- `tests/sql/demo_seed.sql` 是演示数据脚本；
- 当前数据库说明应以主脚本中的真实定义为准。

## 2. 数据库总体构成

当前主脚本中包含：

| 对象类型 | 数量 |
| --- | --- |
| 数据表 | 21 |
| 视图 | 5 |
| 函数 | 7 |
| 存储过程 | 60 |
| 触发器 | 16 |

这说明本项目数据库不仅承担数据存储，也承担查询复用、规则校验和核心教学业务过程控制。

## 3. 核心业务关系

数据库核心关系如下：

1. `user` 为统一账号主表，`admin`、`teacher`、`student` 为角色扩展表。
2. `department` 管理院系，`major` 归属于院系。
3. `course` 定义课程，`section` 表示课程在某个学年学期的具体开课班级。
4. `teacher` 通过 `teaching` 与 `section` 建立任教关系。
5. `section` 通过 `schedule` 绑定教室、时间与周次。
6. `student` 通过 `takes` 与 `section` 建立选课关系。
7. `exam` 记录考试与成绩，`attendance` 记录考勤。
8. `announcement` 与 `announcement_target` 共同完成公告精准投放。
9. `system_log` 用于后台关键操作审计。

特别说明：

- `takes` 当前不包含 `status` 字段；
- “已选课”通过 `takes` 中是否存在记录表示；
- “退课”通过删除 `takes` 对应记录实现。

## 4. 数据表分组说明

### 4.1 账号与角色

| 表 | 关键字段 | 说明 | 关键约束 |
| --- | --- | --- | --- |
| `user` | `user_id`、`name`、`email`、`password`、`status`、`phone`、`image` | 统一账号主表。 | 邮箱唯一、手机号唯一、状态索引。 |
| `admin` | `user_id`、`role` | 管理员角色扩展表。 | `user_id` 与 `user` 一对一。 |
| `teacher` | `user_id`、`title`、`dept_id` | 教师角色扩展表。 | 关联院系。 |
| `student` | `user_id`、`student_no`、`grade`、`enrollment_year`、`dept_id`、`major_id` | 学生角色扩展表。 | 学号唯一，关联院系与专业。 |
| `advisor` | `teacher_id`、`student_id` | 导师与学生关系表。 | 复合主键，避免重复绑定。 |

### 4.2 组织与资源

| 表 | 关键字段 | 说明 | 关键约束 |
| --- | --- | --- | --- |
| `department` | `dept_id`、`dept_name`、`dept_code` | 院系主数据。 | 院系名称与代码唯一。 |
| `major` | `major_id`、`major_name`、`major_code`、`dept_id` | 专业主数据。 | 专业名称与代码唯一，关联院系。 |
| `course` | `course_id`、`name`、`credit`、`hours` | 课程主数据。 | 课程名称唯一。 |
| `classroom` | `classroom_id`、`building`、`room_number`、`capacity`、`type` | 教室主数据。 | 同一教学楼房间唯一。 |
| `time_slot` | `slot_id`、`slot_name`、`start_time`、`end_time` | 节次时间定义。 | 供排课与空闲教室查询复用。 |
| `config` | `config_key`、`config_value` | 系统配置表。 | 采用键值形式保存配置。 |

### 4.3 教学安排

| 表 | 关键字段 | 说明 | 关键约束 |
| --- | --- | --- | --- |
| `section` | `section_id`、`course_id`、`semester`、`year`、`enrollment_start`、`enrollment_end`、`capacity` | 开课班级。 | 同课程同学期同学年唯一。 |
| `teaching` | `teacher_id`、`section_id` | 教师任教关系。 | 复合主键，避免重复任教。 |
| `schedule` | `schedule_id`、`section_id`、`day_of_week`、`start_time`、`end_time`、`classroom_id`、`week_start`、`week_end` | 课程排课。 | 关联教室与开课班级。 |
| `restriction` | `section_id`、`major_id` | 选课专业限制。 | 复合主键，描述“哪些专业可选”。 |

### 4.4 学习过程

| 表 | 关键字段 | 说明 | 关键约束 |
| --- | --- | --- | --- |
| `takes` | `student_id`、`section_id`、`grade`、`enrolled_at` | 学生选课关系。 | 复合主键，选课时间索引。 |
| `exam` | `exam_id`、`teacher_id`、`student_id`、`section_id`、`exam_date`、`exam_type`、`score` | 考试与成绩记录。 | 成绩范围校验，支持考试事件与统计。 |
| `attendance` | `attendance_id`、`schedule_id`、`student_id`、`week`、`status`、`recorded_by` | 考勤记录。 | 同排课同周同学生唯一。 |

### 4.5 公告与审计

| 表 | 关键字段 | 说明 | 关键约束 |
| --- | --- | --- | --- |
| `announcement` | `announcement_id`、`author_user_id`、`title`、`content`、`status`、`published_at` | 公告主表。 | 关联发布人。 |
| `announcement_target` | `target_row_id`、`announcement_id`、`target_type`、`target_id` | 公告投放目标表。 | 同公告同目标不可重复。 |
| `system_log` | `log_id`、`user_id`、`action`、`target_table`、`target_id`、`created_at` | 操作日志表。 | 用于后台审计与追踪。 |

## 5. 视图设计

| 视图 | 说明 |
| --- | --- |
| `v_project_section_teachers` | 聚合每个开课班级的任课教师信息。 |
| `v_project_section_overview` | 聚合开课班级、课程、教师与选课人数。 |
| `v_project_schedule_overview` | 聚合排课、课程、教室和教师信息。 |
| `v_project_student_schedule_overview` | 聚合学生已选课程课表。 |
| `v_student_exam_grades` | 聚合学生考试成绩、课程和教师信息。 |

这些视图有助于让页面和接口以业务视角读取数据，而不是在每个查询中重复手写复杂联表。

## 6. 函数设计

| 函数 | 作用 | 典型场景 |
| --- | --- | --- |
| `fn_check_room_conflict` | 判断教室时间冲突 | 排课与空闲教室判断 |
| `fn_check_teacher_conflict` | 判断教师时间冲突 | 排课冲突检测 |
| `fn_get_student_course_gpa` | 计算学生课程 GPA | 学生门户与成绩分析 |
| `fn_score_to_grade` | 百分制转字母成绩 | 成绩录入与字母成绩生成 |
| `fn_student_attendance_rate` | 计算出勤率 | 学生考勤统计 |
| `fn_student_section_avg` | 计算班级综合成绩 | 成绩汇总 |
| `fn_teacher_section_count` | 统计教师任教班级数量 | 教师工作量统计 |

## 7. 存储过程设计

### 7.1 设计定位

本项目强调“核心教学业务过程化”。选课、课表、考试、成绩、考勤、工作量、公告可见范围等高频业务统一通过存储过程封装，这也是本项目数据库设计的重要亮点。

### 7.2 存储过程分组

| 分组 | 存储过程 | 主要服务场景 |
| --- | --- | --- |
| 学生统计与成绩 | `sp_calc_student_stats`、`sp_get_exam_semesters`、`sp_get_student_exams`、`sp_get_course_avg_by_student`、`sp_get_student_attendance_summary` | 学生门户、成绩页、学期筛选、考勤摘要 |
| 教师资料与班级 | `sp_get_teacher_info`、`sp_update_teacher_profile`、`sp_update_teacher_avatar`、`sp_get_teacher_sections`、`sp_get_section_students`、`sp_get_section_exams` | 教师资料、任教班级、班级学生与考试信息 |
| 考试与成绩管理 | `sp_save_exam`、`sp_update_exam_score`、`sp_delete_exam`、`sp_publish_exam`、`sp_cancel_exam_event`、`sp_auto_assign_grades`、`sp_update_letter_grade`、`sp_batch_import_exam`、`sp_get_final_scores`、`sp_get_student_final_score`、`sp_get_course_avg_score`、`sp_get_grade_distribution`、`sp_get_exam_comparison`、`sp_get_exam_entry_students`、`sp_get_exam_events`、`sp_get_pending_exams` | 考试发布、成绩录入、统计分析与字母成绩生成 |
| 任教、排课与导师 | `sp_get_courses_to_apply`、`sp_apply_to_teach`、`sp_remove_teaching`、`sp_update_section_info`、`sp_get_advisor_students`、`sp_add_schedule`、`sp_update_schedule`、`sp_delete_schedule`、`sp_get_schedule`、`sp_get_teacher_schedule`、`sp_get_teacher_weekly_schedule`、`sp_get_teacher_week_range` | 任教申请、开课调整、教师课表与导师关系 |
| 考勤与工作量 | `sp_record_attendance`、`sp_batch_record_attendance`、`sp_get_schedule_attendance`、`sp_get_section_attendance_report`、`sp_get_dashboard_stats`、`sp_get_teacher_semesters`、`sp_get_workload_summary`、`sp_get_workload_by_section` | 考勤记录、考勤报表、教师首页和工作量统计 |
| 全项目复用过程 | `sp_project_get_student_base_info`、`sp_project_get_student_schedule`、`sp_project_get_student_current_courses`、`sp_project_get_student_current_schedules`、`sp_project_get_available_sections`、`sp_project_get_time_slots`、`sp_project_get_classrooms`、`sp_project_get_classroom_conflicts`、`sp_project_get_student_portal_summary`、`sp_project_get_student_visible_announcements`、`sp_project_get_section_announcements`、`sp_project_get_author_announcements`、`sp_project_get_teacher_visible_announcements` | 选课、课表、公告、门户摘要、空闲教室查询 |

## 8. 触发器设计

| 类别 | 触发器 | 作用 |
| --- | --- | --- |
| 编码与联系方式校验 | `trg_department_code_bi`、`trg_department_code_bu`、`trg_major_code_bi`、`trg_major_code_bu`、`trg_student_no_bi`、`trg_student_no_bu`、`trg_user_contact_bi`、`trg_user_contact_bu` | 校验院系代码、专业代码、学号、邮箱与手机号格式 |
| 教学业务校验 | `trg_exam_score_bi`、`trg_exam_score_bu`、`trg_no_duplicate_teaching`、`trg_project_takes_enrolled_at_bi` | 校验成绩范围、避免重复任教、自动补齐选课时间 |
| 公告业务校验 | `trg_project_announcement_values_bi`、`trg_project_announcement_values_bu`、`trg_project_announcement_target_values_bi`、`trg_project_announcement_target_values_bu` | 规范公告状态、发布时间与投放目标 |

## 9. 索引设计概览

数据库当前围绕高频教学查询配置了关键索引：

| 索引 | 主要服务场景 |
| --- | --- |
| `section(year, semester, course_id, section_id)` | 学期班级检索、选课页、排课页 |
| `schedule(section_id, day_of_week, start_time, end_time)` | 教师课表、班级排课 |
| `schedule(classroom_id, day_of_week, start_time, end_time)` | 教室冲突检测、空闲教室查询 |
| `takes(student_id, enrolled_at)` | 学生当前课程、最近选课记录 |
| `exam(teacher_id, section_id, exam_date, exam_type)` | 教师考试事件、成绩录入 |
| `exam(student_id, section_id, exam_type, exam_date)` | 学生成绩查询、最近成绩统计 |
| `announcement_target(target_type, target_id, announcement_id)` | 公告精准投放与可见范围过滤 |
| `system_log(created_at)` | 日志按时间筛选、归档与清理 |

索引的作用不是单纯“加快查询”，而是与存储过程和视图一起支撑教学业务链路的稳定运行。

## 10. 数据库与代码协作方式

项目应用层通过 PDO 连接数据库，数据库对象在系统中的职责清晰：

- 视图负责业务聚合视角；
- 函数负责规则判断与指标计算；
- 存储过程负责核心教学业务封装；
- 触发器负责边界校验和自动补值；
- 索引负责高频查询性能保障。

教师端和学生端的高频业务广泛调用存储过程，这也是本项目数据库设计最值得展示的部分。

## 11. 维护建议

如果后续数据库有调整，建议按以下顺序同步：

1. 先更新 `school_db_backup.sql`；
2. 再同步本文档；
3. 如涉及部署变化，再更新 `docs/deployment-guide.md`；
4. 如涉及答辩口径，再更新 `docs/defense-guide.md`。

检查文档是否过时时，优先确认：

- 是否仍把 `school_db_backup.sql` 作为初始化主脚本；
- 是否错误写入了不存在的表、视图或存储过程；
- 是否还保留已不存在的字段，例如 `takes.status`；
- 是否遗漏新的索引、触发器或过程定义。
