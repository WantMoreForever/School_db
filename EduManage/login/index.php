<?php
require_once __DIR__ . '/../components/bootstrap.php';
require_once __DIR__ . '/../components/db.php';

app_start_session();
$pdo = app_db();

if (!empty($_SESSION['user_id']) && $pdo !== null) {
    $role = app_find_user_role($pdo, (int) $_SESSION['user_id']);
    if ($role !== null) {
        app_redirect_by_role($role);
    }
}

header('Location: ' . app_login_url());
exit;
