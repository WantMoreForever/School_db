<?php

/**
 * 认证、角色与后台安全动作配置
 *
 * 负责范围：
 * - 用户角色对应的数据表
 * - 登录后的默认跳转页面
 * - 特殊角色（如 super_admin）标识
 * - 后台写操作需要保护的动作列表
 *
 * 修改影响：
 * - 会影响登录分流、权限判断、后台页面可见性、CSRF 保护范围
 */

return [
    'role_tables' => [
        // 管理员角色对应的数据表。
        // 用途：登录后通过检查 admin 表判断是否为管理员。
        'admin' => 'admin',
        // 教师角色对应的数据表。
        'teacher' => 'teacher',
        // 学生角色对应的数据表。
        'student' => 'student',
    ],

    'role_home' => [
        // 管理员登录成功后的默认首页。
        // 说明：数组写法会交给 app_catalog_url() 解析成统一路径。
        'admin' => ['admin', 'pages', 'index'],
        // 教师登录成功后的默认首页。
        'teacher' => ['teacher', 'pages', 'index'],
        // 学生登录成功后的默认首页。
        'student' => ['student', 'pages', 'spa'],
    ],

    'role_home_fragments' => [
        // 学生端默认定位到单页应用中的 #portal 区块。
        // 修改影响：学生登录后进入 SPA 时的默认视图。
        'student' => '#portal',
    ],

    // 超级管理员角色值。
    // 用途：区分普通管理员与可管理管理员账号的 super_admin。
    // 修改影响：管理员管理页、菜单显示、权限校验。
    'super_admin_role' => 'super_admin',

    // 管理端人员邮箱允许的学校域名。
    // 用途：统一后台新增管理员、学生、教师以及批量导入时的邮箱后缀校验规则。
    // 示例：school.edu
    // 修改影响：管理员管理、人员表单、导入校验都会同步使用这个域名规则。
    'school_email_domain' => 'school.edu',

    'admin_unsafe_actions' => [
        // 这些动作属于后台写操作或敏感状态变更。
        // 用途：统一要求 CSRF 校验，避免后台被伪造请求篡改数据。
        // 修改影响：管理员端增删改行为的安全保护范围。
        'add_student',
        'del_student',
        'add_teacher',
        'del_teacher',
        'toggle_status',
        'reset_password',
        'update_student',
        'update_teacher',
        'update_self',
        'add_course',
        'update_course',
        'add_announcement',
        'update_announcement',
        'delete_announcement',
        'pin_announcement',
        'add_classroom',
        'update_classroom',
        'add_schedule',
        'update_schedule',
        'del_schedule',
        'add_department',
        'update_department',
        'add_major',
        'update_major',
    ],
];
