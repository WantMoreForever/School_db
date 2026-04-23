<?php
// Centralized configuration for student area
// This file can be included (returns array) or requested directly over HTTP (returns JSON).

require_once __DIR__ . '/../../components/bootstrap.php';

if (!function_exists('student_term_default_semester')) {
    function student_term_default_semester(): string
    {
        return app_default_current_semester();
    }
}

if (!function_exists('student_term_label')) {
    function student_term_label(string $semester, bool $withSuffix = true): string
    {
        return app_semester_label($semester, $withSuffix, $semester);
    }
}

if (!function_exists('student_term_year_label')) {
    function student_term_year_label(int $year, string $semester, bool $withSuffix = true): string
    {
        return app_semester_year_label($year, $semester, $withSuffix);
    }
}

function stu_cfg_set_by_dot_key(array &$target, $dotKey, $value): void {
    if (!is_string($dotKey) || $dotKey === '') {
        return;
    }

    $parts = explode('.', $dotKey);
    $ref = &$target;

    foreach ($parts as $idx => $part) {
        if ($part === '') {
            return;
        }

        $isLast = ($idx === count($parts) - 1);
        if ($isLast) {
            $ref[$part] = $value;
            return;
        }

        if (!isset($ref[$part]) || !is_array($ref[$part])) {
            $ref[$part] = [];
        }
        $ref = &$ref[$part];
    }
}

function stu_cfg_decode_json_value($raw) {
    if (!is_string($raw)) {
        return $raw;
    }

    $decoded = json_decode($raw, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        return $decoded;
    }

    return $raw;
}

$cfg = [
    // current academic term (defaults to current year/semester)
    'term' => [
        'current_year' => (int)date('Y'),
        'current_semester' => student_term_default_semester(),
        'current_semester_cn' => student_term_label(student_term_default_semester(), true),
    ],

    // general contact information used in student-facing pages
    'office_phone' => '12345678',

    // avatar / upload settings
    'avatar' => [
        'max_size' => (int) app_config('upload.avatar.max_size', 0),
        'allowed_mimes' => app_config('upload.avatar.allowed_mimes', []),
        'upload_dir' => app_avatar_url(),
        'default_avatar' => app_default_avatar_url(),
    ],

    // schedule / timetable UI defaults
    'schedule' => [
        'grid_start_h' => 8,
        'grid_end_h' => 22,
        'row_px' => 72,
        'mins_per_row' => 60,
        'total_weeks' => 16,
    ],

    // UI / visualization helpers
    'gpa' => [
        'arc_radius' => 50,
    ],
];

// Optional DB override: load config entries from table `config`.
// If DB is unavailable or table does not exist, defaults above remain effective.
try {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../../components/db.php';
    }

    $pdo = app_db();
    if ($pdo !== null) {
        $stmt = $pdo->query('SELECT config_key, config_value FROM config ORDER BY config_key');
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        foreach ($rows as $row) {
            $key = $row['config_key'] ?? '';
            if (!is_string($key) || $key === '') {
                continue;
            }

            // 【固定】头像上传相关的配置，不允许被数据库中的设置覆盖
            if (strpos($key, 'avatar.') === 0 || $key === 'avatar') {
                continue;
            }

            $value = stu_cfg_decode_json_value($row['config_value'] ?? null);
            stu_cfg_set_by_dot_key($cfg, $key, $value);
        }
    }
} catch (Throwable $e) {
    // Keep silent for clients; fallback defaults are still usable.
    error_log('student/api/config.php db override failed: ' . $e->getMessage());
}

$term = $cfg['term'] ?? [];
if (isset($term['current_semester']) && !isset($term['current_semester_cn'])) {
    $cfg['term']['current_semester_cn'] = student_term_label((string) $term['current_semester'], true);
}

// Calculate the start date of the current semester based on the DB settings
if (($cfg['term']['current_semester'] ?? '') === 'Spring' && isset($term['spring_start_date'])) {
    $cfg['term']['start_date'] = $term['spring_start_date'];
} elseif (($cfg['term']['current_semester'] ?? '') === 'Fall' && isset($term['fall_start_date'])) {
    $cfg['term']['start_date'] = $term['fall_start_date'];
} else {
    // Fallback: Assume semester starts on the current week's Monday if not set
    $cfg['term']['start_date'] = date('Y-m-d', strtotime('monday this week'));
}

// If requested directly (not included), output JSON and exit.
if (php_sapi_name() !== 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'])) {
    require_once __DIR__ . '/helpers.php';
    student_api_bootstrap();
    student_api_json_ok($cfg);
}

// When included, return the config array.
return $cfg;
