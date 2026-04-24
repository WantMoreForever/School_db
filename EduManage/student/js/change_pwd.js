// ============================================================
// change_pwd.js - 修改密码页面逻辑
// 功能：显示/隐藏密码、强度指示器、确认密码匹配校验
// ============================================================

(function () {
    var changePwdApi = window.studentGetApiUrl('change_pwd');
    var profileApi = window.studentGetApiUrl('profile');
    var loginUrl = window.studentGetLoginUrl();

    document.querySelectorAll('.eye-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.getAttribute('data-target');
            var input    = document.getElementById(targetId);
            if (!input) return;
            if (input.type === 'password') {
                input.type       = 'text';
                btn.textContent  = '👁';
            } else {
                input.type       = 'password';
                btn.textContent  = '👁';
            }
        });
    });

    var strengthBar   = document.getElementById('strengthBar');
    var strengthLabel = document.getElementById('strengthLabel');
    var newPwdInput   = document.getElementById('newPwd');
    var confPwdInput  = document.getElementById('confPwd');

    function checkStrength(val) {
        if (!strengthBar || !strengthLabel) return;
        var score = 0;
        if (val.length >= 6)             score++;
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
            { pct: '100%', color: '#16a34a', label: '非常强 ✓',  labelColor: '#16a34a' },
        ];
        var lvl = levels[Math.min(score, 5)];
        strengthBar.style.width      = lvl.pct;
        strengthBar.style.background = lvl.color;
        strengthLabel.textContent    = lvl.label;
        strengthLabel.style.color    = lvl.labelColor;

        checkMatch();
    }

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

    if (newPwdInput)  newPwdInput.addEventListener('input',  function () { checkStrength(this.value); });
    if (confPwdInput) confPwdInput.addEventListener('input', checkMatch);

    var pwdForm   = document.getElementById('pwdForm');
    var submitBtn = document.getElementById('submitBtn');

    function showAlert(type, message) {
        var scoped = document.getElementById('pwdAlerts') || document.getElementById('alertsContainer');
        if (window.StudentApi && window.StudentApi.showAlert) {
            window.StudentApi.showAlert(type, message, scoped);
            return;
        }

        var container = document.querySelector('.content') || document.body;
        if (!container) return;
        var existing = container.querySelector('.alert-success, .alert-error');
        if (existing) existing.remove();

        var div = document.createElement('div');
        div.className = type === 'success' ? 'alert-success fade-up' : 'alert-error fade-up';
        div.textContent = message;
        container.insertBefore(div, container.firstChild);

        if (type === 'success') {
            setTimeout(function () {
                div.style.transition = 'opacity 0.6s ease';
                div.style.opacity = '0';
                setTimeout(function () { div.remove(); }, 600);
            }, 2000);
        }
    }

    if (pwdForm) {
        pwdForm.addEventListener('submit', function (e) {
            e.preventDefault();

            var oldVal  = document.getElementById('oldPwd')  ? document.getElementById('oldPwd').value  : '';
            var newVal  = newPwdInput  ? newPwdInput.value  : '';
            var confVal = confPwdInput ? confPwdInput.value : '';

            if (!oldVal || !newVal || !confVal) {
                showAlert('error', '请填写所有必填字段。');
                return;
            }
            if (newVal.length < 6) {
                showAlert('error', '新密码至少需要 6 位。');
                return;
            }
            if (newVal !== confVal) {
                showAlert('error', '两次密码不一致，请重新输入。');
                return;
            }

            if (submitBtn) {
                submitBtn.disabled    = true;
                submitBtn.textContent = '提交中...';
            }

            var payload = {
                old_password: oldVal,
                new_password: newVal,
                confirm_password: confVal
            };

            if (window.__CSRF_TOKEN) payload.csrf_token = window.__CSRF_TOKEN;
            fetch(changePwdApi, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify(payload)
            }).then(function (resp) {
                return resp.json().catch(function () { return { ok: false, message: '请求未能返回 JSON 响应' }; });
            }).then(function (data) {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = '确认修改';
                }
                var success = window.StudentApi ? window.StudentApi.isSuccess(data) : (data && (data.ok || data.success));
                if (success) {
                    showAlert('success', window.StudentApi ? window.StudentApi.messageOf(data, '密码修改成功') : (data.message || data.msg || '密码修改成功'));
                    if (document.getElementById('oldPwd')) document.getElementById('oldPwd').value = '';
                    if (newPwdInput) newPwdInput.value = '';
                    if (confPwdInput) confPwdInput.value = '';
                    checkStrength('');
                    checkMatch();
                } else {
                    showAlert('error', window.StudentApi ? window.StudentApi.messageOf(data, '修改密码失败') : (data && (data.message || data.msg || data.error) ? (data.message || data.msg || data.error) : '修改密码失败'));
                }
            }).catch(function (err) {
                if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = '确认修改'; }
                showAlert('error', '系统异常无法提交：' + (err && err.message ? err.message : ''));
            });
        });
    }

    var successAlert = document.getElementById('successAlert');
    if (successAlert) {
        setTimeout(function () {
            successAlert.style.transition = 'opacity 0.6s ease';
            successAlert.style.opacity    = '0';
            setTimeout(function () { successAlert.style.display = 'none'; }, 600);
        }, 2000);
    }

})();

(function () {
    function initPage() {
        if (window.loadStudentSidebar) {
            window.loadStudentSidebar({ view: 'change_pwd' });
        }

        fetch(profileApi, { credentials: 'include' })
            .then(function (resp) {
                var ct = (resp.headers.get && resp.headers.get('content-type')) || '';
                if (!ct || ct.indexOf('application/json') === -1) {
                    if (resp.url && resp.url.indexOf(loginUrl) !== -1) window.location.href = loginUrl;
                    throw new Error('not-json');
                }
                return resp.json();
            })
            .then(function (data) {
                if (!data) return;
                if (data.error === 'unauthenticated') { window.location.href = loginUrl; return; }
                if (data.csrf_token) window.__CSRF_TOKEN = data.csrf_token;

                var alertsEl = document.getElementById('alertsContainer');
                var pwdAlertsEl = document.getElementById('pwdAlerts');
                if (data.alerts_html) {
                    try {
                        var tmp = document.createElement('div');
                        tmp.innerHTML = data.alerts_html || '';
                        var pwdRe = /密码|修改失败|密码修改|密码修改成功/;
                        var nodes = tmp.querySelectorAll('.alert-success, .alert-error');
                        nodes.forEach(function (node) {
                            var txt = (node.textContent || '').trim();
                            if (pwdRe.test(txt)) {
                                if (pwdAlertsEl) {
                                    pwdAlertsEl.innerHTML += node.outerHTML;
                                    var appended = pwdAlertsEl.lastElementChild;
                                    if (appended) {
                                        setTimeout(function () {
                                            if (appended && appended.parentNode) appended.parentNode.removeChild(appended);
                                        }, 2000);
                                    }
                                }
                                node.remove();
                            }
                        });
                        if (alertsEl) alertsEl.innerHTML = tmp.innerHTML;
                    } catch (e) {
                        if (alertsEl) alertsEl.innerHTML = data.alerts_html;
                    }
                }
            })
            .catch(function (err) {
                if (err && err.message === 'not-json') return;
                console.error('profile inject error', err);
            });
    }

    window.initChangePwd = initPage;

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initPage); else initPage();
})();
