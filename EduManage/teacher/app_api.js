(function () {
    var DEFAULT_APP_PATHS = {
        assets: {
            teacher_css: 'style.css',
            teacher_app_api_js: 'app_api.js',
            default_avatar: '/uploads/RC.png',
            avatar_base_url: '/uploads/avatars/'
        },
        teacher: {
            index_url: 'index.php',
            api: {
                teacher: 'api/teacher.php',
                grades: 'api/grades.php',
                announcement: 'api/announcement.php',
                attendance: 'api/attendance.php',
                workload: 'api/workload.php',
                schedule: 'api/schedule.php',
                application: 'api/application.php'
            }
        },
        upload: {
            avatar: {
                max_size: 0,
                allowed_mimes: []
            }
        },
        enums: {},
        versions: {}
    };

    function getAppPaths() {
        return window.APP_PATHS || {};
    }

    function getTeacherPaths() {
        return getAppPaths().teacher || {};
    }

    function getTeacherApiMap() {
        return getTeacherPaths().api || {};
    }

    function getAssets() {
        return getAppPaths().assets || {};
    }

    function getEnums() {
        return getAppPaths().enums || {};
    }

    function getVersions() {
        return getAppPaths().versions || {};
    }

    function getUploadConfig() {
        return getAppPaths().upload || {};
    }

    function getTeacherApiUrl(key, fallback) {
        return getTeacherApiMap()[key]
            || DEFAULT_APP_PATHS.teacher.api[key]
            || fallback
            || '';
    }

    function getTeacherAssetUrl(key, fallback) {
        return getAssets()[key]
            || DEFAULT_APP_PATHS.assets[key]
            || fallback
            || '';
    }

    function getTeacherVersion(key, fallback) {
        var versions = getVersions();
        return versions[key] || fallback || '';
    }

    function withVersion(url, version) {
        if (!url || !version) {
            return url;
        }

        return url + (url.indexOf('?') === -1 ? '?' : '&') + version;
    }

    function getTeacherEnumMap(key) {
        var enums = getEnums();
        return enums[key] || DEFAULT_APP_PATHS.enums[key] || {};
    }

    function getTeacherEnumLabel(group, key, fallback) {
        var map = getTeacherEnumMap(group);
        return map && Object.prototype.hasOwnProperty.call(map, key)
            ? map[key]
            : (fallback || key || '');
    }

    function getTeacherEnumEntries(group) {
        var map = getTeacherEnumMap(group);
        return Object.keys(map).map(function (key) {
            return {
                key: key,
                label: map[key]
            };
        });
    }

    function getTeacherAvatarConfig() {
        var upload = getUploadConfig().avatar || {};
        return {
            max_size: upload.max_size || DEFAULT_APP_PATHS.upload.avatar.max_size,
            allowed_mimes: upload.allowed_mimes || DEFAULT_APP_PATHS.upload.avatar.allowed_mimes
        };
    }

    function getTeacherAvatarBaseUrl() {
        return getTeacherAssetUrl('avatar_base_url', DEFAULT_APP_PATHS.assets.avatar_base_url);
    }

    function getTeacherDefaultAvatarUrl() {
        return getTeacherAssetUrl('default_avatar', DEFAULT_APP_PATHS.assets.default_avatar);
    }

    function buildTeacherAvatarUrl(filename) {
        if (!filename) {
            return getTeacherAvatarBaseUrl();
        }

        return getTeacherAvatarBaseUrl().replace(/\/?$/, '/') + String(filename).replace(/^\/+/, '');
    }

    function formatTeacherSemesterLabel(semester, options) {
        var settings = options || {};
        var label = getTeacherEnumLabel('semester', semester, semester);
        if (settings.withSuffix === false && /学期$/.test(label)) {
            label = label.replace(/学期$/, '');
        }
        if (settings.includeEnglish && semester) {
            label += ' (' + semester + ')';
        }
        return label;
    }

    function getTeacherSemesterOptionsHtml(selectedValue, options) {
        var settings = options || {};
        var entries = getTeacherEnumEntries('semester');
        return entries.map(function (entry) {
            var selected = String(selectedValue || '') === entry.key ? ' selected' : '';
            var label = formatTeacherSemesterLabel(entry.key, settings);
            return '<option value="' + entry.key + '"' + selected + '>' + label + '</option>';
        }).join('');
    }

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

    window.AppApi = window.TeacherApi = {
        dataOf: dataOf,
        isSuccess: isSuccess,
        messageOf: messageOf,
        withVersion: withVersion,
        getTeacherApiUrl: getTeacherApiUrl,
        getTeacherAssetUrl: getTeacherAssetUrl,
        getTeacherVersion: getTeacherVersion,
        getTeacherEnumMap: getTeacherEnumMap,
        getTeacherEnumEntries: getTeacherEnumEntries,
        getTeacherEnumLabel: getTeacherEnumLabel,
        getTeacherAvatarConfig: getTeacherAvatarConfig,
        getTeacherDefaultAvatarUrl: getTeacherDefaultAvatarUrl,
        getTeacherAvatarBaseUrl: getTeacherAvatarBaseUrl,
        buildTeacherAvatarUrl: buildTeacherAvatarUrl,
        formatTeacherSemesterLabel: formatTeacherSemesterLabel,
        getTeacherSemesterOptionsHtml: getTeacherSemesterOptionsHtml
    };

    window.teacherGetApiUrl = getTeacherApiUrl;
    window.teacherGetAssetUrl = getTeacherAssetUrl;
    window.teacherGetVersion = getTeacherVersion;
    window.teacherGetEnumMap = getTeacherEnumMap;
    window.teacherGetEnumLabel = getTeacherEnumLabel;
    window.teacherGetAvatarConfig = getTeacherAvatarConfig;
    window.teacherGetDefaultAvatarUrl = getTeacherDefaultAvatarUrl;
    window.teacherBuildAvatarUrl = buildTeacherAvatarUrl;
    window.teacherFormatSemesterLabel = formatTeacherSemesterLabel;
    window.teacherGetSemesterOptionsHtml = getTeacherSemesterOptionsHtml;
})();
