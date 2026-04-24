# EduManage 索引说明汇报

## 1. 文档目的

本文档面向答辩展示、项目汇报和交接说明，重点回答三个问题：

- 当前项目为什么需要这些索引；
- 这些索引分别在什么真实业务链路中发挥作用；
- 如何用 `SHOW INDEX` 与 `EXPLAIN` 证明它们确实服务了当前系统。

本文档不重复 `docs/index-design.md` 的工程化索引清单，而是按当前项目已经实现的业务路径进行解释。所有说明均以 `school_db_backup.sql`、`config/paths.php`、三端页面与接口代码为准。

## 2. 当前索引策略结论

当前 `EduManage` 的索引设计并不是“所有字段都建索引”，而是围绕五条真实高频链路展开：

1. 学生端的选课与课表查询。
2. 管理端的排课、教师冲突检测与教室冲突检测。
3. 教师端的考试事件、成绩录入与成绩统计。
4. 公告按投放范围做可见性过滤。
5. 系统日志按时间维度做清理与审计。

这些链路都已经落在当前项目代码里，例如：

- 学生端：`student/api/course_select.php`、`student/api/schedule.php`
- 管理端：`admin/api/schedule.php`
- 教师端：`teacher/api/teacher.php`、`teacher/api/announcement.php`
- 公告：`student/api/announcement.php`、`teacher/api/announcement.php`
- 审计：`components/logger.php`

## 3. 业务链路说明

### 3.1 学生选课与课表

对应页面与接口：

- 学生门户 `student/spa.html`
- 选课接口 `student/api/course_select.php`
- 课表接口 `student/api/schedule.php`
- 学生首页摘要 `student/api/student_portal.php`

当前关键索引：

- `section.idx_section_term_course(year, semester, course_id, section_id)`
- `takes.idx_takes_student_enrolled(student_id, enrolled_at)`
- `schedule.idx_schedule_section_day_time(section_id, day_of_week, start_time, end_time)`

为什么需要：

- 学生选课列表首先按当前学年学期筛选 `section`，然后再拼课程、教师和排课信息。
- 学生已选课程和首页摘要会频繁按 `student_id` 读取 `takes`，并按 `enrolled_at` 展示最近选课记录。
- 课表、冲突提示和课程详情都需要按 `section_id` 拿到该开课班级的排课明细，再按星期和时间输出。

实际收益：

- 学期筛选不需要扫描全部开课记录。
- “我已选的课程”可以直接按学生维度读取，而不是从联合主键里做额外排序。
- 课表和时间冲突判断可以先缩小到目标 `section`，再比较时间区间。

推荐验证：

```sql
SHOW INDEX FROM section;
SHOW INDEX FROM takes;
SHOW INDEX FROM schedule;
```

```sql
EXPLAIN
SELECT sec.section_id, c.name
FROM section sec
JOIN course c ON c.course_id = sec.course_id
WHERE sec.year = 2026 AND sec.semester = 'Spring';
```

```sql
EXPLAIN
SELECT section_id, enrolled_at
FROM takes
WHERE student_id = 810201
ORDER BY enrolled_at DESC;
```

### 3.2 管理端排课与冲突检测

对应页面与接口：

- 管理后台排课页 `admin/schedule_manage.php`
- 排课接口 `admin/api/schedule.php`

当前关键索引：

- `schedule.idx_schedule_section_day_time(section_id, day_of_week, start_time, end_time)`
- `schedule.idx_schedule_classroom_day_time(classroom_id, day_of_week, start_time, end_time)`
- `section.idx_section_term_course(year, semester, course_id, section_id)`

为什么需要：

- 管理端排课不是简单新增一条时间记录，而是同时维护课程开课班级、任课教师和具体教室时间。
- 新增或修改排课时，需要检查同一教师是否撞课、同一教室是否被占用。
- 页面本身也会按学年学期列出开课班级和排课信息。

实际收益：

- 以 `section_id` 为入口读取某班级课表时，可以顺着星期和时间快速输出。
- 以 `classroom_id + day_of_week` 检查教室占用时，能先锁定候选记录，再做时间重叠判断。
- 学期维度的开课列表能先按 `year + semester` 收敛结果集。

推荐验证：

```sql
SHOW INDEX FROM schedule;
SHOW INDEX FROM section;
```

```sql
EXPLAIN
SELECT sch.schedule_id, sch.start_time, sch.end_time
FROM schedule sch
WHERE sch.classroom_id = 8401
  AND sch.day_of_week = 2
  AND '08:00:00' < sch.end_time
  AND '09:40:00' > sch.start_time;
```

```sql
EXPLAIN
SELECT sch.schedule_id, sch.start_time, sch.end_time
FROM schedule sch
WHERE sch.section_id = 8501
ORDER BY sch.day_of_week, sch.start_time;
```

### 3.3 教师考试与成绩

对应页面与接口：

- 教师门户 `teacher/index.php`
- 主接口 `teacher/api/teacher.php`
- 成绩统计接口 `teacher/api/grades.php`

当前关键索引：

- `exam.idx_exam_teacher_section_event(teacher_id, section_id, exam_date, exam_type)`
- `exam.idx_exam_student_section_type_date(student_id, section_id, exam_type, exam_date)`
- `exam.idx_exam_student(student_id)`
- `exam.idx_exam_section(section_id)`

为什么需要：

- 教师端会按“某位教师 + 某个开课班级”管理考试事件、发布考试、撤销考试、录入成绩和统计未录成绩。
- 成绩页和统计过程会反复查询“某学生在某个班级某类考试下最近一次成绩”。
- 班级成绩统计、学生成绩查询也会直接按 `section_id` 或 `student_id` 过滤。

实际收益：

- 教师在一个班级下查看考试事件时，不必扫描其他教师或其他班级的考试记录。
- 最近一次期中、期末、测验成绩查询可直接沿着学生维度和考试类型维度定位。
- 班级和学生维度的基础查询不需要额外回退成全表扫描。

推荐验证：

```sql
SHOW INDEX FROM exam;
```

```sql
EXPLAIN
SELECT exam_id, exam_date, exam_type, score
FROM exam
WHERE teacher_id = 810101
  AND section_id = 8501
ORDER BY exam_date DESC, exam_type;
```

```sql
EXPLAIN
SELECT score
FROM exam
WHERE student_id = 810201
  AND section_id = 8501
  AND exam_type = 'final'
ORDER BY exam_date DESC
LIMIT 1;
```

### 3.4 公告可见范围

对应页面与接口：

- 管理员公告页 `admin/announcement.php`
- 教师公告接口 `teacher/api/announcement.php`
- 学生公告接口 `student/api/announcement.php`

当前关键索引：

- `announcement_target.idx_target_type_id_announcement(target_type, target_id, announcement_id)`
- `announcement.idx_announcement_status(status)`
- `announcement.idx_announcement_pub(published_at)`

为什么需要：

- 公告不是只按作者或主键查，而是按 `all / students / teachers / major / section` 做定向投放。
- 学生端和教师端先要根据投放范围找到候选公告，再结合状态和发布时间输出结果。
- 管理端也需要按公告状态和发布时间做列表管理。

实际收益：

- `target_type + target_id` 可以直接命中可见范围过滤入口，而不是把所有目标记录取出来再筛。
- 已发布公告分页时，可以更稳定地按发布时间排序输出。
- 班级公告、专业公告和全体公告可以共用一套结构。

推荐验证：

```sql
SHOW INDEX FROM announcement;
SHOW INDEX FROM announcement_target;
```

```sql
EXPLAIN
SELECT at.announcement_id
FROM announcement_target at
WHERE at.target_type = 'section'
  AND at.target_id = 8501;
```

```sql
EXPLAIN
SELECT a.announcement_id, a.title
FROM announcement a
WHERE a.status = 'published'
ORDER BY a.published_at DESC;
```

### 3.5 系统日志清理与审计

对应代码：

- 日志写入 `components/logger.php`
- 管理后台日志页 `admin/syslog.php`

当前关键索引：

- `system_log.idx_log_created_at(created_at)`
- `system_log.idx_log_user(user_id)`

为什么需要：

- 审计日志会随着写操作持续增长。
- 当前日志清理主要基于 `created_at` 做时间条件删除。
- 日志查看页又会按操作人反查行为记录。

实际收益：

- 历史日志清理不需要扫描整张日志表。
- 后台审计页按用户定位日志时可以更快缩小结果集。

推荐验证：

```sql
SHOW INDEX FROM system_log;
```

```sql
EXPLAIN
DELETE FROM system_log
WHERE created_at < '2025-01-01 00:00:00';
```

## 4. 已反映到当前结构的冗余索引清理

当前结构已经删除了一批被联合索引左前缀覆盖、或与现有唯一索引重复的旧索引，主要包括：

- `user.uq_email`
- `takes.idx_takes_section_status`
- `takes.idx_takes_student_status`
- `section.idx_section_course`
- `section.idx_section_year_sem`
- `schedule.idx_schedule_section`
- `schedule.idx_schedule_classroom`
- `announcement_target.idx_target_lookup`

这说明当前索引策略已经从“单列叠加”转向“围绕业务链路设计联合索引”，重点保留真正服务查询路径的索引组合。

## 5. 汇报时可直接使用的结论

- 当前索引并非为了数据库好看，而是直接服务学生选课、管理员排课、教师成绩管理、公告投放和系统审计。
- 新增的联合索引主要解决的是“先过滤、再排序、再做时间区间判断”的真实页面场景。
- 冗余索引已经在当前结构中清理，避免了重复维护带来的写入成本。
- 项目当前没有额外的独立索引脚本，运行中的数据库应直接以 `school_db_backup.sql` 为准。
- 如果需要证明索引有效，优先使用 `SHOW INDEX` 和 `EXPLAIN`，不要只停留在文档描述层面。
