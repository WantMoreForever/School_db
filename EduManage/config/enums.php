<?php

/**
 * 业务枚举与标签配置
 *
 * 负责范围：
 * - 角色、账号状态、性别、学期、考试类型等固定值
 * - 前端和后端都需要共享的展示标签
 *
 * 修改影响：
 * - 会影响表单下拉、页面标签、接口返回的枚举说明
 * - 如果修改的是“键名”而不是中文标签，还会影响数据库兼容性与业务判断
 */

return [
    // 系统支持的用户角色。
    // 用途：统一角色名集合，便于后续校验和文档说明。
    'roles' => ['admin', 'teacher', 'student'],

    // 管理后台支持的管理员角色。
    // 注意：这里的键值通常与数据库 admin.role 字段保持一致。
    // 修改影响：后台权限判断、管理员管理逻辑。
    'admin_roles' => ['admin', 'super_admin'],

    // user.status 可用状态集合。
    // 修改影响：登录判断、账号启停、前端状态展示。
    'user_status' => ['active', 'inactive', 'banned'],

    'gender' => [
        // 键名用于存储或逻辑判断，值用于中文展示。
        'male' => '男',
        'female' => '女',
        'other' => '其他',
    ],

    'semester' => [
        // 注意：Spring / Fall 键名需要与数据库 section.semester、过程参数保持兼容。
        // 值用于页面显示。
        'Spring' => '春季学期',
        'Fall' => '秋季学期',
    ],

    'exam_type' => [
        // 考试类型枚举。
        // 修改影响：教师端考试管理、学生端考试列表、成绩统计。
        'final' => '期末',
        'midterm' => '期中',
        'quiz' => '测验',
    ],

    'classroom_type' => [
        // 教室类型枚举。
        // 修改影响：教室管理、空闲教室查询、资源展示标签。
        'normal' => '普通',
        'multimedia' => '多媒体',
        'lab' => '机房',
    ],

    'attendance_status' => [
        // 考勤状态枚举。
        // 修改影响：教师考勤录入、学生考勤展示、统计报表。
        'present' => '出勤',
        'absent' => '缺勤',
        'late' => '迟到',
        'excused' => '请假',
        'not_recorded' => '未录入',
    ],

    // 教师职称候选值。
    // 修改影响：教师资料管理、人员导入模板、展示标签。
    'teacher_titles' => ['教授', '副教授', '辅导员', '讲师'],

    'announcement_targets' => [
        // 公告投放目标类型。
        // 注意：键名需要与 announcement_target.target_type 保持兼容。
        'all' => '全体',
        'students' => '学生',
        'teachers' => '教师',
        'major' => '专业',
        'section' => '班级',
    ],

    // 字母成绩枚举。
    // 修改影响：教师成绩录入、学生成绩展示、GPA 相关逻辑。
    // 末尾空字符串用于兼容“未录入成绩”的表单场景。
    'letter_grades' => ['A', 'A-', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'F', ''],
];
