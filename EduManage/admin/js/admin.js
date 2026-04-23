/*
 * admin/js/admin.js
 * 管理后台公共交互脚本：统一确认弹窗、AJAX 表单、导入模板和页面常用交互。
 */
(function () {
    function parseJsonResponse(res) {
        return res.json().catch(function () {
            return null;
        }).then(function (data) {
            var api = window.AppApi || {};
            var payloadOk = api.isSuccess ? api.isSuccess(data) : (data && data.ok !== false && data.success !== false);
            return { ok: res.ok && payloadOk, data: data };
        });
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function buildCsv(rows) {
        return rows.map(function (row) {
            return row.map(function (cell) {
                var text = String(cell == null ? '' : cell);
                return /[",\r\n]/.test(text) ? '"' + text.replace(/"/g, '""') + '"' : text;
            }).join(',');
        }).join('\r\n');
    }

    var AdminConfirm = (function () {
        var modalInstance = null;
        var modalEl = null;
        var titleEl = null;
        var messageEl = null;
        var okBtn = null;
        var cancelBtn = null;

        function ensureModal() {
            if (modalInstance) {
                return;
            }

            modalEl = document.getElementById('adminConfirmModal');
            titleEl = document.getElementById('adminConfirmTitle');
            messageEl = document.getElementById('adminConfirmMessage');
            okBtn = document.getElementById('adminConfirmOk');
            cancelBtn = document.getElementById('adminConfirmCancel');
            if (!modalEl || !titleEl || !messageEl || !okBtn || !cancelBtn) {
                return;
            }

            modalInstance = new bootstrap.Modal(modalEl);
        }

        function fallbackConfirm(message) {
            return Promise.resolve(window.confirm(message || '请确认是否继续。'));
        }

        function fallbackAlert(message) {
            window.alert(message || '操作提示');
            return Promise.resolve(true);
        }

        function confirm(options) {
            ensureModal();
            if (!modalInstance) {
                return fallbackConfirm(options && options.message ? options.message : '');
            }

            var opts = options || {};
            titleEl.textContent = opts.title || '确认操作';
            messageEl.textContent = opts.message || '请确认是否继续。';
            cancelBtn.style.display = 'inline-block';
            cancelBtn.textContent = opts.cancelText || '取消';
            okBtn.textContent = opts.confirmText || '确认';
            okBtn.className = 'btn ' + (opts.confirmClass || 'btn-danger');

            return new Promise(function (resolve) {
                var settled = false;

                function cleanup(result) {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    okBtn.removeEventListener('click', handleOk);
                    modalEl.removeEventListener('hidden.bs.modal', handleHidden);
                    resolve(result);
                }

                function handleOk() {
                    cleanup(true);
                    modalInstance.hide();
                }

                function handleHidden() {
                    cleanup(false);
                }

                okBtn.addEventListener('click', handleOk, { once: true });
                modalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });
                modalInstance.show();
            });
        }

        function alert(message, title) {
            ensureModal();
            if (!modalInstance) {
                return fallbackAlert(message);
            }

            titleEl.textContent = title || '提示';
            messageEl.textContent = message || '操作提示';
            cancelBtn.style.display = 'none';
            okBtn.textContent = '确定';
            okBtn.className = 'btn btn-primary';

            return new Promise(function (resolve) {
                var settled = false;

                function cleanup() {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    okBtn.removeEventListener('click', handleOk);
                    modalEl.removeEventListener('hidden.bs.modal', handleHidden);
                    resolve(true);
                }

                function handleOk() {
                    cleanup();
                    modalInstance.hide();
                }

                function handleHidden() {
                    cleanup();
                }

                okBtn.addEventListener('click', handleOk, { once: true });
                modalEl.addEventListener('hidden.bs.modal', handleHidden, { once: true });
                modalInstance.show();
            });
        }

        return {
            confirm: confirm,
            alert: alert
        };
    })();

    function showAdminAlert(message, title) {
        return AdminConfirm.alert(message, title);
    }

    function postUrlEncoded(url, body) {
        var payload = body instanceof URLSearchParams ? body : new URLSearchParams(body || {});
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'
            },
            body: payload.toString()
        }).then(parseJsonResponse);
    }

    function postFormData(url, formData) {
        return fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(parseJsonResponse);
    }

    function bindImportForm(options) {
        var opts = options || {};
        var form = document.getElementById(opts.formId || '');
        if (!form) {
            return;
        }

        var headers = Array.isArray(opts.headers) ? opts.headers : [];
        var sampleRows = Array.isArray(opts.sampleRows) ? opts.sampleRows : [];
        var errorEl = form.querySelector('.import-error');
        var resultEl = form.querySelector('.import-result');
        var errorListEl = form.querySelector('.import-error-list');
        var submitBtn = form.querySelector('button[type="submit"]');
        var xlsxBtn = document.getElementById(opts.xlsxButtonId || '');
        var csvBtn = document.getElementById(opts.csvButtonId || '');

        function resetFeedback() {
            if (errorEl) {
                errorEl.classList.add('d-none');
                errorEl.textContent = '';
            }
            if (resultEl) {
                resultEl.classList.add('d-none');
                resultEl.textContent = '';
                resultEl.classList.remove('alert-success', 'alert-warning');
            }
            if (errorListEl) {
                errorListEl.classList.add('d-none');
                errorListEl.innerHTML = '';
            }
        }

        function renderResult(data) {
            if (!resultEl) {
                return;
            }

            var successCount = Number(data && data.success_count ? data.success_count : 0);
            var failCount = Number(data && data.fail_count ? data.fail_count : 0);
            var message = data && data.message ? data.message : ('导入完成：成功 ' + successCount + ' 条，失败 ' + failCount + ' 条。');

            if (successCount > 0 && failCount === 0) {
                message += ' 页面即将刷新。';
            } else if (successCount > 0 && failCount > 0) {
                message += ' 关闭弹窗后刷新页面即可查看已导入数据。';
            }

            resultEl.textContent = message;
            resultEl.classList.remove('d-none');
            resultEl.classList.add(failCount > 0 ? 'alert-warning' : 'alert-success');

            if (errorListEl && data && Array.isArray(data.errors) && data.errors.length) {
                errorListEl.innerHTML = data.errors.map(function (item) {
                    return '<div>' + escapeHtml(item) + '</div>';
                }).join('');
                errorListEl.classList.remove('d-none');
            }
        }

        function downloadCsvTemplate() {
            var csv = '\uFEFF' + buildCsv([headers].concat(sampleRows));
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = opts.csvFilename || '导入模板.csv';
            link.click();
            setTimeout(function () {
                URL.revokeObjectURL(url);
            }, 500);
        }

        function downloadXlsxTemplate() {
            if (typeof XLSX === 'undefined') {
                showAdminAlert('Excel 模板库加载失败，请稍后重试或改用 CSV 模板。');
                return;
            }

            var ws = XLSX.utils.aoa_to_sheet([headers].concat(sampleRows));
            var wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, opts.sheetName || '模板');
            XLSX.writeFile(wb, opts.xlsxFilename || '导入模板.xlsx');
        }

        if (xlsxBtn) {
            xlsxBtn.addEventListener('click', downloadXlsxTemplate);
        }
        if (csvBtn) {
            csvBtn.addEventListener('click', downloadCsvTemplate);
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            resetFeedback();
            if (submitBtn) {
                submitBtn.disabled = true;
            }

            fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(parseJsonResponse).then(function (result) {
                if (!result.ok || !result.data || !result.data.ok) {
                    throw new Error(window.AppApi ? window.AppApi.messageOf(result.data, '导入失败') : (result.data && result.data.error ? result.data.error : '导入失败'));
                }

                renderResult(result.data);
                if ((result.data.fail_count || 0) === 0 && (result.data.success_count || 0) > 0) {
                    setTimeout(function () {
                        window.location.reload();
                    }, Number(opts.reloadDelay || 1200));
                }
            }).catch(function (err) {
                if (errorEl) {
                    errorEl.textContent = err.message;
                    errorEl.classList.remove('d-none');
                    return;
                }

                showAdminAlert(err.message);
            }).finally(function () {
                if (submitBtn) {
                    submitBtn.disabled = false;
                }
            });
        });
    }

    window.AdminConfirm = AdminConfirm;
    window.AdminUI = {
        alert: showAdminAlert,
        bindImportForm: bindImportForm,
        escapeHtml: escapeHtml,
        parseJsonResponse: parseJsonResponse,
        postFormData: postFormData,
        postUrlEncoded: postUrlEncoded
    };

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.classList) {
            return;
        }

        if (form.classList.contains('delete-form')) {
            if (form.dataset.confirming === '1') {
                e.preventDefault();
                return;
            }

            var button = form.querySelector('button[type="submit"]');
            if (!button) {
                return;
            }

            e.preventDefault();
            form.dataset.confirming = '1';
            AdminConfirm.confirm({
                title: button.dataset.confirmTitle || '确认操作',
                message: button.dataset.confirm || '请确认是否继续。',
                confirmText: button.dataset.confirmText || '确认',
                cancelText: button.dataset.cancelText || '取消',
                confirmClass: button.dataset.confirmClass || 'btn-danger'
            }).then(function (ok) {
                delete form.dataset.confirming;
                if (ok) {
                    form.submit();
                }
            });
            return;
        }

        if (!form.classList.contains('ajax-form')) {
            return;
        }

        e.preventDefault();

        var modalEl = form.closest('.modal');
        var errorEl = form.querySelector('.modal-error');
        if (errorEl) {
            errorEl.classList.add('d-none');
            errorEl.textContent = '';
        }

        var submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        fetch(form.action, {
            method: form.method || 'POST',
            body: new FormData(form),
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(parseJsonResponse).then(function (result) {
            if (!result.ok || !result.data || !result.data.ok) {
                throw new Error(window.AppApi ? window.AppApi.messageOf(result.data, '操作失败') : (result.data && result.data.error ? result.data.error : '操作失败'));
            }

            if (modalEl) {
                var modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) {
                    modal.hide();
                }
            }

            window.location.reload();
        }).catch(function (err) {
            if (errorEl) {
                errorEl.textContent = err.message;
                errorEl.classList.remove('d-none');
                return;
            }

            showAdminAlert(err.message);
        }).finally(function () {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
        });
    });

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.gen-pwd');
        if (!btn) {
            return;
        }

        var group = btn.closest('.input-group');
        var input = group ? group.querySelector('.pwd-input') : null;
        if (!input) {
            return;
        }

        var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%^&*()_-+';
        var pwd = '';
        for (var i = 0; i < 12; i += 1) {
            pwd += chars.charAt(Math.floor(Math.random() * chars.length));
        }

        input.value = pwd;
        input.type = 'text';
    });

    (function initResetPassword() {
        var config = window.AdminPageConfig || {};
        var modalEl = document.getElementById('confirmResetModal');
        if (!modalEl || !config.resetPasswordUrl) {
            return;
        }

        var modal = new bootstrap.Modal(modalEl);
        var resultModalEl = document.getElementById('resetResultModal');
        var resultModal = resultModalEl ? new bootstrap.Modal(resultModalEl) : null;
        var msgEl = document.getElementById('confirmResetMessage');
        var errEl = document.getElementById('confirmResetError');
        var confirmBtn = document.getElementById('confirmResetBtn');
        var resultMsgEl = document.getElementById('resetResultMessage');
        var resultPwdEl = document.getElementById('resetResultPassword');
        var resultOkBtn = document.getElementById('resetResultOk');
        var currentId = null;
        var currentRedirect = config.defaultRedirect || '';

        function showResetResult(message, password) {
            if (!resultModal) {
                window.alert(message);
                window.location.reload();
                return;
            }

            if (resultMsgEl) {
                resultMsgEl.textContent = message || '该用户密码已重置。';
            }
            if (resultPwdEl) {
                resultPwdEl.textContent = password || '123456';
            }
            resultModal.show();
        }

        document.addEventListener('click', function (e) {
            var trigger = e.target.closest('.btn-reset-pw');
            if (!trigger) {
                return;
            }

            currentId = trigger.getAttribute('data-user-id');
            currentRedirect = trigger.getAttribute('data-redirect') || config.defaultRedirect || '';

            var name = trigger.getAttribute('data-name') || '';
            if (msgEl) {
                msgEl.textContent = name ? ('确认将 ' + name + ' 的密码重置为 123456 吗？') : '确认要将该用户的密码重置为 123456 吗？';
            }
            if (errEl) {
                errEl.classList.add('d-none');
                errEl.textContent = '';
            }
            modal.show();
        });

        if (resultOkBtn && resultModal) {
            resultOkBtn.addEventListener('click', function () {
                resultModal.hide();
                window.location.reload();
            });
        }

        if (!confirmBtn) {
            return;
        }

        confirmBtn.addEventListener('click', function () {
            if (!currentId) {
                return;
            }

            confirmBtn.disabled = true;

            var body = new URLSearchParams();
            body.set('id', currentId);
            if (currentRedirect) {
                body.set('redirect', currentRedirect);
            }
            if (config.csrfToken) {
                body.set('csrf_token', config.csrfToken);
            }

            postUrlEncoded(config.resetPasswordUrl, body).then(function (result) {
                if (!result.ok || !result.data || !result.data.ok) {
                    throw new Error(window.AppApi ? window.AppApi.messageOf(result.data, '重置失败') : (result.data && result.data.error ? result.data.error : '重置失败'));
                }

                modal.hide();
                showResetResult(
                    result.data && result.data.message ? result.data.message : '密码已重置为 123456',
                    result.data && result.data.temp_password ? result.data.temp_password : '123456'
                );
            }).catch(function (err) {
                if (errEl) {
                    errEl.textContent = err.message;
                    errEl.classList.remove('d-none');
                    return;
                }

                showAdminAlert(err.message);
            }).finally(function () {
                confirmBtn.disabled = false;
            });
        });
    })();
})();
