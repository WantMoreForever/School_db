# EduManage 权限矩阵与审计说明

本文档记录当前课程系统的主要角色、接口权限和写操作审计要求。

## 角色

| 角色 | 入口 | 身份校验 |
| --- | --- | --- |
| 管理员 | `/admin/*`、`/admin/api/index.php` | `admin` 表存在当前 `user_id` |
| 超级管理员 | `/admin/admin_manage.php` | `admin.role = super_admin` |
| 教师 | `/teacher/index.php`、`/teacher/api/*` | `teacher` 表存在当前 `user_id` 或 `teacher_id` |
| 学生 | `/student/spa.html`、`/student/api/*` | 已登录且 `user.status = active` |

系统必须保留至少一个超级管理员。超级管理员需要同时满足 `user.status = active`、`user.user_id = admin.user_id`、`admin.role = super_admin`。必要字段为 `user.user_id`、`user.email`、`user.password`、`user.name`、`user.status`、`admin.user_id`、`admin.role`。

## 主要权限矩阵

| 功能 | 管理员 | 超级管理员 | 教师 | 学生 |
| --- | --- | --- | --- | --- |
| 后台首页、课程、排课、教室、院系、专业 | 允许 | 允许 | 禁止 | 禁止 |
| 学生/教师新增、编辑、禁用、重置密码 | 允许 | 允许 | 禁止 | 禁止 |
| 管理员账号维护 | 禁止 | 允许 | 禁止 | 禁止 |
| 系统日志查看 | 允许 | 允许 | 禁止 | 禁止 |
| 后台公告发布、编辑、删除、置顶 | 允许 | 允许 | 禁止 | 禁止 |
| 教师成绩、考试、考勤、工作量接口 | 禁止 | 禁止 | 仅本人授课范围 | 禁止 |
| 教师课程公告 | 禁止 | 禁止 | 仅本人授课或发布范围 | 禁止 |
| 学生个人资料、课表、成绩、公告 | 禁止 | 禁止 | 禁止 | 仅本人 |
| 学生选课、退课 | 禁止 | 禁止 | 禁止 | 仅本人且在开放窗口内 |

## 写操作要求

- 所有后台写操作必须校验 CSRF，AJAX 失败返回 `ERR_CSRF`。
- 多表写操作必须使用事务，例如新增学生、更新学生、发布公告、排课新增/编辑、选课/退课。
- 写操作成功应写入 `system_log`，失败且有明确上下文时也应记录原因。
- 审计日志通过 `components/logger.php` 的 `sys_log()` 与 `sys_log_build()` 写入。
- 测试数据必须可清理，smoke 测试创建的临时数据应使用唯一前缀并在 `finally` 中清除。

## 当前 smoke 覆盖

- 管理员登录、主要页面访问、后台 API 未登录/无权限/缺少 CSRF 失败。
- 管理员学生新增、编辑、删除。
- 管理员公告新增、读取、编辑、置顶、取消置顶、删除。
- 教师主要只读接口、学生主要只读接口、学生空闲教室搜索。
