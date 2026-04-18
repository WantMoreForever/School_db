// ============================================================
// change_pwd.js — 修改密码页交互逻辑
// 功能：显示/隐藏密码、强度指示条、确认密码匹配检测
// 修改成功后：不跳转，仅页面内提示（由 PHP 输出 .alert-success）
// ============================================================

(function () {

    // ── 显示 / 隐藏密码 ──────────────────────────────────────────
    document.querySelectorAll('.eye-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input    = document.getElementById(targetId);
            if (!input) return;
            if (input.type === 'password') {
                input.type       = 'text';
                btn.textContent  = '🙈';
            } else {
                input.type       = 'password';
                btn.textContent  = '👁';
            }
        });
    });

    // ── 密码强度检测 ─────────────────────────────────────────────
    var strengthBar   = document.getElementById('strengthBar');
    var strengthLabel = document.getElementById('strengthLabel');
    var newPwdInput   = document.getElementById('newPwd');
    var confPwdInput  = document.getElementById('confPwd');

    function checkStrength(val) {
        if (!strengthBar || !strengthLabel) return;
        var score = 0;
        if (val.length >= 8)             score++;
        if (val.length >= 12)            score++;
        if (/[A-Z]/.test(val))           score++;
        if (/[0-9]/.test(val))           score++;
        if (/[^A-Za-z0-9]/.test(val))    score++;

        var levels = [
            { pct: '0%',   color: '#e5e7eb', label: '',            labelColor: '' },
            { pct: '25%',  color: '#ef4444', label: '弱',          labelColor: '#ef4444' },
            { pct: '50%',  color: '#f59e0b', label: '一般',        labelColor: '#f59e0b' },
            { pct: '75%',  color: '#3b82f6', label: '较强',        labelColor: '#3b82f6' },
            { pct: '90%',  color: '#22c55e', label: '强',          labelColor: '#22c55e' },
            { pct: '100%', color: '#16a34a', label: '非常强 💪',  labelColor: '#16a34a' },
        ];
        var lvl = levels[Math.min(score, 5)];
        strengthBar.style.width      = lvl.pct;
        strengthBar.style.background = lvl.color;
        strengthLabel.textContent    = lvl.label;
        strengthLabel.style.color    = lvl.labelColor;

        // 新密码变化时重新校验确认密码
        checkMatch();
    }

    // ── 确认密码匹配检测 ─────────────────────────────────────────
    function checkMatch() {
        var matchHint = document.getElementById('matchHint');
        if (!confPwdInput || !matchHint) return;
        var confVal = confPwdInput.value;
        if (!confVal) {
            matchHint.textContent = '';
            confPwdInput.classList.remove('input-ok', 'input-error');
            return;
        }
        var newVal = newPwdInput ? newPwdInput.value : '';
        if (confVal === newVal) {
            matchHint.textContent = '✓ 两次密码一致';
            matchHint.className   = 'match-hint match-ok';
            confPwdInput.classList.add('input-ok');
            confPwdInput.classList.remove('input-error');
        } else {
            matchHint.textContent = '✗ 密码不一致';
            matchHint.className   = 'match-hint match-fail';
            confPwdInput.classList.add('input-error');
            confPwdInput.classList.remove('input-ok');
        }
    }

    // 绑定事件
    if (newPwdInput)  newPwdInput.addEventListener('input',  function () { checkStrength(this.value); });
    if (confPwdInput) confPwdInput.addEventListener('input', checkMatch);

    // ── 提交前客户端校验 ─────────────────────────────────────────
    var pwdForm   = document.getElementById('pwdForm');
    var submitBtn = document.getElementById('submitBtn');

    if (pwdForm) {
        pwdForm.addEventListener('submit', function (e) {
            var oldVal  = document.getElementById('oldPwd')  ? document.getElementById('oldPwd').value  : '';
            var newVal  = newPwdInput  ? newPwdInput.value  : '';
            var confVal = confPwdInput ? confPwdInput.value : '';

            if (!oldVal || !newVal || !confVal) {
                e.preventDefault();
                alert('请填写所有密码字段。');
                return;
            }
            if (newVal.length < 8) {
                e.preventDefault();
                alert('新密码至少需要 8 位。');
                return;
            }
            if (newVal !== confVal) {
                e.preventDefault();
                alert('两次密码不一致，请重新输入。');
                return;
            }
            // 防重复提交
            if (submitBtn) {
                submitBtn.disabled    = true;
                submitBtn.textContent = '提交中…';
            }
        });
    }

    // ── 成功提示自动淡出（5 秒后）────────────────────────────────
    var successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function () {
            successAlert.style.transition = 'opacity 0.6s ease';
            successAlert.style.opacity    = '0';
            setTimeout(function () {
                successAlert.style.display = 'none';
            }, 600);
        }, 5000);
    }

})();