<?php
/**
 * Teacher Workload Report API
 * Calls stored procedures defined in workload_procedures.sql
 */
require_once __DIR__ . '/../../components/bootstrap.php';

app_start_session();
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit; }

require_once __DIR__ . '/config.php';
$pdo        = get_pdo();
$TEACHER_ID = require_teacher_auth($pdo);
$action     = $_GET['action'] ?? ($_POST['action'] ?? '');

try {
    match ($action) {
        'get_semesters'  => action_get_semesters($pdo, $TEACHER_ID),
        'get_summary'    => action_get_summary($pdo, $TEACHER_ID),
        'get_by_section' => action_get_by_section($pdo, $TEACHER_ID),
        default          => json_err("Unknown action: $action"),
    };
} catch (PDOException $e) {
    teacher_handle_exception($e, 'teacher.api.workload');
} catch (Throwable $e) {
    teacher_handle_exception($e, 'teacher.api.workload');
}

// ═══════════════════════════════════════════════════════════
// HANDLERS
// ═══════════════════════════════════════════════════════════

/**
 * GET ?action=get_semesters
 * Returns distinct year/semester pairs for this teacher's sections.
 */
function action_get_semesters(PDO $pdo, int $tid): void {
    $stmt = $pdo->prepare('CALL sp_get_teacher_semesters(?)');
    $stmt->execute([$tid]);
    json_ok($stmt->fetchAll());
}

/**
 * GET ?action=get_summary[&semester=Fall&year=2025]
 * Returns aggregate workload stats (one row).
 */
function action_get_summary(PDO $pdo, int $tid): void {
    $semester = trim($_GET['semester'] ?? '');
    $year     = (int)($_GET['year'] ?? 0);

    $stmt = $pdo->prepare('CALL sp_get_workload_summary(?, ?, ?)');
    $stmt->execute([$tid, $semester, $year]);
    $row = $stmt->fetch();
    json_ok($row ?: (object)[]);
}

/**
 * GET ?action=get_by_section[&semester=Fall&year=2025]
 * Returns per-section workload breakdown.
 */
function action_get_by_section(PDO $pdo, int $tid): void {
    $semester = trim($_GET['semester'] ?? '');
    $year     = (int)($_GET['year'] ?? 0);

    $stmt = $pdo->prepare('CALL sp_get_workload_by_section(?, ?, ?)');
    $stmt->execute([$tid, $semester, $year]);
    json_ok($stmt->fetchAll());
}
