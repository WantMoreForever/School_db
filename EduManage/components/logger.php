<?php
// components/logger.php

require_once __DIR__ . '/../config/app_config.php';

function sys_log_build(string $action, array $context = []): string
{
    $parts = [];
    foreach ($context as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        } elseif (is_array($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $parts[] = $key . '=' . (string) $value;
    }

    return $parts === [] ? $action : $action . ' | ' . implode(', ', $parts);
}

function sys_log($pdo, $user_id, $action, $target_table = null, $target_id = null, $opts = [])
{
    if (!$pdo) {
        return;
    }

    $action_str = substr((string) $action, 0, 255);
    $target_table_str = $target_table !== null ? substr((string) $target_table, 0, 50) : null;
    $target_id_val = $target_id !== null ? (int) $target_id : null;

    if (session_status() !== PHP_SESSION_ACTIVE) {
        @session_start();
    }

    $current_session_id = null;
    if (!empty($_SESSION['user_id'])) {
        $current_session_id = $_SESSION['user_id'];
    } elseif (!empty($_SESSION['teacher_id'])) {
        $current_session_id = $_SESSION['teacher_id'];
    } elseif (!empty($_SESSION['student_id'])) {
        $current_session_id = $_SESSION['student_id'];
    }

    $has_explicit_numeric_user_id = is_int($user_id) || (is_string($user_id) && ctype_digit($user_id));
    if ($current_session_id !== null && !$has_explicit_numeric_user_id) {
        $user_id = $current_session_id;
    }

    $user_id_val = null;
    if ($user_id !== null) {
        if (is_int($user_id) || (is_string($user_id) && ctype_digit($user_id))) {
            $user_id_val = (int) $user_id;
        } else {
            $suffix = ' [' . substr((string) $user_id, 0, 80) . ']';
            $action_str = substr($action_str . $suffix, 0, 255);
        }
    }

    $ensureUtf8 = function ($s) {
        if ($s === null) {
            return null;
        }
        if (function_exists('mb_check_encoding')) {
            if (mb_check_encoding($s, 'UTF-8')) {
                return $s;
            }
            $conv = @mb_convert_encoding($s, 'UTF-8', 'GBK,GB2312,ISO-8859-1');
            if ($conv && mb_check_encoding($conv, 'UTF-8')) {
                return $conv;
            }
            return $s;
        }
        if (function_exists('iconv')) {
            $conv = @iconv('GBK', 'UTF-8//IGNORE', $s);
            return $conv ?: $s;
        }
        return $s;
    };

    $action_for_check = (string) $ensureUtf8($action_str) ?: (string) $action_str;
    $important_keywords = app_config('app.logging.important_keywords', []);
    if (!is_array($important_keywords)) {
        $important_keywords = [];
    }
    $ignore_regex = (string) app_config('app.logging.ignore_regex', '');

    $should_log = false;
    if (!empty($opts['force'])) {
        $should_log = true;
    } else {
        foreach ($important_keywords as $kw) {
            if (mb_stripos($action_for_check, $kw, 0, 'UTF-8') !== false) {
                $should_log = true;
                break;
            }
        }
        if (!$should_log) {
            if ($ignore_regex !== '' && preg_match($ignore_regex, $action_for_check)) {
                return;
            }
            $should_log = true;
        }
    }

    if (!$should_log) {
        return;
    }

    $action_str = $ensureUtf8($action_str);
    $target_table_str = $ensureUtf8($target_table_str);

    try {
        $stmt = $pdo->prepare('INSERT INTO system_log (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user_id_val, $action_str, $target_table_str, $target_id_val]);

        $retentionDays = max(1, (int) app_config('app.logging.retention_days', 30));
        if (rand(1, 100) <= 5) {
            $pdo->exec('DELETE FROM system_log WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)');
        }
    } catch (Throwable $e) {
        if ($user_id_val !== null) {
            try {
                $fallbackAction = substr($action_str . ' [operator_id:' . $user_id_val . ']', 0, 255);
                $stmt = $pdo->prepare('INSERT INTO system_log (user_id, action, target_table, target_id) VALUES (?, ?, ?, ?)');
                $stmt->execute([null, $fallbackAction, $target_table_str, $target_id_val]);
                return;
            } catch (Throwable $inner) {
                error_log('system_log fallback failed: ' . $inner->getMessage());
            }
        }

        error_log('system_log failed: ' . $e->getMessage());
    }
}
