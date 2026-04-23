# EduManage 教学管理系统部署说明

本文档用于 `EduManage` 教学管理系统的本地演示、验收和答辩部署。推荐使用 phpStudy Pro，也可以使用 Apache/Nginx + PHP-FPM。

## 1. 环境要求

| 组件 | 建议版本 | 说明 |
| --- | --- | --- |
| PHP | 8.0 或以上 | 当前 smoke 使用 PHP 8.0.2 验证通过。 |
| MySQL | 8.0 或以上 | 需要支持视图、函数、存储过程、触发器。 |
| Web Server | Apache 或 Nginx | phpstudy 集成环境可直接使用。 |
| PHP 扩展 | `pdo_mysql`、`curl`、`mbstring`、`fileinfo`、`zip` | `zip` 用于 Excel 导入，`curl` 用于 smoke 测试。 |
| 字符集 | UTF-8 / utf8mb4 | 数据库和页面均按 UTF-8 处理。 |

## 2. 项目放置

将项目目录放到站点根目录下，例如：

```text
D:\phpstudy_pro\WWW\EduManage
```

项目可以部署在站点根目录，也可以部署在子目录。`config/paths.php` 会根据当前请求路径自动识别项目 Web Base，例如 `/EduManage/`。

根路径访问规则：

- Apache 使用 `.htaccess`，访问项目根路径会跳转到 `/login/`。
- Nginx 可参考 `nginx.htaccess` 中的示例 location。

## 3. 数据库准备

### 3.1 创建数据库

建议数据库名为 `school_db`，字符集使用 `utf8mb4`：

```sql
CREATE DATABASE IF NOT EXISTS school_db
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;
```

### 3.2 导入 SQL

新环境优先导入：

```text
school_db_backup.sql
```

当前仓库主要交付文件是 `school_db_backup.sql`。如果找不到补充 SQL 文件，通常说明这些对象已经合并进当前备份或当前代码带有 SQL 回退逻辑。

### 3.3 可选索引优化脚本

如果当前环境数据量已经明显增长，或者你准备做性能验证，可以在导入数据库后，按需执行项目内整理好的索引优化脚本：

```text
tests/sql/apply_index_optimization.sql
```

配套说明文档：

```text
docs/index-design.md
```

建议执行顺序：

1. 先在测试库执行脚本中的“新增索引”部分。
2. 对学生选课、排课冲突、成绩查询、公告分页、日志清理等关键 SQL 做 `EXPLAIN`。
3. 完成页面 smoke 或人工回归。
4. 再执行脚本里的“冗余索引清理”部分。

注意：

- 该脚本属于性能优化建议，不是项目启动的硬性前置条件。
- 如果当前数据库结构与仓库内 `school_db_backup.sql` 不一致，请先核对索引名，再执行删除旧索引语句。
- 索引越多，写入成本越高，因此不要在未验证收益前继续叠加新的单列索引。

### 3.4 数据库文档入口

当前数据库相关文档建议按下面顺序查看：

- `docs/database-schema.md`
  当前字段、类型、默认值、主外键、唯一约束、索引和业务关系的权威说明。
- `docs/index-design.md`
  当前索引设计、查询路径和后续新增索引的判断方法。
- `docs/test-data-scenarios.md`
  基于当前结构整理的测试数据覆盖场景。

如果后续导入了新的 SQL 备份或对表结构做了调整，请优先同步这三份文档，而不是只修改 `PROJECT_FUNCTION_DATABASE_DOC.md` 中的摘要段落。

## 4. 配置数据库连接

数据库配置文件：

```text
config/database.php
```

默认配置项：

| 配置项 | 默认值 |
| --- | --- |
| `host` | `localhost` |
| `port` | `3306` |
| `database` | `school_db` |
| `username` | `root` |
| `charset` | `utf8mb4` |

可以通过环境变量覆盖：

```text
DB_HOST
DB_PORT
DB_NAME
DB_USER
DB_PASS
DB_CHARSET
```

phpstudy 本地演示时，通常只需要确认 `DB_PASS` 与本机 MySQL root 密码一致。

### 4.1 共享配置入口

当前运行时配置已经统一收口到 `config/`：

- `config/app_config.php`：统一配置读取入口，代码侧通过 `app_config('upload.avatar.max_size')` 这类点路径读取。
- `config/{app,api,auth,enums,frontend,upload}.php`：运行时、接口、权限、枚举、前端版本号和上传规则配置。
- `config/paths.php`：统一页面、接口、静态资源和上传目录路径。
- `config/frontend-paths.php`：把后端路径、枚举、上传限制、资源版本号输出到 `window.APP_PATHS`，供学生端和教师端前端脚本读取。

部署时如果修改了静态资源版本号、CDN 地址或前端路径，请优先修改 `config/frontend.php` 或 `config/paths.php`，不要直接改页面里的脚本地址或硬编码查询参数。

### 4.2 config 目录结构

| 文件 | 主要用途 | 修改示例 |
| --- | --- | --- |
| `config/app.php` | 设置环境、时区、Session 兜底目录、日志策略 | 修改 `timezone` 为 `UTC` |
| `config/api.php` | 配置响应头、无缓存头、CORS、统一错误码 | 收紧 `cors.allow_origin` |
| `config/auth.php` | 配置角色表、首页跳转、超管角色、学校邮箱域名、后台敏感动作 | 给新角色补登录跳转或调整后台邮箱域名 |
| `config/database.php` | 配置数据库连接 | 修改 `host`、`port` 或 `DB_PASS` |
| `config/enums.php` | 配置业务枚举与中文标签 | 调整考勤状态显示文案 |
| `config/frontend.php` | 配置静态资源版本号和 CDN | 修改 `student_css` 版本号 |
| `config/upload.php` | 配置头像上传和导入限制 | 把头像上限调到 `3 * 1024 * 1024` |
| `config/app_config.php` | 聚合所有配置文件并提供 `app_config()` | 一般不常改，只在新增配置文件时改 |
| `config/paths.php` | 统一维护项目路径目录表 | 新增前端脚本或 API 入口时登记 |
| `config/frontend-paths.php` | 输出 `window.APP_PATHS` 给前端使用 | 一般不改结构，只跟随 `paths.php` / `frontend.php` |

推荐修改方式：

```php
// 例 1：修改学生端样式版本号，强制浏览器刷新缓存
// 文件：config/frontend.php
'student_css' => getenv('ASSET_VERSION_STUDENT_CSS') ?: 'v=20260424a',

// 例 2：修改头像上传大小为 3MB
// 文件：config/upload.php
'avatar' => [
    'max_size' => 3 * 1024 * 1024,
]

// 例 3：代码中读取配置
app_config('upload.avatar.max_size', 0);
app_catalog_url('teacher', 'api', 'grades');
```

修改时需要谨慎的项：

- `config/database.php`：一旦配置错误，项目将无法连接数据库。
- `config/auth.php` 中的 `super_admin_role`：需要与数据库 `admin.role` 保持一致。
- `config/enums.php` 中的学期、公告目标、考试类型键名：通常与数据库存储值兼容，建议只改中文标签，不改键名。
- `config/paths.php`：涉及文件真实路径和 URL，改动前要确认对应文件存在。
- `config/frontend.php` 中的 CDN：如果替换为不可访问地址，会直接导致页面脚本或样式加载失败。

当前仍未完全纳入 `config/` 的硬编码配置主要有：

- 测试脚本中的演示账号和示例邮箱
- 个别后台页面中的演示数据文本

这些项目前仍属于“业务规则”或“测试样本”，后续如果要继续收口，可以再单独提一轮配置化整理。

## 5. Web Server 配置

### 5.1 phpstudy / Apache

1. 将站点目录指向项目所在目录或其上级目录。
2. 确认 Apache 已启用 rewrite。
3. 确认 `.htaccess` 可生效。
4. 浏览器访问项目地址，例如：

```text
http://localhost/EduManage/
```

如果项目直接作为站点根目录：

```text
http://localhost/
```

### 5.2 Nginx

如果项目挂载在 `/EduManage/`，可参考 `nginx.htaccess`：

```nginx
location = /EduManage {
    return 302 /EduManage/login/;
}

location = /EduManage/ {
    return 302 /EduManage/login/;
}
```

PHP 请求需要交给 PHP-FPM 或 phpstudy 的 PHP FastCGI。生产式 Nginx 配置还需要补充 `location ~ \.php$`，课程演示环境通常由 phpstudy 自动生成。

## 6. 目录权限

需要 PHP 可写：

| 目录 | 用途 |
| --- | --- |
| `uploads/avatars/` | 用户头像上传。 |
| `uploads/login/` | 登录页图片资源。 |
| PHP session 目录 | 登录 Session。若默认 session 目录不可写，`components/bootstrap.php` 会尝试使用系统临时目录。 |

Windows/phpstudy 下一般无需额外设置；Linux/Nginx 环境需要确保 Web 用户有写权限。

## 7. 访问入口

| 角色 | 地址 |
| --- | --- |
| 登录 | `/login/` 或 `/login/login.php` |
| 管理员 | `/admin/index.php` |
| 教师 | `/teacher/index.php` |
| 学生 | `/student/spa.html` |

登录后系统会按账号在 `admin`、`teacher`、`student` 表中的角色自动跳转。

## 8. 初始账号

当前 smoke 测试使用以下演示账号：

| 角色 | 邮箱 | 密码 |
| --- | --- | --- |
| 超级管理员 | `admin@school.edu` | `1` |
| 教师 | `limin@school.edu` | `1` |
| 学生 | `liuyang@school.edu` | `123456` |

如果导入的是不同版本数据，请以 `user` 表中的实际账号为准。

部署数据库时必须确认至少存在一个超级管理员：

- `user.status = 'active'`
- `user.user_id = admin.user_id`
- `admin.role = 'super_admin'`

必要字段包括 `user.user_id`、`user.email`、`user.password`、`user.name`、`user.status`、`admin.user_id`、`admin.role`。如果没有超级管理员，系统仍可登录普通管理员，但无法进入管理员账号维护页面。

## 9. 部署后检查

### 9.1 页面检查

依次访问：

- `/login/login.php`
- `/admin/index.php`
- `/teacher/index.php`
- `/student/spa.html`

### 9.2 PHP 语法检查

示例：

```powershell
D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe -l config\paths.php
D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe -l admin\api\shared.php
D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe -l student\api\helpers.php
D:\phpstudy_pro\Extensions\php\php8.0.2nts\php.exe -l teacher\api\config.php
```

### 9.3 Smoke 测试

推荐部署后运行：

```powershell
powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1
```

该脚本会启动 PHP 内置服务器并自动测试登录、主要页面、主要接口、后台写操作和安全失败场景。

如果已经有 Web 服务在运行，也可以指定地址：

```powershell
powershell -ExecutionPolicy Bypass -File tests\smoke\run_smoke.ps1 -BaseUrl http://localhost/EduManage
```

## 10. 常见问题

### 数据库连接失败

检查：

- MySQL 是否启动。
- `config/database.php` 中数据库名、用户名、密码是否正确。
- 数据库是否已导入 `school_db_backup.sql`。

### 登录后跳错角色

检查：

- `user` 表是否有账号。
- 对应账号是否存在于 `admin`、`teacher` 或 `student` 表。
- `user.status` 是否为 `active`。

### 页面资源 404

检查：

- 项目是否被移动到了新的子目录。
- `config/paths.php` 是否能从 URL 自动识别 Web Base。
- Apache rewrite 或 Nginx location 是否配置正确。

### Excel 导入不可用

检查 PHP 是否启用：

```text
ZipArchive
fileinfo
```

如果没有 `ZipArchive`，可改用 UTF-8 编码的 CSV 导入。

### Smoke 测试失败

优先查看：

```text
tests/smoke/php-server.stderr.log
tests/smoke/php-server.stdout.log
```

常见原因是数据库未导入、账号密码与测试脚本不一致、PHP curl 扩展未启用或端口被占用。

## 11. 交付检查清单

- 数据库已导入且可连接。
- 登录页可以打开。
- 管理员、教师、学生三个角色均可登录。
- `uploads/avatars/` 可写。
- 接口响应为 UTF-8 JSON。
- 后台写操作 CSRF 校验正常。
- `tests/smoke/run_smoke.ps1` 通过。
- `docs/api-list.md`、`docs/api-contract.md`、`docs/error-codes.md`、`docs/permission-matrix.md` 已随项目提交。
