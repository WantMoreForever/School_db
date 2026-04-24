# EduManage 索引设计说明

## 1. 文档目的

本文档基于当前项目 PHP 查询、`school_db_backup.sql` 中已经落地的表结构、视图和存储过程整理，用来说明：

- 哪些 SQL 属于高频或关键业务查询；
- 当前实际索引覆盖了哪些路径；
- 本轮已经新增了哪些索引；
- 哪些冗余索引已经从当前结构中清理；
- 后续开发新增查询时，如何判断是否需要补索引。

当前仓库没有单独提供额外的索引变更脚本；本文档描述的索引状态应以 `school_db_backup.sql` 中已经落地的结构为准。如需汇报型说明，可结合 `docs/index-explain-report.md` 一起阅读。

重要原则：

- 不为每个字段盲目建索引；
- 优先服务高频查询、关键业务链路和明显慢查询；
- 联合索引优先围绕 `WHERE`、`JOIN`、`ORDER BY` 的组合设计；
- 如果新联合索引已经覆盖旧单列索引的左前缀，要评估是否可以删掉旧索引；
- 所有建议都应结合 `EXPLAIN` 和真实数据量验证。

## 2. 查询画像

### 2.1 选课与课表

核心位置：

- `student/api/course_select.php`
- `student/api/schedule.php`
- `student/api/student_portal.php`
- `school_db_backup.sql` 中的 `sp_project_get_available_sections`
- `sp_project_get_student_current_courses`
- `sp_project_get_student_current_schedules`

主要访问模式：

- `section` 按 `year + semester` 过滤；
- `takes` 按 `student_id` 查当前已选课程，并按 `enrolled_at` 倒序展示；
- `schedule` 按 `section_id` 取课表，并按 `day_of_week + start_time` 排序；
- 选课时间冲突需要把“当前待选 section 的课表”与“学生本学期已选 section 的课表”做区间重叠判断。

### 2.2 排课与冲突检测

核心位置：

- `admin/api/schedule.php`
- `student/api/free_classroom.php`
- `sp_project_get_classroom_conflicts`
- `sp_get_teacher_schedule`
- `sp_get_teacher_weekly_schedule`

主要访问模式：

- `schedule` 按 `classroom_id + day_of_week` 或 `section_id + day_of_week` 查询；
- 冲突判断依赖时间区间重叠：`start_time < existing_end_time` 且 `end_time > existing_start_time`；
- 排课展示通常还会按 `start_time` 输出。

### 2.3 成绩与考试

核心位置：

- `student/api/exam_info.php`
- `teacher/api/teacher.php`
- `sp_get_exam_events`
- `sp_get_pending_exams`
- `sp_get_final_scores`
- `sp_get_section_students`
- `sp_get_course_avg_by_student`

主要访问模式：

- 教师侧按 `teacher_id + section_id` 管理考试事件、统计未录成绩、撤销考试；
- 学生侧或班级成绩页会反复按 `student_id + section_id + exam_type` 取最近一次成绩；
- 成绩列表通常还会按 `exam_date` 排序。

### 2.4 公告可见范围

核心位置：

- `student/api/announcement.php`
- `teacher/api/announcement.php`
- `admin/announcement.php`
- `sp_project_get_student_visible_announcements`
- `sp_project_get_teacher_visible_announcements`

主要访问模式：

- 先按 `announcement_target.target_type + target_id` 筛出候选公告；
- 再与 `announcement` 结合，按 `status` 和发布时间输出；
- 学生端还会叠加“专业限制”“已选班级”判定。

### 2.5 日志维护

核心位置：

- `components/logger.php`

主要访问模式：

- 后台日志列表按主键倒序读取；
- 定时或写日志时，会执行 `DELETE FROM system_log WHERE created_at < ...` 清理历史日志。

## 3. 当前已落地索引

| 索引 | 建议 SQL | 主要服务查询 | 为什么适合 | 写入开销 | 是否建议 EXPLAIN |
| --- | --- | --- | --- | --- | --- |
| `idx_section_term_course` | `CREATE INDEX idx_section_term_course ON section (year, semester, course_id, section_id);` | 选课页、学期 section 列表、排课页按学期查询 | 先按学期过滤，再按课程和 section 输出，减少 term 维度扫描与额外排序 | `section` 新增/改课时多维护 1 个联合索引 | 是 |
| `idx_schedule_section_day_time` | `CREATE INDEX idx_schedule_section_day_time ON schedule (section_id, day_of_week, start_time, end_time);` | 课程课表、教师课表、学生课表、教师冲突检测 | `section_id` 是排课查询主入口，后续紧跟星期和时间，符合“先过滤再排序/区间判断”的模式 | 排课新增/修改时多维护 1 个索引 | 是 |
| `idx_schedule_classroom_day_time` | `CREATE INDEX idx_schedule_classroom_day_time ON schedule (classroom_id, day_of_week, start_time, end_time);` | 教室冲突检测、空教室查询、教室视角排课 | 教室冲突总是先按教室和星期缩小候选集合，再做时间重叠判断 | 排课写入成本小幅增加 | 是 |
| `idx_takes_student_enrolled` | `CREATE INDEX idx_takes_student_enrolled ON takes (student_id, enrolled_at);` | 学生当前课程、学生门户最近课程 | 现有主键适合判重，不适合 `WHERE student_id = ? ORDER BY enrolled_at DESC` | 选课/退课时多维护 1 个索引 | 是 |
| `idx_exam_teacher_section_event` | `CREATE INDEX idx_exam_teacher_section_event ON exam (teacher_id, section_id, exam_date, exam_type);` | 教师考试事件、未录成绩统计、撤销考试、工作量统计 | 教师端大多按教师和班级管理考试，再按日期/类型聚合输出 | 成绩发布、录入、撤销时多维护 1 个索引 | 是 |
| `idx_exam_student_section_type_date` | `CREATE INDEX idx_exam_student_section_type_date ON exam (student_id, section_id, exam_type, exam_date);` | 班级成绩页、学生成绩页、期末成绩子查询 | 很多查询都在做“某学生某班某考试类型最近一次成绩”查找，这个索引命中率很高 | 成绩写入时多维护 1 个索引 | 是 |
| `idx_target_type_id_announcement` | `CREATE INDEX idx_target_type_id_announcement ON announcement_target (target_type, target_id, announcement_id);` | 公告可见范围判断 | 让 `target_type + target_id` 命中后直接拿到 `announcement_id`，减少回表 | 公告投放目标写入量通常较低，成本可接受 | 是 |
| `idx_log_created_at` | `CREATE INDEX idx_log_created_at ON system_log (created_at);` | 历史日志清理 | 日志删除目前按时间条件过滤，没有索引时会扫描整表 | 写日志时多维护 1 个时间索引 | 是 |

## 4. 联合索引字段顺序说明

### 4.1 `section (year, semester, course_id, section_id)`

字段顺序理由：

- `year`、`semester` 是学期维度的第一过滤条件；
- `course_id`、`section_id` 对应列表页输出顺序；
- 这样既照顾 `WHERE year = ? AND semester = ?`，也兼顾 `ORDER BY course_id, section_id`。

### 4.2 `schedule (section_id, day_of_week, start_time, end_time)`

字段顺序理由：

- 多数课表查询都是先确定 `section_id`；
- `day_of_week` 和 `start_time` 紧跟其后，有利于按课表顺序输出；
- `end_time` 主要用于时间区间重叠判断，放在后位即可。

### 4.3 `schedule (classroom_id, day_of_week, start_time, end_time)`

字段顺序理由：

- 教室冲突检测和教室维度排课都先按 `classroom_id` 缩小范围；
- 再按 `day_of_week` 过滤单日排课；
- 时间重叠判断最后依赖 `start_time`、`end_time`。

### 4.4 `takes (student_id, enrolled_at)`

字段顺序理由：

- `student_id` 是筛选条件；
- `enrolled_at` 是常见排序字段；
- 现有主键 `(student_id, section_id)` 更适合判重，不适合“按选课时间倒序”。

### 4.5 `exam (teacher_id, section_id, exam_date, exam_type)`

字段顺序理由：

- 教师端几乎都先按 `teacher_id + section_id` 定位业务范围；
- `exam_date` 常用于事件列表排序；
- `exam_type` 常用于事件区分、补录和分组。

### 4.6 `exam (student_id, section_id, exam_type, exam_date)`

字段顺序理由：

- 班级成绩和学生成绩相关查询，第一步总是先确定学生；
- 然后是班级和考试类型；
- `exam_date` 放最后，方便“最近一次成绩”这类查询。

### 4.7 `announcement_target (target_type, target_id, announcement_id)`

字段顺序理由：

- 公告可见范围查询最常从“目标类型 + 目标对象”出发；
- `announcement_id` 放在最后，可作为连接 `announcement` 的输出列。

## 5. 已完成清理的冗余索引

| 索引 | 所在表 | 冗余原因 | 建议 |
| --- | --- | --- | --- |
| `uq_email` | `user` | 与 `uq_user_email` 完全重复，都是 `email` 唯一索引 | 当前结构中已删除 |
| `idx_takes_section_status` | `takes` | 与 `idx_takes_section` 完全重复，字段集合相同 | 当前结构中已删除 |
| `idx_takes_student_status` | `takes` | 被主键 `(student_id, section_id)` 左前缀覆盖 | 当前结构中已删除 |
| `idx_section_course` | `section` | 被唯一索引 `uq_section(course_id, semester, year)` 左前缀覆盖 | 当前结构中已删除 |
| `idx_section_year_sem` | `section` | 被 `idx_section_term_course(year, semester, course_id, section_id)` 左前缀覆盖 | 当前结构中已删除 |
| `idx_schedule_section` | `schedule` | 被 `idx_schedule_section_day_time(section_id, ...)` 左前缀覆盖 | 当前结构中已删除 |
| `idx_schedule_classroom` | `schedule` | 被 `idx_schedule_classroom_day_time(classroom_id, ...)` 左前缀覆盖 | 当前结构中已删除 |
| `idx_target_lookup` | `announcement_target` | 被 `idx_target_type_id_announcement(target_type, target_id, announcement_id)` 左前缀覆盖 | 当前结构中已删除 |

## 6. 当前不建议新增索引的表

### 6.1 `attendance`

当前已有：

- 唯一索引 `uq_attendance(schedule_id, student_id, week)`
- 单列索引 `student_id`
- 单列索引 `schedule_id`

原因：

- 考勤读取主要围绕 `schedule_id + student_id + week`、`student_id`、`schedule_id`；
- 现有结构已经覆盖教师录入、按节次查考勤、按学生统计考勤的主要路径；
- 继续叠加联合索引，收益不如前面几张核心业务表明显。

### 6.2 `teaching`

当前已有：

- 主键 `(teacher_id, section_id)`
- 索引 `section_id`

原因：

- 教师维度和班级维度的联查入口已经被很好覆盖；
- 冲突检测的瓶颈主要在 `schedule`，而不是 `teaching`。

### 6.3 `user`

当前已有：

- `email` 唯一索引
- `phone` 唯一索引
- `status` 索引

原因：

- 登录、查重、后台启用禁用都已经能命中现有索引；
- 当前更值得处理的是重复唯一索引，而不是继续新增。

## 7. 建议验证方式

### 7.1 在真实库校验索引状态

建议执行：

```sql
SHOW INDEX FROM section;
SHOW INDEX FROM schedule;
SHOW INDEX FROM takes;
SHOW INDEX FROM exam;
SHOW INDEX FROM announcement_target;
SHOW INDEX FROM system_log;
```

如果数据库是由当前 `school_db_backup.sql` 导入的，理论上这些索引已经存在；这一步主要用于确认运行中的实例没有落后于仓库结构。

### 7.2 对关键 SQL 做 EXPLAIN

优先验证这几类：

- 学生选课列表
- 学生当前课程
- 教师冲突检测
- 教室冲突检测
- 教师考试事件列表
- 学生成绩/班级成绩页
- 公告可见性分页查询
- 日志清理语句

建议关注：

- `type` 是否从 `ALL` 下降为 `ref`、`range` 或 `const`
- `key` 是否命中新建索引
- `rows` 是否明显减少
- `Extra` 中是否减少 `Using filesort`、`Using temporary`

### 7.3 再执行业务回归

至少检查：

- 学生端：选课、已选课程、课表、成绩、公告、空教室
- 教师端：课表、成绩录入、考试事件、考勤、公告
- 管理端：排课、教室冲突、公告管理、日志页

## 8. 后续开发如何判断要不要加索引

可以按下面顺序判断：

1. 先确认这个查询是否真的是高频、核心链路或慢查询。
2. 先看现有主键、唯一索引、外键索引是否已经覆盖。
3. 如果查询是 `WHERE + ORDER BY` 组合，不要只给 `WHERE` 字段加单列索引，要一起看排序字段。
4. 如果查询经常同时出现多个条件，优先考虑联合索引，而不是堆多个单列索引。
5. 如果一个新联合索引已经覆盖旧索引左前缀，要评估是否删掉旧索引。
6. 任何索引变更都要结合 `EXPLAIN` 和真实数据量，而不是只看 SQL 语句长得“像会慢”。
