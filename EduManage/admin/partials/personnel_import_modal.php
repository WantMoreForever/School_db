<?php
/**
 * admin/partials/personnel_import_modal.php
 * 人员批量导入弹窗：由学生/教师页面传入模板、示例和接口配置。
 */
$importConfig = $importConfig ?? [];
$modalId = $importConfig['modal_id'] ?? 'importPersonnelModal';
$formId = $importConfig['form_id'] ?? 'importPersonnelForm';
$actionUrl = $importConfig['action_url'] ?? '';
$title = $importConfig['title'] ?? '批量导入';
$headersText = $importConfig['headers_text'] ?? '';
$requiredText = $importConfig['required_text'] ?? '';
$fileHelpText = $importConfig['file_help_text'] ?? '';
$xlsxButtonId = $importConfig['xlsx_button_id'] ?? 'downloadPersonnelTemplateXlsx';
$csvButtonId = $importConfig['csv_button_id'] ?? 'downloadPersonnelTemplateCsv';
$headers = $importConfig['headers'] ?? [];
$sampleRows = $importConfig['sample_rows'] ?? [];
$xlsxFilename = $importConfig['xlsx_filename'] ?? '人员导入模板.xlsx';
$csvFilename = $importConfig['csv_filename'] ?? '人员导入模板.csv';
$sheetName = $importConfig['sheet_name'] ?? '人员模板';
?>
<div class="modal fade" id="<?= h($modalId) ?>" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= h($title) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="<?= h($formId) ?>" action="<?= h($actionUrl) ?>" method="post" enctype="multipart/form-data">
                <div class="modal-body">
                    
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <button type="button" class="btn btn-outline-secondary" id="<?= h($xlsxButtonId) ?>">下载 Excel 模板</button>
                        <button type="button" class="btn btn-outline-secondary" id="<?= h($csvButtonId) ?>">下载 CSV 模板</button>
                    </div>
                    <div class="alert alert-danger d-none import-error" role="alert"></div>
                    <div class="alert d-none import-result" role="alert"></div>
                    <div class="small text-danger d-none import-error-list"></div>
                    <?= admin_csrf_input() ?>
                    <div class="mb-3">
                        <label class="form-label">导入文件</label>
                        <input type="file" name="import_file" class="form-control" accept=".xlsx,.csv" required>
                        <div class="form-text"><?= h($fileHelpText) ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="submit" class="btn btn-primary">开始导入</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    window.AdminUI.bindImportForm({
        formId: <?= json_encode($formId, JSON_UNESCAPED_UNICODE) ?>,
        xlsxButtonId: <?= json_encode($xlsxButtonId, JSON_UNESCAPED_UNICODE) ?>,
        csvButtonId: <?= json_encode($csvButtonId, JSON_UNESCAPED_UNICODE) ?>,
        headers: <?= json_encode($headers, JSON_UNESCAPED_UNICODE) ?>,
        sampleRows: <?= json_encode($sampleRows, JSON_UNESCAPED_UNICODE) ?>,
        xlsxFilename: <?= json_encode($xlsxFilename, JSON_UNESCAPED_UNICODE) ?>,
        csvFilename: <?= json_encode($csvFilename, JSON_UNESCAPED_UNICODE) ?>,
        sheetName: <?= json_encode($sheetName, JSON_UNESCAPED_UNICODE) ?>
    });
});
</script>
