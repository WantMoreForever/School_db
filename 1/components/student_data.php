<?php
// components/student_data.php

/**
 * 根据 user_id 读取学生完整基础信息。
 *
 * ★ 【联动修改 - 登录完成后】
 *   $uid 由 requireStudentLogin() 返回，登录页完成后
 *   requireStudentLogin() 改为读取 $_SESSION['user_id']，
 *   本函数本身无需修改。
 *
 * @param PDO $pdo
 * @param int $uid  来自 requireStudentLogin() 的 user_id
 * @return array|null  找不到学生时返回 null
 */
function getStudentBaseInfo(PDO $pdo, int $uid): ?array
{
    $stmt = $pdo->prepare("CALL sp_get_student_base_info(?)");
    $stmt->execute([$uid]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }

    $uploadDir     = 'uploads/';
    $avatarFile    = $row['image'] ?? '';
    $hasAvatar     = ($avatarFile && file_exists($uploadDir . $avatarFile));
    $avatarPath    = $hasAvatar ? $uploadDir . $avatarFile : $uploadDir . 'RC.PNG';

    return [
        'user_id'         => (int)$row['user_id'],
        'name'            => $row['name'],
        'student_id'      => $row['student_no'],
        'gender'          => $row['gender'] === 'male' ? '男' : ($row['gender'] === 'female' ? '女' : '其他'),
        'email'           => $row['email'],
        'phone'           => $row['phone'] ?? '未填写',
        'grade'           => $row['enrollment_year'],
        'major'           => $row['dept_name'],
        'dept_name'       => $row['dept_name'],
        'status'          => $row['status'] === 'active' ? '正常' : ($row['status'] === 'inactive' ? '停用' : '封禁'),
        'avatar_initials' => mb_substr($row['name'], 0, 1),
        'avatar_path'     => $avatarPath,
        'has_avatar'      => $hasAvatar,
        'enrollment_year' => $row['enrollment_year'],
        'grade_label'     => $row['grade'] ?? '—',
        'raw_image'       => $avatarFile,
    ];
}