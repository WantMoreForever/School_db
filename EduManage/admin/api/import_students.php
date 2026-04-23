<?php

declare(strict_types=1);

/**
 * admin/api/import_students.php
 * 学生批量导入接口：处理学生模板上传、解析、校验和入库。
 */

require_once __DIR__ . '/import_common.php';

$pdo = admin_import_bootstrap();
admin_import_require_post_csrf();

$expectedHeaders = ['姓名', '邮箱', '密码', '性别', '手机号', '学号', '入学年份', '年级', '院系', '专业'];

try {
    $rows = admin_import_read_rows_from_upload($_FILES['import_file'] ?? null);
} catch (Throwable $e) {
    admin_import_json_response(false, ['error' => $e->getMessage()], 400);
}

if (empty($rows)) {
    admin_import_json_response(false, ['error' => '导入文件为空'], 400);
}

$headerRow = array_shift($rows);
if (!admin_import_validate_header(is_array($headerRow) ? $headerRow : [], $expectedHeaders)) {
    admin_import_json_response(false, [
        'error' => '表头不符合固定模板，请使用系统提供的模板后再导入',
        'expected_headers' => $expectedHeaders,
    ], 400);
}

$departments = [];
$deptStmt = $pdo->query('SELECT dept_id, dept_name FROM department ORDER BY dept_id');
foreach ($deptStmt ? $deptStmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
    $departments[strtolower(trim((string) $row['dept_name']))] = [
        'dept_id' => (int) $row['dept_id'],
        'dept_name' => (string) $row['dept_name'],
    ];
}

$majors = [];
$majorStmt = $pdo->query('SELECT major_id, major_name, dept_id FROM major ORDER BY major_id');
foreach ($majorStmt ? $majorStmt->fetchAll(PDO::FETCH_ASSOC) : [] as $row) {
    $majors[strtolower(trim((string) $row['major_name']))] = [
        'major_id' => (int) $row['major_id'],
        'major_name' => (string) $row['major_name'],
        'dept_id' => (int) $row['dept_id'],
    ];
}

$existingEmails = admin_import_fetch_simple_map($pdo, 'SELECT email FROM user', 'email');
$existingPhones = admin_import_fetch_simple_map($pdo, 'SELECT phone FROM user WHERE phone IS NOT NULL AND phone <> \'\'', 'phone');
$existingStudentNos = admin_import_fetch_simple_map($pdo, 'SELECT student_no FROM student', 'student_no');

$seenEmails = [];
$seenPhones = [];
$seenStudentNos = [];
$successCount = 0;
$failCount = 0;
$errors = [];

$insertUser = $pdo->prepare(
    "INSERT INTO user (name, email, password, status, gender, phone, image) VALUES (?, ?, ?, 'active', ?, ?, NULL)"
);
$insertStudent = $pdo->prepare(
    'INSERT INTO student (user_id, student_no, grade, enrollment_year, dept_id, major_id) VALUES (?, ?, ?, ?, ?, ?)'
);

foreach ($rows as $index => $row) {
    $fileRow = $index + 2;
    $normalized = admin_import_trim_trailing_empty_cells(admin_import_normalize_row(is_array($row) ? $row : []));
    if (empty($normalized) || admin_import_is_row_empty($normalized)) {
        continue;
    }
    if (count($normalized) > count($expectedHeaders)) {
        $failCount++;
        $errors[] = '第 ' . $fileRow . ' 行列数超过模板要求';
        continue;
    }

    $normalized = array_pad($normalized, count($expectedHeaders), '');
    $record = array_combine($expectedHeaders, $normalized);
    if (!is_array($record)) {
        $failCount++;
        $errors[] = '第 ' . $fileRow . ' 行解析失败';
        continue;
    }

    $name = trim($record['姓名']);
    $email = admin_import_normalize_email($record['邮箱']);
    $password = trim($record['密码']);
    $gender = admin_import_map_gender($record['性别']);
    $phone = trim($record['手机号']);
    $studentNo = trim($record['学号']);
    $enrollmentYear = trim($record['入学年份']);
    $grade = trim($record['年级']);
    $deptName = trim($record['院系']);
    $majorName = trim($record['专业']);

    $rowErrors = [];

    if ($name === '') {
        $rowErrors[] = '姓名不能为空';
    }
    if ($msg = admin_import_validate_school_email($email)) {
        $rowErrors[] = $msg;
    }
    if ($password === '') {
        $rowErrors[] = '密码不能为空';
    }
    if ($gender === null) {
        $rowErrors[] = '性别必须填写为 男、女、其他 或 male、female、other';
    }
    if ($msg = admin_import_validate_phone($phone)) {
        $rowErrors[] = $msg;
    }
    if ($msg = admin_import_validate_student_no($studentNo)) {
        $rowErrors[] = $msg;
    }
    if (!preg_match('/^\d{4}$/', $enrollmentYear) || (int) $enrollmentYear < 2000 || (int) $enrollmentYear > 2099) {
        $rowErrors[] = '入学年份必须为 2000-2099 之间的四位年份';
    }
    if ($deptName === '') {
        $rowErrors[] = '院系不能为空';
    }

    $deptKey = strtolower($deptName);
    $majorKey = strtolower($majorName);
    $dept = $departments[$deptKey] ?? null;
    $major = $majorName !== '' ? ($majors[$majorKey] ?? null) : null;

    if ($dept === null) {
        $rowErrors[] = '院系不存在：' . $deptName;
    }
    if ($majorName !== '' && $major === null) {
        $rowErrors[] = '专业不存在：' . $majorName;
    }
    if ($dept !== null && $major !== null && (int) $major['dept_id'] !== (int) $dept['dept_id']) {
        $rowErrors[] = '专业“' . $majorName . '”不属于院系“' . $deptName . '”';
    }

    if (isset($existingEmails[$email]) || isset($seenEmails[$email])) {
        $rowErrors[] = '邮箱已存在：' . $email;
    }
    if ($phone !== '' && (isset($existingPhones[strtolower($phone)]) || isset($seenPhones[strtolower($phone)]))) {
        $rowErrors[] = '手机号已存在：' . $phone;
    }
    if (isset($existingStudentNos[strtolower($studentNo)]) || isset($seenStudentNos[strtolower($studentNo)])) {
        $rowErrors[] = '学号已存在：' . $studentNo;
    }

    if (!empty($rowErrors)) {
        $failCount++;
        $errors[] = '第 ' . $fileRow . ' 行：' . implode('；', $rowErrors);
        continue;
    }

    try {
        $pdo->beginTransaction();
        $insertUser->execute([
            $name,
            $email,
            password_hash($password, PASSWORD_DEFAULT),
            $gender,
            $phone !== '' ? $phone : null,
        ]);
        $userId = (int) $pdo->lastInsertId();

        $insertStudent->execute([
            $userId,
            $studentNo,
            $grade !== '' ? $grade : null,
            (int) $enrollmentYear,
            (int) $dept['dept_id'],
            $major !== null ? (int) $major['major_id'] : null,
        ]);
        $pdo->commit();

        $successCount++;
        $seenEmails[$email] = true;
        if ($phone !== '') {
            $seenPhones[strtolower($phone)] = true;
        }
        $seenStudentNos[strtolower($studentNo)] = true;
        $existingEmails[$email] = true;
        if ($phone !== '') {
            $existingPhones[strtolower($phone)] = true;
        }
        $existingStudentNos[strtolower($studentNo)] = true;

        admin_import_sys_log($pdo, '批量导入学生: ' . $name . ' (ID: ' . $userId . ')', 'user,student', $userId);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $failCount++;
        $errors[] = '第 ' . $fileRow . ' 行：写入失败，请检查数据唯一性或数据库约束';
    }
}

if ($successCount === 0 && $failCount === 0) {
    admin_import_json_response(false, ['error' => '未识别到可导入的数据行'], 400);
}

$message = '导入完成：成功 ' . $successCount . ' 条，失败 ' . $failCount . ' 条。';
if ($successCount > 0 && $failCount > 0) {
    $message .= ' 已成功的数据不会回滚。';
}

admin_import_json_response(true, [
    'message' => $message,
    'success_count' => $successCount,
    'fail_count' => $failCount,
    'errors' => array_slice($errors, 0, 50),
]);
