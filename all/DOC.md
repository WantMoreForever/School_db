# 教师门户系统说明文档

## 一、项目概述

本系统是基于 PHP + MySQL 的**教师端管理门户**，采用单页应用（SPA）架构。通过与 JiaoWu 教务登录系统的 Session 共享实现统一身份认证，教师登录后可管理课程、成绩、考试、课程表，并申请新的教学任务。

---

## 二、系统架构

```
all/
├── index.php                  # 入口页（Session 验证 + 身份校验）
├── index.html                 # SPA 前端页面（HTML + JS）
├── logout.php                 # 退出登录
├── style.css                  # 全局样式
├── JiaoWu/                    # 独立的教务登录子系统（只读，不可修改）
│   ├── login.php              # 登录表单
│   ├── login_check.php        # 验证账号，设置 Session
│   └── teacher.php            # 登录后跳转页（简单欢迎页）
├── api/
│   ├── config.php             # 数据库配置、Session 工具函数
│   ├── teacher.php            # 教师核心 API（12个 action）
│   ├── schedule.php           # 课程表 API
│   ├── grades.php             # 成绩统计 API
│   ├── application.php        # 课程申请与选课管理 API
│   ├── teacher_procedures.sql # 核心存储过程（12个）
│   ├── schedule_procedures.sql# 课程表存储过程
│   ├── grades_procedures.sql  # 成绩统计存储过程
│   └── application_procedures.sql # 申请管理存储过程
├── db.sql                     # 原始数据库表结构（只读，共13张表）
└── uploads/avatars/           # 教师头像上传目录
```

---

## 三、登录与身份认证

### 3.1 Session 集成机制

JiaoWu 的 `login_check.php` 登录成功后设置：
```php
$_SESSION['user_id'] = $user_id;
$_SESSION['user_name'] = $user['name'];
```

本系统的 `index.php` 读取 `$_SESSION['user_id']`，查询 `teacher` 表确认其为教师角色，并同步到 `$_SESSION['teacher_id']`：

```php
// index.php 核心逻辑
$user_id = $_SESSION['user_id'] ?? $_SESSION['teacher_id'] ?? null;
if (!$user_id) { header('Location: JiaoWu/login.php'); exit; }
// 验证教师身份 -> 设置 teacher_id -> 输出 index.html
```

`api/config.php` 中的 `get_teacher_id()` 按以下优先级获取教师 ID：
1. URL 参数 `teacher_name`（调试用）
2. URL 参数 `teacher_id`（调试用）
3. `$_SESSION['teacher_id']`（门户自有 Session）
4. `$_SESSION['user_id']`（JiaoWu 登录 Session，自动验证教师身份）
5. 默认 user_id=100（测试兜底）

### 3.2 登录流程

```
教师访问 index.php
      |
      +- Session 有效（user_id 存在且为教师）
      |         +-> 输出教师门户页面（index.html）
      |
      +- Session 无效
                +-> 跳转到 JiaoWu/login.php
                          |
                          +- 登录成功 -> JiaoWu/teacher.php（简单欢迎页）
                                    +-> 手动导航回 index.php（完整门户）
```

---

## 四、功能模块说明

### 4.1 仪表盘

**API：** `GET /api/teacher.php?action=get_dashboard`

显示教师总览数据：
- 授课班级数、学生总数、考试记录数、综合平均分
- 班级列表（课程名、学期、选课人数、平均分、待评人数）

**存储过程：** `sp_get_teacher_dashboard(p_teacher_id)`
- 使用 CTE + Window Function 聚合各班级统计数据

---

### 4.2 我的课程

**API：** `GET /api/teacher.php?action=get_sections`

展示教师任教的所有班级卡片，点击进入班级详情。

**班级详情：** `GET action=get_section_students&section_id=N`

显示已选课学生列表，包含：
- 测验均分、期中分、期末分、加权均分（期末50% + 期中30% + 测验20%）
- 建议等级（自动计算）、已分配等级
- 支持内联编辑等级、快速添加考试记录

---

### 4.3 成绩管理

- 手动为每位学生分配字母等级
- 自动分配：`POST action=auto_assign_grades`，根据加权均分批量计算等级

**等级映射：**

| 加权均分 | 等级 | | 加权均分 | 等级 |
|---------|------|-|---------|------|
| >= 93  | A    | | >= 77  | C+   |
| >= 90  | A-   | | >= 73  | C    |
| >= 87  | B+   | | >= 70  | C-   |
| >= 83  | B    | | >= 60  | D    |
| >= 80  | B-   | | < 60   | F    |

---

### 4.4 考试记录

- 新增/修改/删除考试记录
- 按考试类型筛选（期末 / 期中 / 测验）

**API：** `save_exam`, `update_exam`, `delete_exam`, `get_section_exams`

---

### 4.5 课程表

**API（`/api/schedule.php`）：**
- `get_week_range` — 获取学期周次范围
- `get_weekly_schedule&week=N` — 获取指定周课程表

前端渲染为可视化时间格（08:00–21:30），支持按周导航，点击查看详情。

---

### 4.6 课程管理（4个子标签页）

#### 可申请课程
- 列出所有课程，标注是否已在授课
- 申请授课：`POST action=apply_to_teach`
  - 自动查找或创建 `section` 记录，写入 `teaching` 表
  - BEFORE INSERT 触发器防止重复，事务保障原子性

#### 教学概览
- `get_teaching_overview`：CTE + ROW_NUMBER() + RANK() + SUM() OVER() 多维统计
- `get_enrollment_stats`：SUM(CASE WHEN) 聚合各状态选课人数
- 支持编辑班级容量/选课时间、移除教学任务

#### 待审核选课
- `get_pending_enrollments`：ROW_NUMBER() OVER (PARTITION BY section_id) 计算排队位置
- 批准：UPDATE takes SET status='enrolled'
- 拒绝：UPDATE takes SET status='dropped'

#### 我的指导学生
- 读取 `advisor` 表，调用 `fn_get_student_course_gpa()` 计算 GPA
- RANK() OVER (ORDER BY gpa DESC) 排名

---

### 4.7 个人设置

- 编辑姓名、电话、性别
- 上传头像（JPEG/PNG/GIF/WebP，最大 2MB）

---

## 五、数据库对象总览

| 类型 | 数量 | 说明 |
|------|------|------|
| 原始表 | 13 | user, teacher, student, department, course, section, teaching, takes, exam, schedule, restriction, admin, advisor |
| 存储过程 | 19 | 12 核心 + 5 课程表 + 1 考试分数 + 1 查询 |
| 函数 | 7 | 3 核心 + 2 冲突检查 + 1 成绩 + 1 GPA |
| 触发器 | 1 | trg_no_duplicate_teaching（BEFORE INSERT ON teaching）|
| 视图 | 1 | v_teaching_overview（教学总览宽表）|

---

## 六、关键 SQL 技术

| 技术 | 使用场景 |
|------|---------|
| WITH CTE | 仪表盘聚合、教学概览 |
| ROW_NUMBER() OVER | 选课排队位置 |
| RANK() OVER | 班级选课率排名、GPA 排名 |
| SUM/AVG() OVER() | 累计统计 |
| SUM(CASE WHEN) | 各状态人数统计 |
| EXISTS 子查询 | 标注是否已在授课 |
| 事务 + 回滚 | 申请授课原子操作 |
| BEFORE INSERT 触发器 | 防止重复 teaching 记录 |
| SIGNAL SQLSTATE | 触发器向存储过程传递错误 |
| DECLARE EXIT HANDLER | 存储过程捕获触发器信号 |

---

## 七、前端架构

- 纯 Vanilla JS，对象字面量模块模式
- 模块：Dashboard, Courses, Grades, Exams, ExamModal, Profile, Schedule, Applications, Nav, Modal, Toast
- API 调用：apiFetch / apiGet / apiPost 统一封装
- 样式：style.css（macOS Ventura 风格，CSS 变量 + Flex/Grid）

---

## 八、安全机制

1. **权限校验**：每个 API 端点验证教师身份
2. **SQL 注入防护**：PDO 预编译语句
3. **冲突检查**：课程表时间冲突、重复任课在存储过程层检查
4. **文件上传安全**：校验 MIME 类型 + 大小限制

---

*最后更新：2026-03-31*
