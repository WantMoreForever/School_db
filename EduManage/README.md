# EduManage 教学管理系统

英文名称：EduManage (Teaching Management System)

`EduManage` 是一个面向高校教学场景的教学管理系统，围绕管理员、教师、学生三类角色组织项目结构，覆盖基础数据维护、排课、选课、考试、成绩、考勤、公告与工作量统计等核心业务。

项目支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx，适合课程设计展示、课堂验收和答辩演示。

## 项目概览

| 角色 | 入口 | 主要职责 |
| --- | --- | --- |
| 管理员 | `/admin/index.php` | 维护人员、院系、专业、课程、教室、排课、公告和系统日志。 |
| 教师 | `/teacher/index.php` | 管理任教课程、考试、成绩、考勤、课程公告和工作量。 |
| 学生 | `/student/spa.html` | 查看门户、选课、课表、成绩、考试安排、空闲教室和通知公告。 |

统一登录入口：

- `/login/`
- `/login/login.php`

登录成功后，系统会根据账号所属角色自动分流到对应端。

## 快速部署

1. 准备支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx。
2. 将项目目录放到站点根目录或虚拟主机目录下，并让站点根路径指向项目目录。
3. 创建数据库 `school_db`，字符集建议使用 `utf8mb4`。
4. 按顺序导入数据库脚本：
   1. `school_db_backup.sql`
   2. `tests/sql/demo_seed.sql`
5. 修改运行配置：
   - `config/database.php`
   - `config/app.php`
6. 访问系统入口并使用演示账号登录。

特别说明：

- `school_db_backup.sql` 是数据库初始化主脚本，包含当前项目使用的表、视图、函数、存储过程、触发器与索引定义。
- 系统将选课、课表、考试、成绩、考勤、工作量、公告可见范围等核心教学业务封装在存储过程中，便于统一规则、复用查询和答辩展示。

## 演示账号

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@school.edu` | `123456` |
| 教师 | `teacher@school.edu` | `123456` |
| 学生 | `student@school.edu` | `123456` |

## 当前目录结构

| 路径 | 说明 |
| --- | --- |
| `admin/` | 管理员页面、接口、脚本与样式。 |
| `teacher/` | 教师端页面与接口。 |
| `student/` | 学生端单页门户、接口、脚本与样式。 |
| `login/` | 登录与退出相关页面。 |
| `components/` | 公共数据库、认证、日志、成绩与学生数据组件。 |
| `config/` | 数据库、应用、权限、路径、前端与上传配置。 |
| `docs/` | 部署、数据库、亮点、答辩等说明文档。 |
| `tests/` | smoke 测试与测试数据脚本。 |
| `school_db_backup.sql` | 数据库初始化主脚本。 |

## 数据库设计特点

- 统一账号模型：`user` 作为主账号表，`admin`、`teacher`、`student` 作为角色扩展表。
- 结构化教学数据：`course`、`section`、`teaching`、`schedule`、`takes`、`exam`、`attendance` 组成完整教学链路。
- 丰富数据库对象：当前主脚本中包含 21 张表、5 个视图、7 个函数、60 个存储过程、16 个触发器。
- 核心教学业务过程化：教师端和学生端高频流程广泛调用存储过程，体现数据库层的业务封装能力。

## 文档入口

- `PROJECT_FUNCTION_DATABASE_DOC.md`：项目功能、目录结构与数据库总体说明。
- `docs/deployment-guide.md`：部署、配置、验收与常见问题。
- `docs/database-schema.md`：数据库结构、关系、索引与对象说明。
- `docs/project-highlights.md`：项目亮点与交付亮点摘要。
- `docs/defense-guide.md`：课程答辩介绍、讲解重点与推荐演示路线。
- `docs/user-manual.md`：三端使用手册。

建议在答辩或演示前先阅读 `docs/deployment-guide.md` 与 `docs/defense-guide.md`，再结合 `docs/database-schema.md` 讲解数据库设计亮点。
