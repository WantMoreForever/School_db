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

    window.AppApi = window.AppApi || {
        dataOf: dataOf,
        isSuccess: isSuccess,
        messageOf: messageOf
    };
})();
