<?php
/**
 * admin/api/resources.php
 * 管理后台资源接口：处理课程、教室、院系和专业等基础教学资源维护。
 */

if (!in_array($act, [
    'add_course',
    'update_course',
    'add_classroom',
    'update_classroom',
    'add_department',
    'update_department',
    'add_major',
    'update_major',
], true)) {
    return false;
}

switch ($act) {
    case 'update_course':
        $id = (int) ($_POST['course_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $credit = $_POST['credit'] ?? null;
        $hours = $_POST['hours'] ?? null;
        $description = $_POST['description'] ?? '';

        if ($id > 0) {
            try {
                $stmt = $pdo->prepare('UPDATE course SET name = ?, credit = ?, hours = ?, description = ? WHERE course_id = ?');
                $stmt->execute([$name, $credit !== '' ? $credit : null, $hours !== '' ? $hours : null, $description, $id]);

                if (function_exists('sys_log')) {
                    $desc = '修改课程: ' . ($name ?: ('ID ' . $id)) . ' (ID: ' . $id . ')';
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'course', $id);
                }
            } catch (PDOException $e) {
                admin_api_error_response($isAjax, 'course.php', '更新课程失败', 500);
            }
        }

        admin_api_success_response($isAjax, 'course.php', '更新成功');
        break;

    case 'add_course':
        $name = trim($_POST['name'] ?? '');
        $credit = $_POST['credit'] ?? 0;
        $hours = $_POST['hours'] ?? 0;
        $description = $_POST['description'] ?? '';

        $stmt = $pdo->prepare('INSERT INTO course (name, credit, hours, description) VALUES (?, ?, ?, ?)');
        $stmt->execute([$name, $credit, $hours, $description]);

        if (function_exists('sys_log')) {
            $newCourseId = (int) $pdo->lastInsertId();
            $desc = '新增课程: ' . ($name ?: ('ID ' . $newCourseId)) . ' (ID: ' . $newCourseId . ')';
            sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'course', $newCourseId);
        }

        admin_api_redirect('course.php');

    case 'add_classroom':
        $building = trim($_POST['building'] ?? '');
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 50);
        $type = $_POST['type'] ?? 'normal';

        $stmt = $pdo->prepare('SELECT classroom_id FROM classroom WHERE building = ? AND room_number = ? LIMIT 1');
        $stmt->execute([$building, $roomNumber]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'classroom.php', '该教学楼的房间号已存在');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO classroom (building, room_number, capacity, type) VALUES (?, ?, ?, ?)');
            $stmt->execute([$building, $roomNumber, $capacity, $type]);

            if (function_exists('sys_log')) {
                $newClassroomId = (int) $pdo->lastInsertId();
                $desc = '新增教室: ' . $building . '-' . $roomNumber . ' (ID: ' . $newClassroomId . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'classroom', $newClassroomId);
            }
        } catch (PDOException $e) {
            admin_api_error_response($isAjax, 'classroom.php', '添加失败', 500);
        }

        admin_api_success_response($isAjax, 'classroom.php', '添加成功');
        break;

    case 'update_classroom':
        $id = (int) ($_POST['classroom_id'] ?? 0);
        $building = trim($_POST['building'] ?? '');
        $roomNumber = trim($_POST['room_number'] ?? '');
        $capacity = (int) ($_POST['capacity'] ?? 50);
        $type = $_POST['type'] ?? 'normal';

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT classroom_id FROM classroom WHERE building = ? AND room_number = ? AND classroom_id != ? LIMIT 1');
            $stmt->execute([$building, $roomNumber, $id]);
            if ($stmt->fetch()) {
                admin_api_error_response($isAjax, 'classroom.php', '修改后的教学楼和房间号与其他教室冲突');
            }

            $stmt = $pdo->prepare('UPDATE classroom SET building = ?, room_number = ?, capacity = ?, type = ? WHERE classroom_id = ?');
            $stmt->execute([$building, $roomNumber, $capacity, $type, $id]);

            if (function_exists('sys_log')) {
                $desc = '修改教室: ' . $building . '-' . $roomNumber . ' (ID: ' . $id . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'classroom', $id);
            }
        }

        admin_api_success_response($isAjax, 'classroom.php', '更新成功');
        break;

    case 'add_department':
        $deptCode = admin_api_normalize_department_code($_POST['dept_code'] ?? '');
        $deptName = trim($_POST['dept_name'] ?? '');

        if ($msg = admin_api_validate_department_code($deptCode)) {
            admin_api_error_response($isAjax, 'department.php', $msg);
        }
        if ($deptName === '') {
            admin_api_error_response($isAjax, 'department.php', '院系名称不能为空');
        }

        $stmt = $pdo->prepare('SELECT dept_id FROM department WHERE dept_code = ? OR dept_name = ? LIMIT 1');
        $stmt->execute([$deptCode, $deptName]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'department.php', '院系代码或院系名称已存在');
        }

        try {
            $stmt = $pdo->prepare('INSERT INTO department (dept_code, dept_name) VALUES (?, ?)');
            $stmt->execute([$deptCode, $deptName]);

            if (function_exists('sys_log')) {
                $newDeptId = (int) $pdo->lastInsertId();
                $desc = '新增院系: ' . $deptName . ' (' . $deptCode . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'department', $newDeptId);
            }
        } catch (PDOException $e) {
            admin_api_error_response($isAjax, 'department.php', '新增院系失败', 500);
        }

        admin_api_success_response($isAjax, 'department.php', '新增成功');
        break;

    case 'update_department':
        $id = (int) ($_POST['dept_id'] ?? 0);
        $deptCode = admin_api_normalize_department_code($_POST['dept_code'] ?? '');
        $deptName = trim($_POST['dept_name'] ?? '');

        if ($id <= 0) {
            admin_api_error_response($isAjax, 'department.php', '缺少院系ID');
        }
        if ($msg = admin_api_validate_department_code($deptCode)) {
            admin_api_error_response($isAjax, 'department.php', $msg);
        }
        if ($deptName === '') {
            admin_api_error_response($isAjax, 'department.php', '院系名称不能为空');
        }

        $stmt = $pdo->prepare('SELECT dept_id FROM department WHERE (dept_code = ? OR dept_name = ?) AND dept_id != ? LIMIT 1');
        $stmt->execute([$deptCode, $deptName, $id]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'department.php', '院系代码或院系名称冲突');
        }

        try {
            $stmt = $pdo->prepare('UPDATE department SET dept_code = ?, dept_name = ? WHERE dept_id = ?');
            $stmt->execute([$deptCode, $deptName, $id]);

            if (function_exists('sys_log')) {
                $desc = '修改院系: ' . $deptName . ' (' . $deptCode . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'department', $id);
            }
        } catch (PDOException $e) {
            admin_api_error_response($isAjax, 'department.php', '更新院系失败', 500);
        }

        admin_api_success_response($isAjax, 'department.php', '更新成功');
        break;

    case 'add_major':
        $majorCode = admin_api_normalize_major_code($_POST['major_code'] ?? '');
        $majorName = trim($_POST['major_name'] ?? '');
        $deptId = (int) ($_POST['dept_id'] ?? 0);
        $majorColumns = admin_api_major_columns($pdo);

        if ($majorColumns['has_code']) {
            $msg = admin_api_validate_major_code($majorCode);
            if ($msg !== null) {
                admin_api_error_response($isAjax, 'major.php', $msg);
            }
        }

        if ($majorColumns['has_dept']) {
            if ($deptId <= 0) {
                admin_api_error_response($isAjax, 'major.php', '请选择院系');
            }
            $stmt = $pdo->prepare('SELECT dept_id FROM department WHERE dept_id = ? LIMIT 1');
            $stmt->execute([$deptId]);
            if (!$stmt->fetch()) {
                admin_api_error_response($isAjax, 'major.php', '所选院系不存在');
            }
        }

        $uniqueParts = [];
        $uniqueParams = [];
        if ($majorColumns['has_code']) {
            $uniqueParts[] = 'major_code = ?';
            $uniqueParams[] = $majorCode;
        }
        if ($majorColumns['has_name']) {
            $uniqueParts[] = 'major_name = ?';
            $uniqueParams[] = $majorName;
        }

        if ($uniqueParts !== []) {
            $stmt = $pdo->prepare('SELECT major_id FROM major WHERE ' . implode(' OR ', $uniqueParts) . ' LIMIT 1');
            $stmt->execute($uniqueParams);
            if ($stmt->fetch()) {
                admin_api_error_response($isAjax, 'major.php', '专业代码或名称已存在');
            }
        }

        try {
            $fields = [];
            $placeholders = [];
            $values = [];

            if ($majorColumns['has_code']) {
                $fields[] = 'major_code';
                $placeholders[] = '?';
                $values[] = $majorCode;
            }
            if ($majorColumns['has_name']) {
                $fields[] = 'major_name';
                $placeholders[] = '?';
                $values[] = $majorName;
            }
            if ($majorColumns['has_dept']) {
                $fields[] = 'dept_id';
                $placeholders[] = '?';
                $values[] = $deptId;
            }

            if ($fields !== []) {
                $sql = 'INSERT INTO major (' . implode(',', $fields) . ') VALUES (' . implode(',', $placeholders) . ')';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($values);

                if (function_exists('sys_log')) {
                    $newMajorId = (int) $pdo->lastInsertId();
                    $displayValue = $majorName ?: ($majorCode ?: ('ID ' . $newMajorId));
                    $desc = '新增专业: ' . $displayValue . ' (ID: ' . $newMajorId . ')';
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'major', $newMajorId);
                }
            }
        } catch (PDOException $e) {
            admin_api_error_response($isAjax, 'major.php', '添加失败', 500);
        }

        admin_api_success_response($isAjax, 'major.php', '添加成功');
        break;

    case 'update_major':
        $id = (int) ($_POST['major_id'] ?? 0);
        $majorCode = admin_api_normalize_major_code($_POST['major_code'] ?? '');
        $majorName = trim($_POST['major_name'] ?? '');
        $deptId = (int) ($_POST['dept_id'] ?? 0);
        $majorColumns = admin_api_major_columns($pdo);

        if ($majorColumns['has_code']) {
            $msg = admin_api_validate_major_code($majorCode);
            if ($msg !== null) {
                admin_api_error_response($isAjax, 'major.php', $msg);
            }
        }

        $uniqueParts = [];
        $uniqueParams = [];
        if ($majorColumns['has_code']) {
            $uniqueParts[] = 'major_code = ?';
            $uniqueParams[] = $majorCode;
        }
        if ($majorColumns['has_name']) {
            $uniqueParts[] = 'major_name = ?';
            $uniqueParams[] = $majorName;
        }

        if ($uniqueParts !== []) {
            $sql = 'SELECT major_id FROM major WHERE (' . implode(' OR ', $uniqueParts) . ') AND major_id != ? LIMIT 1';
            $checkParams = $uniqueParams;
            $checkParams[] = $id;
            $stmt = $pdo->prepare($sql);
            $stmt->execute($checkParams);
            if ($stmt->fetch()) {
                admin_api_error_response($isAjax, 'major.php', '专业代码或名称冲突');
            }
        }

        $setParts = [];
        $setParams = [];
        if ($majorColumns['has_code']) {
            $setParts[] = 'major_code = ?';
            $setParams[] = $majorCode;
        }
        if ($majorColumns['has_name']) {
            $setParts[] = 'major_name = ?';
            $setParams[] = $majorName;
        }
        if ($majorColumns['has_dept']) {
            $setParts[] = 'dept_id = ?';
            $setParams[] = $deptId;
        }

        if ($setParts !== []) {
            $sql = 'UPDATE major SET ' . implode(', ', $setParts) . ' WHERE major_id = ?';
            $setParams[] = $id;
            $pdo->prepare($sql)->execute($setParams);

            if (function_exists('sys_log')) {
                $displayValue = $majorName ?: ('ID ' . $id);
                $desc = '修改专业: ' . $displayValue . ' (ID: ' . $id . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'major', $id);
            }
        }

        admin_api_success_response($isAjax, 'major.php', '更新成功');
        break;
}

return true;
