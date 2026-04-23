(function () {
    var DEFAULT_APP_PATHS = {
        login_url: '../login/login.php',
        logout_url: '../login/logout.php',
        assets: {
            student_css: 'css/student_style.css'
        },
        student: {
            spa_url: 'spa.html',
            api: {
                announcement: 'api/announcement.php',
                change_pwd: 'api/change_pwd.php',
                config: 'api/config.php',
                course_select: 'api/course_select.php',
                exam_info: 'api/exam_info.php',
                free_classroom: 'api/free_classroom.php',
                my_grades: 'api/my_grades.php',
                profile: 'api/profile.php',
                schedule: 'api/schedule.php',
                sidebar: 'api/sidebar.php',
                student_portal: 'api/student_portal.php'
            }
        }
    };

    function getAppPaths() {
        return window.APP_PATHS || {};
    }

    function getStudentPaths() {
        return getAppPaths().student || {};
    }

    function getStudentApiMap() {
        return getStudentPaths().api || {};
    }

    function getAssets() {
        return getAppPaths().assets || {};
    }

    function getVersions() {
        return getAppPaths().versions || {};
    }

    function getEnums() {
        return getAppPaths().enums || {};
    }

    function getStudentApiUrl(key, fallback) {
        return getStudentApiMap()[key] || DEFAULT_APP_PATHS.student.api[key] || fallback || '';
    }

    function getAssetUrl(key, fallback) {
        return getAssets()[key] || DEFAULT_APP_PATHS.assets[key] || fallback || '';
    }

    function getLoginUrl(fallback) {
        return getAppPaths().login_url || DEFAULT_APP_PATHS.login_url || fallback || '';
    }

    function getLogoutUrl(fallback) {
        return getAppPaths().logout_url || DEFAULT_APP_PATHS.logout_url || fallback || '';
    }

    function getStudentSpaUrl(fallback) {
        return getStudentPaths().spa_url || DEFAULT_APP_PATHS.student.spa_url || fallback || 'spa.html';
    }

    function getAssetVersion(key, fallback) {
        return getVersions()[key] || fallback || '';
    }

    function getEnumMap(key) {
        return getEnums()[key] || {};
    }

    function withVersion(url, version) {
        if (!url || !version) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + version;
    }

    function getSemesterLabel(semester, options) {
        var settings = options || {};
        var labelMap = getEnumMap('semester');
        var label = labelMap[semester] || semester || '';
        if (settings.withSuffix === false && /学期$/.test(label)) {
            label = label.replace(/学期$/, '');
        }
        if (settings.includeEnglish && semester) {
            label += ' (' + semester + ')';
        }
        return label;
    }

    function formatYearSemester(year, semester, options) {
        var label = getSemesterLabel(semester, options);
        return year ? String(year) + ' 年 ' + label : label;
    }

    function applySpaBindings() {
        var studentCss = document.getElementById('studentStyleCssLink');
        if (studentCss) {
            studentCss.href = withVersion(
                getAssetUrl('student_css'),
                getAssetVersion('student_css')
            );
        }

        var pwdForm = document.getElementById('pwdForm');
        if (pwdForm) {
            pwdForm.action = getStudentApiUrl('change_pwd');
        }

        var avatarForm = document.getElementById('avatarForm');
        if (avatarForm) {
            avatarForm.action = getStudentApiUrl('profile');
        }

        var infoForm = document.getElementById('infoForm');
        if (infoForm) {
            infoForm.action = getStudentApiUrl('profile');
        }
    }

    window.studentGetApiUrl = getStudentApiUrl;
    window.studentGetAssetUrl = getAssetUrl;
    window.studentGetLoginUrl = getLoginUrl;
    window.studentGetLogoutUrl = getLogoutUrl;
    window.studentGetSpaUrl = getStudentSpaUrl;
    window.studentGetAssetVersion = getAssetVersion;
    window.studentWithVersion = withVersion;
    window.studentGetEnumMap = getEnumMap;
    window.studentGetSemesterLabel = getSemesterLabel;
    window.studentFormatYearSemester = formatYearSemester;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applySpaBindings);
    } else {
        applySpaBindings();
    }
})();
