// js/student_sidebar.js
// 渲染侧边栏，从 student sidebar API 获取数据并在前端渲染

(function () {
    var sidebarApi = window.studentGetApiUrl('sidebar');
    var loginUrl = window.studentGetLoginUrl();
    var defaultLogoutUrl = window.studentGetLogoutUrl();
    var spaUrl = window.studentGetSpaUrl();
    function esc(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function detectViewFromHash() {
        var h = (location.hash || '').toLowerCase();
        if (h.indexOf('#profile') !== -1) return 'profile';
        if (h.indexOf('#schedule') !== -1) return 'schedule';
        if (h.indexOf('#free_classroom') !== -1) return 'free_classroom';
        if (h.indexOf('#course') !== -1) return 'course';
        if (h.indexOf('#grades') !== -1) return 'grades';
        if (h.indexOf('#exam') !== -1) return 'exam';
        if (h.indexOf('#change_pwd') !== -1) return 'change_pwd';
        if (h.indexOf('#announcement') !== -1) return 'announcement';
        return 'portal';
    }

            function renderSidebar(payload, view) {
        var container = document.getElementById('sidebar-container');
        if (!container) return;

        var student = (payload && payload.student) || null;
        var meta = (payload && payload.meta) || {};
        var logoutUrl = meta.logout_url || defaultLogoutUrl;

        function active(v) {
            return view === v ? ' active' : '';
        }

        var avatarHtml = '';
        if (student && student.has_avatar) {
            avatarHtml = '<img class="avatar-img" src="' + esc(student.avatar_path || '') + '" alt="头像" style="width:44px;height:44px;min-width:44px;max-width:44px;border-radius:50%;object-fit:cover;flex-shrink:0;">';
        } else {
            avatarHtml = '<div class="avatar-circle">' + esc(student ? (student.avatar_initials || '?') : '?') + '</div>';
        }

        container.innerHTML = '' +
            '<aside class="sidebar">' +
            '  <div class="sidebar-logo">' +
            '    <div class="school-name" style="line-height:1.2;">吉林大学<br>教学管理系统<br><span style="font-size:11px;font-weight:normal;color:#94a3b8;">Jilin University Teaching Management System</span></div>' +
            '    <div class="portal-tag">学生门户</div>' +
            '    <button id="sidebarToggleBtn" class="sidebar-toggle" title="切换侧栏显示">‹</button>' +
            '  </div>' +
            '  <div class="sidebar-profile">' +
                 avatarHtml +
            '    <div>' +
            '      <div class="pname">' + esc(student ? (student.name || '未登录') : '未登录') + '</div>' +
            '      <div class="pid">' + esc(student ? (student.student_id || '无') : '无') + '</div>' +
            '    </div>' +
            '  </div>' +
            '  <nav class="sidebar-nav">' +
            '    <div class="nav-section-label">首页</div>' +
            '    <a class="nav-item' + active('portal') + '" href="' + spaUrl + '#portal"><span class="icon">🏠</span><span class="nav-text">首页</span></a>' +   
            '    <div class="nav-section-label">学业</div>' +
            '    <a class="nav-item' + active('course') + '" href="' + spaUrl + '#course"><span class="icon">📚</span><span class="nav-text">选课系统</span></a>' +
            '    <a class="nav-item' + active('grades') + '" href="' + spaUrl + '#grades"><span class="icon">📊</span><span class="nav-text">成绩查询</span></a>' +
            '    <a class="nav-item' + active('schedule') + '" href="' + spaUrl + '#schedule"><span class="icon">📅</span><span class="nav-text">课程表</span></a>' +
            '    <a class="nav-item' + active('free_classroom') + '" href="' + spaUrl + '#free_classroom"><span class="icon">🏫</span><span class="nav-text">空闲教室</span></a>' +
            '    <a class="nav-item' + active('exam') + '" href="' + spaUrl + '#exam"><span class="icon">📝</span><span class="nav-text">考试安排</span></a>' +
            '    <div class="nav-section-label">互动</div>' +
            '    <a class="nav-item' + active('announcement') + '" href="' + spaUrl + '#announcement"><span class="icon">📢</span><span class="nav-text">通知公告</span></a>' +
            '    <div class="nav-section-label">账户</div>' +
            '    <a class="nav-item' + active('profile') + '" href="' + spaUrl + '#profile"><span class="icon">👤</span><span class="nav-text">个人信息</span></a>' +
            '    <a class="nav-item' + active('change_pwd') + '" href="' + spaUrl + '#change_pwd"><span class="icon">🔒</span><span class="nav-text">修改密码</span></a>' +
            '    <a class="nav-item" href="javascript:void(0);" onclick="showLogoutModal(\'' + esc(logoutUrl) + '\')"><span class="icon">🚪</span><span class="nav-text">退出登录</span></a>' +
            '  </nav>' +
            '  <div class="sidebar-footer">' + esc(meta.version || 'v2.0 教学管理系统') + '</div>' +
            '</aside>';
    }

    function setSidebarActive(view) {
        var container = document.getElementById('sidebar-container');
        if (!container) return;
        var items = container.querySelectorAll('a.nav-item');
        if (!items || !items.length) return;

        items.forEach(function (a) {
            var href = (a.getAttribute('href') || '').toLowerCase();
            var isActive = false;
            if (view === 'profile' && href.indexOf('#profile') !== -1) isActive = true;
            if (view === 'portal' && href.indexOf('#portal') !== -1) isActive = true;
            if (view === 'schedule' && href.indexOf('#schedule') !== -1) isActive = true;
            if (view === 'free_classroom' && href.indexOf('#free_classroom') !== -1) isActive = true;
            if (view === 'course' && href.indexOf('#course') !== -1) isActive = true;
            if (view === 'grades' && href.indexOf('#grades') !== -1) isActive = true;
            if (view === 'exam' && href.indexOf('#exam') !== -1) isActive = true;
            if (view === 'change_pwd' && href.indexOf('#change_pwd') !== -1) isActive = true;
            if (view === 'announcement' && href.indexOf('#announcement') !== -1) isActive = true;
            a.classList.toggle('active', isActive);
        });
    }

    function loadStudentSidebar(options) {
        options = options || {};
        var view = options.view || detectViewFromHash();
        var force = !!options.force;
        var container = document.getElementById('sidebar-container');
        if (!container) return Promise.resolve(null);

        if (!force && container.querySelector('.sidebar')) {
            setSidebarActive(view);
            return Promise.resolve({ success: true, cached: true, view: view });
        }

        var sidebarUrl = sidebarApi + '?view=' + encodeURIComponent(view) + '&_t=' + Date.now();
        return fetch(sidebarUrl, {
            credentials: 'include',
            cache: 'no-store',
            headers: { 'Accept': 'application/json' }
        })
            .then(function (resp) {
                var ct = (resp.headers.get && resp.headers.get('content-type')) || '';
                if (!ct || ct.indexOf('application/json') === -1) {
                    if (resp.url && resp.url.indexOf(loginUrl) !== -1) window.location.href = loginUrl;
                    throw new Error('not-json');
                }
                return resp.json();
            })
            .then(function (data) {
                if (!data || !data.success) {
                    if (data && data.error === 'unauthenticated') window.location.href = loginUrl;
                    return data;
                }
                renderSidebar(data, view);
                return data;
            })
            .catch(function (err) {
                if (err && err.message === 'not-json') return null;
                console.error('sidebar load error', err);
                return null;
            });
    }

    window.loadStudentSidebar = loadStudentSidebar;
})();
