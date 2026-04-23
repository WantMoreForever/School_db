(function () {
    function isSuccess(payload) {
        if (!payload) return false;
        if (Object.prototype.hasOwnProperty.call(payload, 'ok')) return payload.ok === true;
        if (Object.prototype.hasOwnProperty.call(payload, 'success')) return payload.success !== false;
        return true;
    }

    function messageOf(payload, fallback) {
        if (!payload) return fallback || '请求失败';
        return payload.message || payload.msg || payload.error || fallback || '请求失败';
    }

    function dataOf(payload, fallback) {
        if (!payload) return fallback;
        return Object.prototype.hasOwnProperty.call(payload, 'data') ? payload.data : (fallback === undefined ? payload : fallback);
    }

    async function parseJson(response) {
        var payload = await response.json().catch(function () {
            return null;
        });
        if (!response.ok || !isSuccess(payload)) {
            throw new Error(messageOf(payload, '请求失败'));
        }
        return payload;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function showAlert(type, text, container) {
        var target = container || document.getElementById('alertsContainer') || document.body;
        if (!target) return;
        var div = document.createElement('div');
        div.className = type === 'success' ? 'alert alert-success fade-up' : 'alert alert-error fade-up';
        div.innerHTML = escapeHtml(text || (type === 'success' ? '操作成功' : '操作失败'));
        target.innerHTML = '';
        target.appendChild(div);
        setTimeout(function () {
            if (!div.parentNode) return;
            div.style.transition = 'opacity 0.6s ease';
            div.style.opacity = '0';
            setTimeout(function () {
                if (div.parentNode) div.parentNode.removeChild(div);
            }, 600);
        }, type === 'success' ? 2200 : 4200);
    }

    function confirm(message, options) {
        var opts = options || {};
        return new Promise(function (resolve) {
            var overlay = document.createElement('div');
            overlay.className = 'app-confirm-overlay';
            overlay.setAttribute('role', 'presentation');

            var modal = document.createElement('div');
            modal.className = 'app-confirm-modal fade-up';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-modal', 'true');

            modal.innerHTML =
                '<div class="app-confirm-title">' + escapeHtml(opts.title || '操作确认') + '</div>' +
                '<div class="app-confirm-message">' + escapeHtml(message || '请确认是否继续。') + '</div>' +
                '<div class="app-confirm-actions">' +
                '  <button type="button" class="btn btn-outline app-confirm-cancel">' + escapeHtml(opts.cancelText || '取消') + '</button>' +
                '  <button type="button" class="btn btn-primary app-confirm-ok">' + escapeHtml(opts.confirmText || '确认') + '</button>' +
                '</div>';

            overlay.appendChild(modal);
            document.body.appendChild(overlay);

            function cleanup(result) {
                overlay.classList.add('is-leaving');
                setTimeout(function () {
                    if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                    resolve(result);
                }, 160);
            }

            overlay.addEventListener('click', function (event) {
                if (event.target === overlay) cleanup(false);
            });
            modal.querySelector('.app-confirm-cancel').addEventListener('click', function () { cleanup(false); });
            modal.querySelector('.app-confirm-ok').addEventListener('click', function () { cleanup(true); });
            setTimeout(function () {
                var ok = modal.querySelector('.app-confirm-ok');
                if (ok) ok.focus();
            }, 30);
        });
    }

    window.AppApi = window.StudentApi = {
        confirm: confirm,
        dataOf: dataOf,
        escapeHtml: escapeHtml,
        isSuccess: isSuccess,
        messageOf: messageOf,
        parseJson: parseJson,
        showAlert: showAlert
    };
})();
