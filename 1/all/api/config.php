<?php
/**
 * Database Configuration
 * school_db — Teacher Portal
 */
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'school_db');
define('DB_USER', 'root');       // ← change to your MySQL user
define('DB_PASS', 'yaoxicheng');           // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Avatar upload settings
define('AVATAR_DIR',     __DIR__ . '/../uploads/');
define('AVATAR_URL',     '../uploads/');
define('AVATAR_MAX_SIZE', 2 * 1024 * 1024); // 2 MB
define('AVATAR_ALLOWED', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

function get_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/**
 * 获取教师ID（优先级：URL参数 > 教师session > JiaoWu登录session > 默认）
 * JiaoWu登录后设置 $_SESSION['user_id']，本函数自动识别并验证其教师身份
 */
function get_teacher_id(PDO $pdo): ?int {
    // 1. 调试参数：teacher_name
    if (!empty($_REQUEST['teacher_name'])) {
        $name = trim($_REQUEST['teacher_name']);
        $stmt = $pdo->prepare('SELECT u.user_id FROM user u JOIN teacher t ON u.user_id = t.user_id WHERE u.name = ? LIMIT 1');
        $stmt->execute([$name]);
        $row = $stmt->fetch();
        if ($row) {
            $_SESSION['teacher_id'] = $row['user_id'];
            return (int)$row['user_id'];
        }
    }

    // 2. 调试参数：teacher_id
    if (!empty($_REQUEST['teacher_id'])) {
        $id = (int)$_REQUEST['teacher_id'];
        $_SESSION['teacher_id'] = $id;
        return $id;
    }

    // 3. 教师门户直接session
    if (!empty($_SESSION['teacher_id'])) {
        return (int)$_SESSION['teacher_id'];
    }

    // 4. JiaoWu 登录系统写入的 user_id，验证其教师身份
    if (!empty($_SESSION['user_id'])) {
        $uid  = (int)$_SESSION['user_id'];
        $stmt = $pdo->prepare('SELECT user_id FROM teacher WHERE user_id = ? LIMIT 1');
        $stmt->execute([$uid]);
        if ($stmt->fetch()) {
            $_SESSION['teacher_id'] = $uid;
            return $uid;
        }
    }

    // 5. 默认测试账号（user_id=100）
    $_SESSION['teacher_id'] = 100;
    return 100;
}

/**
 * 要求教师已登录，否则返回401。
 * 当 JiaoWu session 或 teacher session 均无效时触发。
 */
function require_teacher_auth(PDO $pdo): int {
    // 若两个session均为空，视为未登录
    if (empty($_SESSION['teacher_id']) && empty($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => '未登录，请先通过教务系统登录', 'redirect' => '../JiaoWu/login.php']);
        exit;
    }
    return get_teacher_id($pdo);
}

function json_ok(mixed $data): never {
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_err(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

function json_rows(PDOStatement $stmt): array {
    return $stmt->fetchAll();
}

