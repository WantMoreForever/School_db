# 教师门户系统运行指南

## 环境要求

| 组件 | 版本要求 |
|------|---------|
| PHP  | 8.0 及以上 |
| MySQL | 8.0 及以上 |
| 浏览器 | Chrome / Edge / Firefox（现代版本）|

---

## 一、数据库初始化

### 1.1 创建数据库与基础表

```bash
mysql -u root -p < db.sql
```

### 1.2 导入存储过程与函数

依次执行以下文件（注意顺序）：

```bash
mysql -u root -p school_db < teacher_procedures.sql
mysql -u root -p school_db < api/schedule_procedures.sql
mysql -u root -p school_db < api/grades_procedures.sql
mysql -u root -p school_db < api/application_procedures.sql
```

> **提示：** 导入时若出现 `ERROR 1304 (42000): PROCEDURE already exists`，可先执行 `DROP PROCEDURE IF EXISTS ...` 后重新导入。

### 1.3 验证导入结果

```sql
USE school_db;
SHOW PROCEDURE STATUS WHERE Db = 'school_db';
SHOW FUNCTION STATUS WHERE Db = 'school_db';
SHOW TRIGGERS;
```

---

## 二、PHP 配置

### 2.1 修改数据库连接信息

编辑 `api/config.php`，修改以下常量：

```php
define('DB_HOST', 'localhost');   // 数据库地址
define('DB_PORT', 3306);          // 端口
define('DB_NAME', 'school_db');   // 数据库名
define('DB_USER', 'root');        // 用户名
define('DB_PASS', '你的密码');    // 密码
```

### 2.2 创建头像上传目录

```bash
mkdir -p uploads/avatars
# Windows:
# md uploads\avatars
```

---

## 三、启动服务

### 方式一：PHP 内置服务器（推荐开发环境）

在项目根目录（`all/`）执行：

```bash
php -S localhost:8000
```

### 方式二：XAMPP / WAMP（Windows 集成环境）

1. 将 `all/` 目录放到 Apache 的 `htdocs/` 下（或配置虚拟主机指向 `all/`）
2. 启动 Apache + MySQL
3. 访问 `http://localhost/all/index.php`

### 方式三：Nginx + PHP-FPM

Nginx 配置示例：

```nginx
server {
    listen 80;
    root /path/to/all;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 四、访问流程

### 4.1 首次访问（未登录）

1. 打开浏览器访问：`http://localhost:8000/index.php`
2. 系统检测到未登录 → **自动跳转** 到 `JiaoWu/login.php`
3. 输入教师账号（email）和密码登录
4. JiaoWu 验证通过后跳转到 `JiaoWu/teacher.php`（简单欢迎页）
5. **手动导航**回 `http://localhost:8000/index.php` 进入完整教师门户

> **说明：** 由于 JiaoWu 登录后固定跳转到自身的 teacher.php，无法自动跳回完整门户。
> 可在 `JiaoWu/teacher.php` 页面手动点击地址栏，回到 index.php。

### 4.2 已登录访问

直接访问 `http://localhost:8000/index.php`，Session 有效则直接进入门户。

### 4.3 退出登录

点击侧边栏底部的 **退出登录** 按钮，或直接访问：
```
http://localhost:8000/logout.php
```
Session 销毁后自动跳转回 JiaoWu 登录页。

---

## 五、测试账号（调试模式）

若无 JiaoWu 登录，可通过 URL 参数直接访问（开发阶段）：

```
# 按 teacher_id 直接登录（user_id=100 为默认测试教师）
http://localhost:8000/index.html

# 通过 API 参数指定教师
http://localhost:8000/api/teacher.php?action=get_profile&teacher_id=100
http://localhost:8000/api/teacher.php?action=get_profile&teacher_name=Youuy
```

---

## 六、常见问题

### Q1：页面访问返回空白或 404

- 确认 PHP 服务已启动（`php -S localhost:8000` 在 `all/` 目录下执行）
- 确认访问的是 `index.php` 而非 `index.html`（后者不会做 Session 校验）

### Q2：API 返回 `{"ok":false,"error":"..."}`

1. 检查 `api/config.php` 中的数据库密码是否正确
2. 确认存储过程已全部导入：`SHOW PROCEDURE STATUS WHERE Db='school_db';`
3. 查看 PHP 错误日志：`php -S localhost:8000 2>php_error.log`

### Q3：登录后门户显示无数据

- 确认数据库中有教师数据：`SELECT * FROM teacher LIMIT 5;`
- 确认 `teaching` 表中有该教师的记录
- 可在 URL 加 `?teacher_id=100` 切换测试教师

### Q4：头像上传失败

- 检查 `uploads/avatars/` 目录是否存在且 PHP 进程有写入权限：
  ```bash
  chmod 755 uploads/avatars   # Linux/Mac
  ```

### Q5：存储过程导入报错

```sql
-- 在 MySQL 中先设置分隔符
DELIMITER $$
-- 然后粘贴存储过程内容
-- ...
DELIMITER ;
```

或直接用命令行：
```bash
mysql -u root -p school_db --delimiter="$$" < api/application_procedures.sql
```

---

## 七、项目文件说明

| 文件 | 说明 | 是否可修改 |
|------|------|-----------|
| `index.php` | 门户入口（Session 校验）| 可改 |
| `index.html` | 前端 SPA（含全部 JS）| 可改 |
| `logout.php` | 退出登录 | 可改 |
| `style.css` | 全局样式 | 可改 |
| `api/config.php` | 数据库连接配置 | **需改密码** |
| `api/teacher.php` | 核心教师 API | 可改 |
| `api/schedule.php` | 课程表 API | 可改 |
| `api/grades.php` | 成绩统计 API | 可改 |
| `api/application.php` | 申请管理 API | 可改 |
| `db.sql` | 数据库表结构 | **不可改** |
| `JiaoWu/*` | 教务登录子系统 | **不可改** |

---

*最后更新：2026-03-31*
