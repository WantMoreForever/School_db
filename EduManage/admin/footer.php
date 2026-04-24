<!-- admin/footer.php：管理后台通用页脚，负责收尾结构与公共脚本加载。 -->
</main>
<div class="modal fade" id="adminConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="adminConfirmTitle">确认操作</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="adminConfirmMessage">请确认是否继续。</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="adminConfirmCancel">取消</button>
                <button type="button" class="btn btn-danger" id="adminConfirmOk">确认</button>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= htmlspecialchars(app_url('admin/js/app_api.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
<script src="<?= htmlspecialchars(app_admin_js_url(), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>
</html>
