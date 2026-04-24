<?php
// components/db.php

require_once __DIR__ . '/../config/app_config.php';

$database_config = app_config('database', []);

$db_host = (string) ($database_config['host'] ?? '');
$db_port = (int) ($database_config['port'] ?? 0);
$db_user = (string) ($database_config['username'] ?? '');
$db_pass = (string) ($database_config['password'] ?? '');
$db_name = (string) ($database_config['database'] ?? '');
$db_charset = (string) ($database_config['charset'] ?? '');

$pdo = null;
$db_error = null;

try {
    if ($db_host === '' || $db_port <= 0 || $db_user === '' || $db_name === '' || $db_charset === '') {
        throw new RuntimeException('Database configuration is incomplete.');
    }

    $pdo = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset={$db_charset}",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    $db_error = $e->getMessage();
}

if (!function_exists('app_db')) {
    function app_db(): ?PDO
    {
        global $pdo;

        return $pdo instanceof PDO ? $pdo : null;
    }
}

if (!function_exists('app_require_pdo')) {
    function app_require_pdo(): PDO
    {
        $pdo = app_db();
        if ($pdo !== null) {
            return $pdo;
        }

        $dbError = $GLOBALS['db_error'] ?? null;
        if (is_string($dbError) && $dbError !== '') {
            throw new RuntimeException($dbError);
        }

        throw new RuntimeException('Database connection is not available.');
    }
}

if (!function_exists('app_call_rows')) {
    function app_call_rows(PDO $pdo, string $procedure, array $params = []): array
    {
        $sets = app_call_multi_result_rows($pdo, $procedure, $params);

        return $sets[0] ?? [];
    }
}

if (!function_exists('app_call_multi_result_rows')) {
    function app_call_multi_result_rows(PDO $pdo, string $procedure, array $params = []): array
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $procedure)) {
            throw new InvalidArgumentException('Invalid procedure name.');
        }

        $placeholders = implode(', ', array_fill(0, count($params), '?'));
        $stmt = $pdo->prepare('CALL `' . $procedure . '`(' . $placeholders . ')');
        $stmt->execute($params);

        $sets = [];
        try {
            do {
                $sets[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } while ($stmt->nextRowset());
        } finally {
            $stmt->closeCursor();
        }

        return $sets;
    }
}
