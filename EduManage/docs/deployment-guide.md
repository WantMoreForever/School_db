# EduManage 教学管理系统部署说明

本文档用于 `EduManage` 教学管理系统的本地演示、课程验收与答辩部署。项目支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx。

## 1. 环境要求

| 组件 | 建议版本 | 说明 |
| --- | --- | --- |
| PHP | 8.0 或以上 | 建议启用错误日志，便于部署排查。 |
| MySQL | 8.0 或以上 | 需要支持视图、函数、存储过程、触发器。 |
| Web Server | Apache 或 Nginx | 站点根目录指向项目目录即可。 |
| PHP 扩展 | `pdo_mysql`、`curl`、`mbstring`、`fileinfo`、`zip` | `zip` 用于 Excel 导入，`curl` 用于 smoke 测试。 |
| 字符集 | UTF-8 / utf8mb4 | 数据库与页面均建议统一为 UTF-8。 |

## 2. 项目放置

将项目目录放到 Web 站点根目录或虚拟主机目录下，例如：

```text
D:\web\EduManage
/var/www/EduManage
```

部署原则：

- 站点根路径直接指向项目目录；
- Apache 可使用仓库中的 `.htaccess`；
- Nginx 可参考仓库根目录的 `nginx.conf.example`。

## 3. 数据库准备

### 3.1 创建数据库

建议数据库名为 `school_db`，字符集使用 `utf8mb4`：

```sql
CREATE DATABASE IF NOT EXISTS school_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
```

### 3.2 导入顺序

新环境必须按以下顺序导入：

```text
school_db_backup.sql
tests/sql/demo_seed.sql
```

说明：

- `school_db_backup.sql` 是数据库初始化主脚本，包含当前项目使用的表、视图、函数、存储过程、触发器与索引定义。
- `tests/sql/demo_seed.sql` 是演示数据脚本，导入后即可使用演示账号登录。
- `tests/sql/qa_business_validation_seed.sql` 仅用于补充业务验证，不属于答辩主链路。

### 3.3 为什么先导入主脚本

项目的数据库设计强调“结构与业务并重”。`school_db_backup.sql` 不仅创建表结构，还同时创建：

- 视图；
- 函数；
- 存储过程；
- 触发器；
- 索引。

因此，新环境部署时应把 `school_db_backup.sql` 视为数据库初始化主脚本，而不是普通数据备份文件。

## 4. 配置说明

部署时优先检查以下文件：

```text
config/database.php
config/app.php
```

### 4.1 `config/database.php`

建议重点核对：

| 配置项 | 说明 |
| --- | --- |
| `host` | MySQL 主机地址 |
| `port` | MySQL 端口 |
| `database` | 数据库名，默认建议 `school_db` |
| `username` | 数据库用户名 |
| `password` | 数据库密码 |
| `charset` | 建议 `utf8mb4` |

项目默认允许通过环境变量覆盖数据库连接，但不是必须条件。常见环境变量包括：

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
```

### 4.2 `config/app.php`

建议重点核对：

| 配置项 | 说明 |
| --- | --- |
| `env` | 本地演示可用 `local`，正式环境建议 `production` |
| `debug` | 调试阶段可临时开启 |
| `timezone` | 建议保持 `Asia/Shanghai` |
| `session.cookie.secure` | HTTPS 环境建议开启 |
| `session.cookie.samesite` | 默认 `Lax` |

### 4.3 其他配置文件

| 文件 | 作用 |
| --- | --- |
| `config/auth.php` | 登录分流、角色表与后台敏感操作配置。 |
| `config/api.php` | 响应头、缓存与错误码配置。 |
| `config/enums.php` | 学期、考勤状态、教室类型等枚举标签。 |
| `config/frontend.php` | 前端资源版本号与静态资源配置。 |
| `config/upload.php` | 上传大小与文件类型限制。 |
| `config/paths.php` | 页面、接口、资源与上传路径表。 |

## 5. Web Server 配置

### 5.1 Apache

部署要点：

1. 站点根目录指向项目目录；
2. 启用 rewrite；
3. 允许 `.htaccess` 生效；
4. 访问项目根路径后会进入登录页。

### 5.2 Nginx

推荐直接参考仓库中的 `nginx.conf.example`。其中已包含：

- `/` 跳转到 `/login/`
- `/login` 规范化到 `/login/`
- PHP 请求转发
- 静态资源与入口页访问规则

## 6. 目录可写权限

需要保证以下目录或位置可写：

| 路径 | 用途 |
| --- | --- |
| `uploads/avatars/` | 用户头像上传 |
| `uploads/login/` | 登录页图片资源 |
| PHP Session 目录 | 登录态保存 |

如果默认 Session 目录不可写，项目会按应用配置尝试使用可写目录保存 Session。

## 7. 访问入口

| 角色 | 地址 |
| --- | --- |
| 登录 | `/login/` 或 `/login/login.php` |
| 管理员 | `/admin/index.php` |
| 教师 | `/teacher/index.php` |
| 学生 | `/student/spa.html` |

## 8. 演示账号

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@school.edu` | `123456` |
| 教师 | `teacher@school.edu` | `123456` |
| 学生 | `student@school.edu` | `123456` |

部署时请确认系统中至少存在一个超级管理员：

- `user.status = 'active'`
- `admin.role = 'super_admin'`

## 9. 部署后核查

### 9.1 页面访问检查

依次访问：

- `/login/login.php`
- `/admin/index.php`
- `/teacher/index.php`
- `/student/spa.html`

### 9.2 PHP 语法检查

可使用通用命令检查关键文件：

```powershell
php -l config/paths.php
php -l admin/api/shared.php
php -l student/api/helpers.php
php -l teacher/api/config.php
```

### 9.3 Smoke 测试

推荐在部署完成后执行：

```powershell
powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1
```

如果已经有 Web 服务在运行，也可以指定地址：

```powershell
powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1 -BaseUrl http://localhost
```

## 10. 数据库设计讲解建议

如果需要面向教师或答辩老师说明数据库设计，建议突出以下几点：

1. `school_db_backup.sql` 是初始化主脚本，确保一份脚本即可恢复完整数据库对象；
2. 系统不是只有数据表，还包含视图、函数、存储过程、触发器和索引；
3. 选课、课表、考试、成绩、考勤、工作量、公告可见范围等核心教学业务使用存储过程封装；
4. 触发器与约束用于保证成绩、编码、选课时间、公告投放等数据规则；
5. 索引围绕高频教学查询设计，兼顾展示性能与维护成本。

## 11. 常见问题

### 数据库连接失败

请检查：

- MySQL 是否正常启动；
- `config/database.php` 中数据库信息是否正确；
- 是否已按顺序导入 `school_db_backup.sql` 与 `tests/sql/demo_seed.sql`。

### 登录后角色不正确

请检查：

- 账号是否存在于 `user` 表；
- 对应用户是否存在于 `admin`、`teacher` 或 `student` 表；
- `user.status` 是否为 `active`。

### 页面资源 404

请检查：

- 站点根目录是否指向项目目录；
- Apache 或 Nginx 是否按项目目录模式配置；
- `.htaccess` 或 `nginx.conf.example` 是否已正确启用。

### Excel 导入不可用

请检查 PHP 是否启用了：

```text
ZipArchive
fileinfo
```

### Smoke 测试失败

建议优先查看：

```text
tests/smoke/php-server.stderr.log
tests/smoke/php-server.stdout.log
```

常见原因包括：数据库未导入、演示账号被修改、`curl` 扩展未启用或端口被占用。

## 12. 交付检查清单

- 已准备支持 PHP + MySQL 的 Web 环境、本地集成环境、Apache 或 Nginx；
- 已导入 `school_db_backup.sql` 与 `tests/sql/demo_seed.sql`；
- 已核对 `config/database.php` 与 `config/app.php`；
- 登录页、管理员端、教师端、学生端均可访问；
- `uploads/avatars/` 可写；
- 关键接口能返回 UTF-8 JSON；
- `tests/smoke/run_smoke.ps1` 已执行；
- 相关文档已随项目一并提交。
