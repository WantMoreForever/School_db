<!-- admin/partials/reset_password_modals.php：重置密码相关弹窗模板。 -->
<div class="modal fade" id="confirmResetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认重置密码</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p id="confirmResetMessage">确认要将该用户的密码重置为 <code>123456</code> 吗？</p>
                <div class="alert alert-danger d-none" id="confirmResetError"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                <button type="button" class="btn btn-primary" id="confirmResetBtn">确认</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="resetResultModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">密码重置成功</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="mb-2" id="resetResultMessage">该用户密码已重置。</p>
                <div class="reset-password-card">
                    <span class="reset-password-label">新密码</span>
                    <code id="resetResultPassword">123456</code>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" id="resetResultOk">知道了</button>
            </div>
        </div>
    </div>
</div>
