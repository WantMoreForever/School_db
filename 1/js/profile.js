// ============================================================
// profile.js — 个人信息页交互逻辑
// 功能：头像点击展开上传表单、文件预览、撤销更改
// ============================================================

(function () {
    // ── 头像点击 → 展开上传表单 ────────────────────────────────
    const avatarWrap   = document.querySelector('.profile-avatar-wrap');
    const avatarForm   = document.getElementById('avatarForm');
    const avatarOverlay = document.getElementById('avatarOverlay');
    const avatarInput  = document.getElementById('avatarInput');
    const avatarSubmit = document.getElementById('avatarSubmit');
    const avatarCancel = document.getElementById('avatarCancel');
    const avatarFileLabel = document.querySelector('.avatar-file-label');
    const previewRow   = document.getElementById('avatarPreviewRow');
    const newPreviewImg = document.getElementById('avatarNewPreview');
    const fileNameSpan = document.getElementById('avatarFileName');

    // 点击头像区域：展开表单 + 触发文件选择
    if (avatarWrap && avatarForm) {
        avatarWrap.addEventListener('click', function () {
            avatarForm.classList.add('visible');
            avatarInput.click();
        });
    }

    // 点击文件标签也触发选择
    if (avatarFileLabel) {
        avatarFileLabel.addEventListener('click', function () {
            avatarInput.click();
        });
    }

    // 文件选择后：预览图片 + 显示文件名 + 启用上传按钮
    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            fileNameSpan.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';

            const reader = new FileReader();
            reader.onload = function (e) {
                newPreviewImg.src = e.target.result;
                previewRow.style.display = 'flex';
            };
            reader.readAsDataURL(file);

            avatarSubmit.disabled = false;
            avatarForm.classList.add('visible');
        });
    }

    // 取消按钮：收起表单 + 重置
    if (avatarCancel) {
        avatarCancel.addEventListener('click', function () {
            avatarForm.classList.remove('visible');
            avatarInput.value = '';
            if (newPreviewImg) newPreviewImg.src = '';
            if (previewRow)   previewRow.style.display = 'none';
            if (fileNameSpan) fileNameSpan.textContent = '';
            if (avatarSubmit) avatarSubmit.disabled = true;
        });
    }

    // 如果 URL 带 #avatar，自动展开上传区
    if (window.location.hash === '#avatar' && avatarForm) {
        avatarForm.classList.add('visible');
        setTimeout(function () {
            avatarForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 300);
    }
})();
// ── 手机号验证 ────────────────────────────────────────────────
(function () {
    var phoneInput = document.getElementById('phoneInput');
    var infoForm = document.getElementById('infoForm');

    // 在手机号输入框下方插入提示元素
    if (phoneInput) {
        var phoneHint = document.createElement('div');
        phoneHint.id = 'phoneHint';
        phoneHint.style.cssText = 'font-size:12px;margin-top:4px;min-height:16px;';
        phoneInput.parentNode.insertBefore(phoneHint, phoneInput.nextSibling);

        function validatePhone(val) {
            return /^1[3-9]\d{9}$/.test(val.trim());
        }

        function checkPhone() {
            var val = phoneInput.value.trim();
            if (!val) {
                phoneHint.textContent = '';
                phoneInput.classList.remove('input-ok', 'input-error');
                return true;
            }
            if (validatePhone(val)) {
                phoneHint.textContent = '✓ 格式正确';
                phoneHint.style.color = '#22c55e';
                phoneInput.classList.add('input-ok');
                phoneInput.classList.remove('input-error');
                return true;
            } else {
                phoneHint.textContent = '✗ 请输入有效的11位手机号';
                phoneHint.style.color = '#ef4444';
                phoneInput.classList.add('input-error');
                phoneInput.classList.remove('input-ok');
                return false;
            }
        }

        phoneInput.addEventListener('input', checkPhone);
        phoneInput.addEventListener('blur', checkPhone);

        // 表单提交前拦截
        if (infoForm) {
            infoForm.addEventListener('submit', function (e) {
                if (!checkPhone()) {
                    e.preventDefault();
                    phoneInput.focus();
                }
            });
        }
    }
})();
// ── 撤销信息更改（全局函数，供 HTML onclick 调用）─────────────
function resetInfoForm() {
    const form = document.getElementById('infoForm');
    if (form) form.reset();
}