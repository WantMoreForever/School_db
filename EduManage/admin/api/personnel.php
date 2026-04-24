<?php
/**
 * admin/api/personnel.php
 * 管理后台人员接口：处理学生、教师、用户状态、密码重置和管理员个人资料维护。
 */

if (!in_array($act, [
    'add_student',
    'del_student',
    'add_teacher',
    'del_teacher',
    'toggle_status',
    'reset_password',
    'update_student',
    'update_teacher',
    'update_self',
], true)) {
    return false;
}

switch ($act) {
    case 'add_student':
        $name = trim($_POST['name'] ?? '');
        $email = admin_api_normalize_email($_POST['email'] ?? '');
        $pwd = $_POST['pwd'] ?? '';
        $studentNo = trim($_POST['student_no'] ?? '');
        $deptId = $_POST['dept_id'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');

        if ($deptId === '') {
            $deptId = null;
        }
        if ($msg = admin_api_validate_school_email($email)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }
        if ($msg = admin_api_validate_phone($phone)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }
        if ($msg = admin_api_validate_student_no($studentNo)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }

        $stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'student.php', '此邮箱已被占用，请使用其他邮箱');
        }

        if ($phone !== '') {
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                admin_api_error_response($isAjax, 'student.php', '该手机号已被占用，请检查后重试');
            }
        }

        $stmt = $pdo->prepare('SELECT user_id FROM student WHERE student_no = ? LIMIT 1');
        $stmt->execute([$studentNo]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'student.php', '该学号已被占用，请检查后重试');
        }

        $avatarResult = admin_api_handle_avatar_upload();
        if (is_array($avatarResult) && isset($avatarResult['error'])) {
            admin_api_error_response($isAjax, 'student.php', $avatarResult['error']);
        }
        $avatarFile = is_string($avatarResult) ? $avatarResult : null;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO user (name, email, password, status, gender, phone, image) VALUES (?, ?, ?, 'active', ?, ?, ?)");
            $stmt->execute([
                $name,
                $email,
                password_hash($pwd, PASSWORD_DEFAULT),
                $gender ?: null,
                $phone ?: null,
                $avatarFile,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO student (user_id, student_no, dept_id) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $studentNo, $deptId]);
            $pdo->commit();

            if (function_exists('sys_log')) {
                $desc = '新增学生: ' . ($name ?: $email) . ' (ID: ' . $userId . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'student', $userId);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_api_error_response($isAjax, 'student.php', '创建失败，请稍后重试或联系管理员', 500);
        }

        admin_api_success_response($isAjax, 'student.php', '创建成功');
        break;

    case 'del_student':
        $id = (int) ($_GET['id'] ?? 0);
        $pdo->prepare('DELETE FROM student WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM user WHERE user_id = ?')->execute([$id]);

        if (function_exists('sys_log')) {
            sys_log($pdo, $_SESSION['user_id'] ?? null, '删除学生: (ID: ' . $id . ')', 'student', $id);
        }

        admin_api_redirect('student.php');

    case 'add_teacher':
        $name = trim($_POST['name'] ?? '');
        $email = admin_api_normalize_email($_POST['email'] ?? '');
        $pwd = $_POST['pwd'] ?? '';
        $title = $_POST['title'] ?? '';
        $deptId = $_POST['dept_id'] ?? null;
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');

        if ($deptId === '') {
            $deptId = null;
        }
        if ($msg = admin_api_validate_school_email($email)) {
            admin_api_error_response($isAjax, 'teacher.php', $msg);
        }
        if ($msg = admin_api_validate_phone($phone)) {
            admin_api_error_response($isAjax, 'teacher.php', $msg);
        }

        $stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            admin_api_error_response($isAjax, 'teacher.php', '此邮箱已被占用，请使用其他邮箱');
        }

        if ($phone !== '') {
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                admin_api_error_response($isAjax, 'teacher.php', '该手机号已被占用，请检查后重试');
            }
        }

        $avatarResult = admin_api_handle_avatar_upload();
        if (is_array($avatarResult) && isset($avatarResult['error'])) {
            admin_api_error_response($isAjax, 'teacher.php', $avatarResult['error']);
        }
        $avatarFile = is_string($avatarResult) ? $avatarResult : null;

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO user (name, email, password, status, gender, phone, image) VALUES (?, ?, ?, 'active', ?, ?, ?)");
            $stmt->execute([
                $name,
                $email,
                password_hash($pwd, PASSWORD_DEFAULT),
                $gender ?: null,
                $phone ?: null,
                $avatarFile,
            ]);
            $userId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare('INSERT INTO teacher (user_id, title, dept_id) VALUES (?, ?, ?)');
            $stmt->execute([$userId, $title, $deptId]);
            $pdo->commit();

            if (function_exists('sys_log')) {
                $desc = '新增教师: ' . ($name ?: $email) . ' (ID: ' . $userId . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'teacher', $userId);
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_api_error_response($isAjax, 'teacher.php', '创建失败，请稍后重试或联系管理员', 500);
        }

        admin_api_success_response($isAjax, 'teacher.php', '创建成功');
        break;

    case 'del_teacher':
        $id = (int) ($_GET['id'] ?? 0);
        $pdo->prepare('DELETE FROM teacher WHERE user_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM user WHERE user_id = ?')->execute([$id]);

        if (function_exists('sys_log')) {
            sys_log($pdo, $_SESSION['user_id'] ?? null, '删除教师: (ID: ' . $id . ')', 'teacher', $id);
        }

        admin_api_redirect('teacher.php');

    case 'toggle_status':
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT status, name, email FROM user WHERE user_id = ?');
            $stmt->execute([$id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $newStatus = ($user['status'] === 'active') ? 'inactive' : 'active';
                $stmt = $pdo->prepare('UPDATE user SET status = ? WHERE user_id = ?');
                $stmt->execute([$newStatus, $id]);

                if (function_exists('sys_log')) {
                    $display = $user['name'] ?? $user['email'] ?? ('ID ' . $id);
                    $actionName = ($newStatus === 'inactive') ? '禁用用户' : '启用用户';
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $actionName . ': ' . $display . ' (ID: ' . $id . ')', 'user', $id);
                }
            }
        }

        $redirect = admin_api_clean_redirect((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php'));
        admin_api_redirect($redirect);

    case 'reset_password':
        $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
        if ($id <= 0) {
            $redirect = admin_api_clean_redirect((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php'));
            if ($isAjax) {
                admin_api_json_response(false, ['error' => '缺少用户 ID', 'message' => '缺少用户 ID'], 400);
            }
            admin_api_redirect($redirect, ['error' => '缺少用户 ID']);
        }

        try {
            $tmpPwd = '123456';
            $stmt = $pdo->prepare('UPDATE user SET password = ? WHERE user_id = ?');
            $stmt->execute([password_hash($tmpPwd, PASSWORD_DEFAULT), $id]);

            if (function_exists('sys_log')) {
                sys_log($pdo, $_SESSION['user_id'] ?? null, '重置用户密码: (ID: ' . $id . ')', 'user', $id);
            }
        } catch (PDOException $e) {
            $redirect = admin_api_clean_redirect((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php'));
            if ($isAjax) {
                admin_api_json_response(false, ['error' => '重置密码失败', 'message' => '重置密码失败'], 500);
            }
            admin_api_redirect($redirect, ['error' => '重置密码失败']);
        }

        $message = '密码已重置为：' . $tmpPwd;
        if ($isAjax) {
            admin_api_json_response(true, ['message' => $message, 'temp_password' => $tmpPwd]);
        }

        $redirect = admin_api_clean_redirect((string) ($_POST['redirect'] ?? $_GET['redirect'] ?? 'index.php'));
        admin_api_redirect($redirect, ['success' => $message]);

    case 'update_student':
        $id = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = admin_api_normalize_email($_POST['email'] ?? '');
        $statusPresent = array_key_exists('status', $_POST);
        $status = $statusPresent ? $_POST['status'] : null;
        $studentNo = trim($_POST['student_no'] ?? '');
        $deptId = $_POST['dept_id'] ?? null;
        $pwd = $_POST['pwd'] ?? '';
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');

        if ($deptId === '') {
            $deptId = null;
        }
        if ($msg = admin_api_validate_school_email($email)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }
        if ($msg = admin_api_validate_phone($phone)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }
        if ($msg = admin_api_validate_student_no($studentNo)) {
            admin_api_error_response($isAjax, 'student.php', $msg);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) $row['user_id'] !== $id) {
                admin_api_error_response($isAjax, 'student.php', '此邮箱已被其他账户使用，请使用其它邮箱或联系管理员');
            }

            if ($phone !== '') {
                $stmt = $pdo->prepare('SELECT user_id FROM user WHERE phone = ? LIMIT 1');
                $stmt->execute([$phone]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && (int) $row['user_id'] !== $id) {
                    admin_api_error_response($isAjax, 'student.php', '该手机号已被其他账户使用，请使用其它手机号或联系管理员');
                }
            }

            $stmt = $pdo->prepare('SELECT user_id FROM student WHERE student_no = ? LIMIT 1');
            $stmt->execute([$studentNo]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) $row['user_id'] !== $id) {
                admin_api_error_response($isAjax, 'student.php', '该学号已被其他学生使用，请核对后重试');
            }

            $avatarResult = admin_api_handle_avatar_upload();
            if (is_array($avatarResult) && isset($avatarResult['error'])) {
                admin_api_error_response($isAjax, 'student.php', $avatarResult['error']);
            }
            $avatarFile = is_string($avatarResult) ? $avatarResult : null;

            try {
                $pdo->beginTransaction();

                $oldAvatar = null;
                if ($avatarFile) {
                    $oldStmt = $pdo->prepare('SELECT image FROM user WHERE user_id = ?');
                    $oldStmt->execute([$id]);
                    $oldAvatar = $oldStmt->fetchColumn();
                }

                $sets = ['name = ?', 'email = ?'];
                $params = [$name, $email];
                if ($statusPresent) {
                    $sets[] = 'status = ?';
                    $params[] = $status;
                }
                if ($pwd !== '') {
                    $sets[] = 'password = ?';
                    $params[] = password_hash($pwd, PASSWORD_DEFAULT);
                }
                $sets[] = 'gender = ?';
                $params[] = $gender ?: null;
                $sets[] = 'phone = ?';
                $params[] = $phone ?: null;
                if ($avatarFile) {
                    $sets[] = 'image = ?';
                    $params[] = $avatarFile;
                }

                $params[] = $id;
                $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
                $pdo->prepare($sql)->execute($params);

                if ($avatarFile && $oldAvatar) {
                    admin_api_delete_avatar_file((string) $oldAvatar);
                }

                $stmt = $pdo->prepare('UPDATE student SET student_no = ?, dept_id = ? WHERE user_id = ?');
                $stmt->execute([$studentNo, $deptId, $id]);
                $pdo->commit();

                if (function_exists('sys_log')) {
                    $desc = '编辑学生信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user,student', $id);
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                admin_api_error_response($isAjax, 'student.php', '更新失败，请稍后重试或联系管理员', 500);
            }
        }

        admin_api_success_response($isAjax, 'student.php', '更新成功');
        break;

    case 'update_teacher':
        $id = (int) ($_POST['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = admin_api_normalize_email($_POST['email'] ?? '');
        $statusPresent = array_key_exists('status', $_POST);
        $status = $statusPresent ? $_POST['status'] : null;
        $title = $_POST['title'] ?? '';
        $deptId = $_POST['dept_id'] ?? null;
        $pwd = $_POST['pwd'] ?? '';
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');

        if ($deptId === '') {
            $deptId = null;
        }
        if ($msg = admin_api_validate_school_email($email)) {
            admin_api_error_response($isAjax, 'teacher.php', $msg);
        }
        if ($msg = admin_api_validate_phone($phone)) {
            admin_api_error_response($isAjax, 'teacher.php', $msg);
        }

        if ($id > 0) {
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) $row['user_id'] !== $id) {
                admin_api_error_response($isAjax, 'teacher.php', '此邮箱已被其他账户使用，请使用其它邮箱或联系管理员');
            }

            if ($phone !== '') {
                $stmt = $pdo->prepare('SELECT user_id FROM user WHERE phone = ? LIMIT 1');
                $stmt->execute([$phone]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row && (int) $row['user_id'] !== $id) {
                    admin_api_error_response($isAjax, 'teacher.php', '该手机号已被其他账户使用，请使用其它手机号或联系管理员');
                }
            }

            $avatarResult = admin_api_handle_avatar_upload();
            if (is_array($avatarResult) && isset($avatarResult['error'])) {
                admin_api_error_response($isAjax, 'teacher.php', $avatarResult['error']);
            }
            $avatarFile = is_string($avatarResult) ? $avatarResult : null;

            try {
                $pdo->beginTransaction();

                $oldAvatar = null;
                if ($avatarFile) {
                    $oldStmt = $pdo->prepare('SELECT image FROM user WHERE user_id = ?');
                    $oldStmt->execute([$id]);
                    $oldAvatar = $oldStmt->fetchColumn();
                }

                $sets = ['name = ?', 'email = ?'];
                $params = [$name, $email];
                if ($statusPresent) {
                    $sets[] = 'status = ?';
                    $params[] = $status;
                }
                if ($pwd !== '') {
                    $sets[] = 'password = ?';
                    $params[] = password_hash($pwd, PASSWORD_DEFAULT);
                }
                $sets[] = 'gender = ?';
                $params[] = $gender ?: null;
                $sets[] = 'phone = ?';
                $params[] = $phone ?: null;
                if ($avatarFile) {
                    $sets[] = 'image = ?';
                    $params[] = $avatarFile;
                }

                $params[] = $id;
                $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
                $pdo->prepare($sql)->execute($params);

                if ($avatarFile && $oldAvatar) {
                    admin_api_delete_avatar_file((string) $oldAvatar);
                }

                $stmt = $pdo->prepare('UPDATE teacher SET title = ?, dept_id = ? WHERE user_id = ?');
                $stmt->execute([$title, $deptId, $id]);
                $pdo->commit();

                if (function_exists('sys_log')) {
                    $desc = '编辑教师信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
                    sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user,teacher', $id);
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                admin_api_error_response($isAjax, 'teacher.php', '更新失败，请稍后重试或联系管理员', 500);
            }
        }

        admin_api_success_response($isAjax, 'teacher.php', '更新成功');
        break;

    case 'update_self':
        $id = (int) ($_SESSION['user_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $pwd = $_POST['pwd'] ?? '';
        $gender = $_POST['gender'] ?? null;
        $phone = trim($_POST['phone'] ?? '');
        $profileRedirect = app_catalog_url('admin', 'pages', 'profile');

        if ($msg = admin_api_validate_phone($phone)) {
            admin_api_error_response($isAjax, $profileRedirect, $msg);
        }

        if ($phone !== '') {
            $stmt = $pdo->prepare('SELECT user_id FROM user WHERE phone = ? LIMIT 1');
            $stmt->execute([$phone]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && (int) $row['user_id'] !== $id) {
                admin_api_error_response($isAjax, $profileRedirect, '该手机号已被其他账户使用，请使用其它手机号或联系管理员');
            }
        }

        $avatarResult = admin_api_handle_avatar_upload();
        if (is_array($avatarResult) && isset($avatarResult['error'])) {
            admin_api_error_response($isAjax, $profileRedirect, $avatarResult['error']);
        }
        $avatarFile = is_string($avatarResult) ? $avatarResult : null;

        try {
            $pdo->beginTransaction();

            $oldAvatar = null;
            if ($avatarFile) {
                $oldStmt = $pdo->prepare('SELECT image FROM user WHERE user_id = ?');
                $oldStmt->execute([$id]);
                $oldAvatar = $oldStmt->fetchColumn();
            }

            $sets = ['name = ?'];
            $params = [$name];
            if ($pwd !== '') {
                $sets[] = 'password = ?';
                $params[] = password_hash($pwd, PASSWORD_DEFAULT);
            }
            $sets[] = 'gender = ?';
            $params[] = $gender ?: null;
            $sets[] = 'phone = ?';
            $params[] = $phone ?: null;
            if ($avatarFile) {
                $sets[] = 'image = ?';
                $params[] = $avatarFile;
            }

            $params[] = $id;
            $sql = 'UPDATE user SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
            $pdo->prepare($sql)->execute($params);

            if ($avatarFile && $oldAvatar) {
                admin_api_delete_avatar_file((string) $oldAvatar);
            }

            $pdo->commit();

            if (function_exists('sys_log')) {
                $desc = '编辑管理员信息: ' . ($name ?: 'ID ' . $id) . ' (ID ' . $id . ')';
                sys_log($pdo, $_SESSION['user_id'] ?? null, $desc, 'user', $id);
            }

            $_SESSION['user_name'] = $name;
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            admin_api_error_response($isAjax, $profileRedirect, '更新失败，请稍后重试或联系管理员', 500);
        }

        if ($isAjax) {
            admin_api_json_response(true, ['message' => '更新成功']);
        }
        admin_api_redirect($profileRedirect, ['success' => '1']);
}

return true;
