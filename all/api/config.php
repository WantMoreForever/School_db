<?php
/**
 * Database Configuration
 * school_db — Teacher Portal
 */
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'school_db');
define('DB_USER', 'root');       // ← change to your MySQL user
define('DB_PASS', 'Conan4869+LAN');           // ← change to your MySQL password
define('DB_CHARSET', 'utf8mb4');

// Avatar upload settings
define('AVATAR_DIR',     __DIR__ . '/../uploads/avatars/');
define('AVATAR_URL',     '../uploads/avatars/');
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
 * Get teacher ID from session or parameters
 * Support flexible login for testing
 * Priority: teacher_name parameter > teacher_id parameter > session > default
 */
function get_teacher_id(PDO $pdo): ?int {
    // 1. Check for teacher_name parameter (for flexible login)
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

    // 2. Check for teacher_id parameter
    if (!empty($_REQUEST['teacher_id'])) {
        $id = (int)$_REQUEST['teacher_id'];
        $_SESSION['teacher_id'] = $id;
        return $id;
    }

    // 3. Check session
    if (!empty($_SESSION['teacher_id'])) {
        return (int)$_SESSION['teacher_id'];
    }

    // 4. Default to Youuy (user_id = 100) for testing
    $_SESSION['teacher_id'] = 100;
    return 100;
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

