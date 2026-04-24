# EduManage 接口清单

本文档整理 `EduManage` 教学管理系统当前版本的主要 HTTP 接口。接口响应遵循 `docs/api-contract.md`：成功和失败响应均包含 `ok`、`success`、`code`、`message`，并兼容旧字段。

## 通用约定

| 项 | 说明 |
| --- | --- |
| 登录态 | 使用 PHP Session。 |
| 字符编码 | UTF-8，JSON 使用 `application/json; charset=utf-8`。 |
| CSRF | 后台写操作和学生部分写操作需要 `csrf_token` 或 `X-CSRF-Token`。 |
| 响应结构 | 见 `docs/api-contract.md`。 |
| 错误码 | 见 `docs/error-codes.md`。 |

## 登录与退出

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| `GET` | `/login/` 或 `/login/login.php` | 登录页。 |
| `POST` | `/login/login.php` | 提交邮箱、密码，按角色跳转到管理员、教师或学生入口。 |
| `GET` | `/login/logout.php` | 清理 Session 并退出登录。 |

## 管理员 API

统一入口：`/admin/api/index.php?act=...`

调用方式：

- 页面表单可普通提交。
- AJAX 调用应带 `X-Requested-With: XMLHttpRequest`，返回 JSON envelope。
- 写操作必须带 CSRF。

### 人员管理

| `act` | 方法 | 说明 |
| --- | --- | --- |
| `add_student` | `POST` | 新增学生账号和学生资料。 |
| `update_student` | `POST` | 编辑学生资料、状态、密码、头像等。 |
| `del_student` | `GET` | 删除学生及对应用户账号。 |
| `add_teacher` | `POST` | 新增教师账号和教师资料。 |
| `update_teacher` | `POST` | 编辑教师资料、状态、密码、头像等。 |
| `del_teacher` | `GET` | 删除教师及对应用户账号。 |
| `toggle_status` | `POST/GET` | 启用或禁用用户。 |
| `reset_password` | `POST/GET` | 将指定用户密码重置为默认值。 |
| `update_self` | `POST` | 修改当前管理员个人资料。 |

### 基础资源管理

| `act` | 方法 | 说明 |
| --- | --- | --- |
| `add_course` | `POST` | 新增课程。 |
| `update_course` | `POST` | 编辑课程。 |
| `add_classroom` | `POST` | 新增教室。 |
| `update_classroom` | `POST` | 编辑教室。 |
| `add_department` | `POST` | 新增院系。 |
| `update_department` | `POST` | 编辑院系。 |
| `add_major` | `POST` | 新增专业。 |
| `update_major` | `POST` | 编辑专业。 |

### 排课管理

| `act` | 方法 | 说明 |
| --- | --- | --- |
| `add_schedule` | `POST` | 新增开课班级、任课教师和排课记录。 |
| `update_schedule` | `POST` | 修改排课和关联开课信息。 |
| `del_schedule` | `POST/GET` | 删除排课记录。 |

### 公告管理

| `act` | 方法 | 说明 |
| --- | --- | --- |
| `add_announcement` | `POST` | 发布公告。 |
| `get_announcement` | `GET` | 获取公告详情和投放目标。 |
| `update_announcement` | `POST` | 编辑公告内容、状态和目标。 |
| `delete_announcement` | `POST` | 删除公告及目标记录。 |
| `pin_announcement` | `POST` | 设置或取消优先显示。 |

### 批量导入

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| `POST` | `/admin/api/import_students.php` | 批量导入学生 Excel/CSV。 |
| `POST` | `/admin/api/import_teachers.php` | 批量导入教师 Excel/CSV。 |

导入接口要求：

- `multipart/form-data`
- `csrf_token`
- 文件类型为 `.xlsx` 或 UTF-8 编码 `.csv`

## 教师 API

教师端接口使用 `action` 参数，均要求教师登录。多数写操作只允许操作本人任教范围内的数据。

### 教师基础接口：`/teacher/api/teacher.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_profile` | `GET` | 获取教师个人资料。 |
| `update_profile` | `POST` | 更新教师资料。 |
| `upload_avatar` | `POST` | 上传教师头像。 |
| `change_password` | `POST` | 修改密码。 |
| `get_dashboard` | `GET` | 获取教师仪表盘统计。 |
| `get_sections` | `GET` | 获取教师任教班级。 |
| `get_section_students` | `GET` | 获取班级学生列表。 |
| `get_section_exams` | `GET` | 获取班级考试记录。 |
| `save_exam` | `POST` | 保存考试成绩。 |
| `update_exam` | `POST` | 更新考试记录。 |
| `delete_exam` | `POST` | 删除考试记录。 |
| `update_letter_grade` | `POST` | 更新字母成绩。 |
| `auto_assign_grades` | `POST` | 自动分配字母成绩。 |
| `batch_import_exam` | `POST` | 批量导入考试成绩。 |
| `publish_exam` | `POST` | 发布考试并生成学生考试记录。 |
| `get_exam_events` | `GET` | 获取考试事件。 |
| `get_pending_exams` | `GET` | 获取待完成考试。 |
| `get_entry_students` | `GET` | 获取可录入成绩学生。 |
| `cancel_exam_event` | `POST` | 取消考试事件。 |

### 成绩统计接口：`/teacher/api/grades.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_final_scores` | `GET` | 获取班级期末成绩。 |
| `get_student_final_score` | `GET` | 获取单个学生期末成绩。 |
| `get_course_avg` | `GET` | 获取班级平均分。 |
| `get_student_course_avg` | `GET` | 获取学生课程平均成绩。 |
| `get_grade_distribution` | `GET` | 获取成绩分布。 |
| `get_exam_comparison` | `GET` | 获取考试类型对比。 |
| `get_student_gpa` | `GET` | 获取学生 GPA。 |

### 考勤接口：`/teacher/api/attendance.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_section_schedules` | `GET` | 获取班级排课。 |
| `get_schedule_attendance` | `GET` | 获取某排课某周考勤。 |
| `get_student_summary` | `GET` | 获取学生考勤摘要。 |
| `get_section_report` | `GET` | 获取班级考勤报表。 |
| `record_attendance` | `POST` | 记录单条考勤。 |
| `batch_record` | `POST` | 批量记录考勤。 |

### 教师课表接口：`/teacher/api/schedule.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_schedule` | `GET` | 获取指定班级课表。 |
| `get_teacher_schedule` | `GET` | 获取教师全部课表。 |
| `get_weekly_schedule` | `GET` | 获取指定周课表。 |
| `get_week_range` | `GET` | 获取可用周次范围。 |
| `add_schedule` | `POST` | 教师新增排课。 |
| `update_schedule` | `POST` | 教师修改排课。 |
| `delete_schedule` | `POST` | 教师删除排课。 |
| `check_conflicts` | `GET` | 检查排课冲突。 |

### 任教申请接口：`/teacher/api/application.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_courses_to_apply` | `GET` | 获取可申请任教课程。 |
| `apply_to_teach` | `POST` | 申请任教。 |
| `get_teaching_overview` | `GET` | 获取任教概览。 |
| `remove_teaching` | `POST` | 移除任教关系。 |
| `update_section_info` | `POST` | 更新开课班级信息。 |
| `get_enrollment_stats` | `GET` | 获取选课统计。 |
| `get_advisor_students` | `GET` | 获取导师学生。 |

### 教师公告接口：`/teacher/api/announcement.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_section_announcements` | `GET` | 获取课程公告。 |
| `get_teacher_announcements` | `GET` | 获取本人发布公告。 |
| `get_inbox` | `GET` | 获取教师公告收件箱。 |
| `post_announcement` | `POST` | 向任教班级发布公告。 |
| `update_announcement` | `POST` | 编辑本人公告。 |
| `delete_announcement` | `POST` | 删除本人公告。 |
| `pin_announcement` | `POST` | 设置或取消优先显示。 |

### 工作量接口：`/teacher/api/workload.php`

| `action` | 方法 | 说明 |
| --- | --- | --- |
| `get_semesters` | `GET` | 获取教师任教学期。 |
| `get_summary` | `GET` | 获取工作量汇总。 |
| `get_by_section` | `GET` | 按班级获取工作量统计。 |

## 学生 API

学生端接口位于 `/student/api/`，要求学生登录且账号状态为 `active`。

| 方法 | 路径 | 说明 |
| --- | --- | --- |
| `GET` | `/student/api/config.php` | 获取学生端当前学期等配置。 |
| `GET` | `/student/api/student_portal.php` | 获取学生首页摘要。 |
| `GET/POST` | `/student/api/profile.php` | 获取个人信息、更新手机号、上传头像。 |
| `POST` | `/student/api/change_pwd.php` | 修改密码。 |
| `GET` | `/student/api/sidebar.php` | 获取侧栏学生信息。 |
| `GET` | `/student/api/schedule.php` | 获取学生课表。 |
| `GET` | `/student/api/my_grades.php` | 获取学生成绩。 |
| `GET` | `/student/api/exam_info.php` | 获取考试安排。 |
| `GET` | `/student/api/announcement.php` | 获取学生可见公告。 |

### 学生选课：`/student/api/course_select.php`

| 方法 | 参数 | 说明 |
| --- | --- | --- |
| `GET` | 无 | 获取当前学期已选课程和可选课程。 |
| `POST` | `action=enroll`、`section_id`、`csrf_token` | 选课。 |
| `POST` | `action=drop`、`section_id`、`csrf_token` | 退课。 |

### 空闲教室：`/student/api/free_classroom.php`

| 方法 | 参数 | 说明 |
| --- | --- | --- |
| `GET` | 无 | 获取周次、节次、教室元数据。 |
| `GET` | `action=search`、`week`、`day_of_week`、`slot_start_id`、`slot_end_id`、`classroom_id` | 查询空闲教室或指定教室占用情况。 |

## 页面入口补充

| 角色 | 页面 |
| --- | --- |
| 管理员 | `/admin/index.php`、`/admin/student.php`、`/admin/teacher.php`、`/admin/course.php`、`/admin/schedule_manage.php`、`/admin/classroom.php`、`/admin/department.php`、`/admin/major.php`、`/admin/announcement.php`、`/admin/syslog.php`、`/admin/profile.php` |
| 教师 | `/teacher/index.php` |
| 学生 | `/student/spa.html` |

## 测试覆盖对应接口

`tests/smoke/smoke_http.php` 已覆盖：

- 登录页、管理员登录、教师登录、学生登录。
- 管理员页面访问、学生新增/编辑/删除、公告新增/读取/编辑/置顶/删除。
- 未登录后台 API、学生访问后台 API、缺失 CSRF 等安全失败场景。
- 教师成绩、考试、考勤、课表、工作量、公告只读接口。
- 学生首页、资料、课表、成绩、考试、公告、选课、空闲教室接口。
