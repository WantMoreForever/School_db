<?php
// components/student_data.php

declare(strict_types=1);

function getStudentBaseInfo(PDO $pdo, int $uid): ?array
{
    try {
        $rows = function_exists('app_call_rows')
            ? app_call_rows($pdo, 'sp_project_get_student_base_info', [$uid])
            : [];
        $row = $rows[0] ?? null;
    } catch (Throwable $e) {
        $stmt = $pdo->prepare("
            SELECT u.*, s.student_no, s.grade, s.enrollment_year, d.dept_name, m.major_name
            FROM user u
            JOIN student s ON s.user_id = u.user_id
            JOIN department d ON d.dept_id = s.dept_id
            LEFT JOIN major m ON m.major_id = s.major_id
            WHERE u.user_id = ?
        ");
        $stmt->execute([$uid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$row) {
        return null;
    }

    $uploadDir = rtrim(app_avatar_dir(), "\\/") . DIRECTORY_SEPARATOR;
    $avatarFile = (string) ($row['image'] ?? '');
    $hasAvatar = (
        $avatarFile !== '' &&
        strcasecmp($avatarFile, 'RC.png') !== 0 &&
        file_exists($uploadDir . basename($avatarFile))
    );
    $avatarPath = $hasAvatar ? app_avatar_url($avatarFile) : app_default_avatar_url();

    $name = (string) ($row['name'] ?? '');
    $avatarInitial = '';
    if ($name !== '') {
        $avatarInitial = function_exists('mb_substr')
            ? (string) mb_substr($name, 0, 1, 'UTF-8')
            : substr($name, 0, 1);
    }

    $gender = match ((string) ($row['gender'] ?? '')) {
        'male' => '男',
        'female' => '女',
        default => '未填写',
    };

    $status = match ((string) ($row['status'] ?? '')) {
        'active' => '正常',
        'inactive' => '已停用',
        default => '未知',
    };

    return [
        'user_id' => (int) $row['user_id'],
        'name' => $name,
        'student_id' => (string) ($row['student_no'] ?? ''),
        'gender' => $gender,
        'email' => (string) ($row['email'] ?? ''),
        'phone' => (string) ($row['phone'] ?? ''),
        'grade' => $row['grade'] ?? null,
        'major' => (string) ($row['major_name'] ?? $row['dept_name'] ?? ''),
        'major_name' => $row['major_name'] ?? null,
        'dept_name' => (string) ($row['dept_name'] ?? ''),
        'status_raw' => (string) ($row['status'] ?? ''),
        'status' => $status,
        'avatar_initials' => $avatarInitial,
        'avatar_path' => $avatarPath,
        'has_avatar' => $hasAvatar,
        'enrollment_year' => $row['enrollment_year'] ?? null,
        'grade_label' => (string) ($row['grade'] ?? '未设置'),
        'raw_image' => $avatarFile,
    ];
}
