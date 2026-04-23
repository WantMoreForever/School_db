<?php

declare(strict_types=1);

/**
 * admin/api/import_common.php
 * 批量导入公共工具：提供 Excel/CSV 解析、字段映射和导入校验辅助函数。
 */

require_once __DIR__ . '/../common.php';
require_once __DIR__ . '/../../components/logger.php';

function admin_import_json_response(bool $ok, array $payload = [], int $status = 200): void
{
    http_response_code($status);
    app_send_json_header();

    echo json_encode(
        admin_import_json_envelope($ok, $payload, $status),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function admin_import_json_envelope(bool $ok, array $payload = [], int $status = 200): array
{
    $message = (string) ($payload['message'] ?? $payload['msg'] ?? ($ok ? '操作成功' : ($payload['error'] ?? '请求失败')));
    $code = (string) ($payload['code'] ?? ($ok ? app_config('api.default_success_code', 'OK') : admin_import_error_code_for_status($status)));
    $error = $payload['error'] ?? ($ok ? null : $message);

    $envelope = [
        'ok' => $ok,
        'success' => $ok,
        'code' => $code,
        'message' => $message,
    ];
    if (!$ok || $error !== null) {
        $envelope['error'] = $error;
    }

    return array_merge($envelope, $payload, [
        'ok' => $ok,
        'success' => $ok,
        'code' => $code,
        'message' => $message,
    ]);
}

function admin_import_error_code_for_status(int $status): string
{
    return app_api_error_code_for_status($status);
}

function admin_import_bootstrap(): PDO
{
    $pdo = app_require_pdo();
    if (!$pdo instanceof PDO) {
        admin_import_json_response(false, ['error' => '数据库连接失败'], 500);
    }

    admin_auth();

    return $pdo;
}

function admin_import_require_post_csrf(): void
{
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'POST') {
        admin_import_json_response(false, ['error' => '仅支持 POST 请求'], 405);
    }

    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if (!app_validate_csrf(is_string($token) ? $token : null)) {
        admin_import_json_response(false, ['error' => 'CSRF 验证失败'], 419);
    }
}

function admin_import_normalize_email(string $email): string
{
    return strtolower(trim($email));
}

function admin_import_validate_school_email(string $email): ?string
{
    return admin_validate_school_email($email);
}

function admin_import_validate_phone(string $phone): ?string
{
    if ($phone === '') {
        return null;
    }
    if (!preg_match('/^\d{11}$/', $phone)) {
        return '手机号必须为11位数字';
    }

    return null;
}

function admin_import_validate_student_no(string $studentNo): ?string
{
    if (!preg_match('/^\d{8}$/', $studentNo)) {
        return '学号必须为8位数字';
    }

    return null;
}

function admin_import_map_gender(string $value): ?string
{
    $normalized = strtolower(trim($value));
    $map = [
        'male' => 'male',
        'm' => 'male',
        '男' => 'male',
        'female' => 'female',
        'f' => 'female',
        '女' => 'female',
        'other' => 'other',
        '其他' => 'other',
    ];

    return $map[$normalized] ?? null;
}

function admin_import_normalize_cell($value): string
{
    if ($value === null) {
        return '';
    }

    $text = trim((string) $value);
    if (str_starts_with($text, "\xEF\xBB\xBF")) {
        $text = substr($text, 3);
    }

    return trim($text);
}

function admin_import_normalize_row(array $row): array
{
    return array_map('admin_import_normalize_cell', $row);
}

function admin_import_trim_trailing_empty_cells(array $row): array
{
    while (!empty($row) && end($row) === '') {
        array_pop($row);
    }

    return array_values($row);
}

function admin_import_is_row_empty(array $row): bool
{
    foreach ($row as $value) {
        if (admin_import_normalize_cell($value) !== '') {
            return false;
        }
    }

    return true;
}

function admin_import_validate_header(array $headerRow, array $expectedHeaders): bool
{
    return admin_import_trim_trailing_empty_cells(admin_import_normalize_row($headerRow)) === $expectedHeaders;
}

function admin_import_read_rows_from_upload(?array $file): array
{
    if (!$file || !isset($file['error'], $file['tmp_name'], $file['name'])) {
        throw new RuntimeException('请选择导入文件');
    }
    if ((int) $file['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('请选择导入文件');
    }
    if ((int) $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('文件上传失败，请重试');
    }

    $name = (string) $file['name'];
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $tmpName = (string) $file['tmp_name'];

    $allowedExtensions = app_config('upload.import.allowed_extensions', []);
    if (!is_array($allowedExtensions)) {
        $allowedExtensions = [];
    }

    if (!in_array($ext, $allowedExtensions, true)) {
        throw new RuntimeException('仅支持 .' . implode(' 或 .', $allowedExtensions) . ' 文件');
    }

    return $ext === 'xlsx'
        ? admin_import_read_xlsx_rows($tmpName)
        : admin_import_read_csv_rows($tmpName);
}

function admin_import_read_csv_rows(string $path): array
{
    $contents = @file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('读取 CSV 文件失败');
    }

    if (str_starts_with($contents, "\xEF\xBB\xBF")) {
        $contents = substr($contents, 3);
    }
    if ($contents !== '' && !preg_match('//u', $contents)) {
        throw new RuntimeException('CSV 必须使用 UTF-8 编码');
    }

    $stream = fopen('php://temp', 'r+');
    if ($stream === false) {
        throw new RuntimeException('无法解析 CSV 文件');
    }

    fwrite($stream, $contents);
    rewind($stream);

    $rows = [];
    while (($row = fgetcsv($stream)) !== false) {
        $rows[] = is_array($row) ? $row : [];
    }

    fclose($stream);

    return $rows;
}

function admin_import_read_xlsx_rows(string $path): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('当前服务器未启用 ZipArchive，暂时无法读取 Excel，请改用 UTF-8 编码的 CSV');
    }

    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Excel 文件打开失败，请确认文件未损坏');
    }

    try {
        $sharedStrings = admin_import_read_xlsx_shared_strings($zip);
        $sheetPath = admin_import_find_first_sheet_path($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        if ($sheetXml === false) {
            throw new RuntimeException('Excel 文件中未找到工作表');
        }

        return admin_import_parse_xlsx_sheet_rows($sheetXml, $sharedStrings);
    } finally {
        $zip->close();
    }
}

function admin_import_read_xlsx_shared_strings(ZipArchive $zip): array
{
    $xml = $zip->getFromName('xl/sharedStrings.xml');
    if ($xml === false) {
        return [];
    }

    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
        libxml_clear_errors();
        throw new RuntimeException('Excel 共享字符串解析失败');
    }
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $strings = [];
    foreach ($xpath->query('/a:sst/a:si') as $item) {
        $parts = [];
        foreach ($xpath->query('.//a:t', $item) as $textNode) {
            $parts[] = $textNode->textContent;
        }
        $strings[] = implode('', $parts);
    }

    return $strings;
}

function admin_import_find_first_sheet_path(ZipArchive $zip): string
{
    $workbookPath = 'xl/workbook.xml';
    $workbookXml = $zip->getFromName('xl/workbook.xml');
    $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
    if ($workbookXml === false || $relsXml === false) {
        throw new RuntimeException('Excel 工作簿结构不完整');
    }

    $workbookDom = new DOMDocument();
    $relsDom = new DOMDocument();

    libxml_use_internal_errors(true);
    $workbookLoaded = $workbookDom->loadXML($workbookXml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
    $relsLoaded = $relsDom->loadXML($relsXml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA);
    libxml_clear_errors();

    if (!$workbookLoaded || !$relsLoaded) {
        throw new RuntimeException('Excel 工作簿解析失败');
    }

    $workbookXpath = new DOMXPath($workbookDom);
    $workbookXpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbookXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

    $sheetNode = $workbookXpath->query('/a:workbook/a:sheets/a:sheet[1]')->item(0);
    if (!$sheetNode instanceof DOMElement) {
        throw new RuntimeException('Excel 文件中没有工作表');
    }

    $relId = $sheetNode->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
    if ($relId === '') {
        throw new RuntimeException('Excel 工作表关系缺失');
    }

    $relsXpath = new DOMXPath($relsDom);
    $relsXpath->registerNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');

    $target = '';
    foreach ($relsXpath->query('/p:Relationships/p:Relationship') as $relNode) {
        if (
            $relNode instanceof DOMElement
            && $relNode->getAttribute('Id') === $relId
            && str_ends_with($relNode->getAttribute('Type'), '/worksheet')
        ) {
            $target = $relNode->getAttribute('Target');
            break;
        }
    }

    if ($target === '') {
        throw new RuntimeException('Excel 工作表路径解析失败');
    }

    return admin_import_resolve_zip_target($workbookPath, $target);
}

function admin_import_normalize_zip_path(string $path): string
{
    $parts = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return implode('/', $parts);
}

function admin_import_resolve_zip_target(string $sourcePath, string $target): string
{
    $target = str_replace('\\', '/', trim($target));
    if ($target === '') {
        throw new RuntimeException('Excel 工作表路径为空');
    }

    if (str_starts_with($target, '/')) {
        return admin_import_normalize_zip_path(ltrim($target, '/'));
    }

    $sourceDir = str_replace('\\', '/', dirname($sourcePath));
    if ($sourceDir === '.' || $sourceDir === '') {
        return admin_import_normalize_zip_path($target);
    }

    return admin_import_normalize_zip_path($sourceDir . '/' . $target);
}

function admin_import_parse_xlsx_sheet_rows(string $sheetXml, array $sharedStrings): array
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    if (!$dom->loadXML($sheetXml, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA)) {
        libxml_clear_errors();
        throw new RuntimeException('Excel 工作表解析失败');
    }
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

    $rows = [];
    foreach ($xpath->query('/a:worksheet/a:sheetData/a:row') as $rowNode) {
        $row = [];
        foreach ($xpath->query('a:c', $rowNode) as $cellNode) {
            if (!$cellNode instanceof DOMElement) {
                continue;
            }

            $ref = $cellNode->getAttribute('r');
            $colRef = preg_replace('/\d+/', '', $ref);
            $colIndex = admin_import_column_ref_to_index($colRef);
            $row[$colIndex] = admin_import_read_xlsx_cell_value($xpath, $cellNode, $sharedStrings);
        }

        if (empty($row)) {
            $rows[] = [];
            continue;
        }

        ksort($row);
        $maxIndex = max(array_keys($row));
        $normalized = [];
        for ($i = 0; $i <= $maxIndex; $i++) {
            $normalized[] = $row[$i] ?? '';
        }
        $rows[] = $normalized;
    }

    return $rows;
}

function admin_import_read_xlsx_cell_value(DOMXPath $xpath, DOMElement $cellNode, array $sharedStrings): string
{
    $type = $cellNode->getAttribute('t');

    if ($type === 'inlineStr') {
        $parts = [];
        foreach ($xpath->query('.//a:t', $cellNode) as $textNode) {
            $parts[] = $textNode->textContent;
        }

        return implode('', $parts);
    }

    $valueNode = $xpath->query('a:v', $cellNode)->item(0);
    $value = $valueNode ? $valueNode->textContent : '';

    if ($type === 's') {
        $index = (int) $value;
        return isset($sharedStrings[$index]) ? (string) $sharedStrings[$index] : '';
    }
    if ($type === 'b') {
        return $value === '1' ? 'TRUE' : 'FALSE';
    }

    return (string) $value;
}

function admin_import_column_ref_to_index(string $columnRef): int
{
    $columnRef = strtoupper($columnRef);
    $length = strlen($columnRef);
    $index = 0;

    for ($i = 0; $i < $length; $i++) {
        $index = ($index * 26) + (ord($columnRef[$i]) - 64);
    }

    return max(0, $index - 1);
}

function admin_import_fetch_simple_map(PDO $pdo, string $sql, string $keyField, ?string $valueField = null): array
{
    $stmt = $pdo->query($sql);
    $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
    $map = [];

    foreach ($rows as $row) {
        $key = strtolower(trim((string) ($row[$keyField] ?? '')));
        if ($key === '') {
            continue;
        }

        $map[$key] = $valueField === null ? true : ($row[$valueField] ?? null);
    }

    return $map;
}

function admin_import_sys_log(PDO $pdo, string $description, string $targetTable, int $targetId): void
{
    if (!function_exists('sys_log')) {
        return;
    }

    sys_log($pdo, $_SESSION['user_id'] ?? null, $description, $targetTable, $targetId);
}
